<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks the processing state of Stripe events, and processes the events Stripe failed to deliver.
 *
 * Received webhooks are recorded as they are processed. Undelivered events are fetched from the
 * Events API, recorded as pending, and processed in small batches through Action Scheduler.
 * Only the event ID is stored, so each pending event is fetched again before it is processed.
 *
 * @internal Subject to change without notice between releases.
 */
class WC_Stripe_Event_Reconciler {
	public const PROCESS_ACTION = 'wc_stripe_process_pending_events';

	public const RECONCILE_ACTION = 'wc_stripe_reconcile_events';

	public const CLEANUP_ACTION = 'wc_stripe_delete_expired_events';

	public const CLAIM_ACQUIRED = 'acquired';

	public const CLAIM_PROCESSED = 'processed';

	public const CLAIM_LOCKED = 'locked';

	private const ACTION_GROUP = 'woocommerce-gateway-stripe';

	public const BATCH_SIZE = 5;

	/**
	 * Failed fetches and interrupted processing are retried once per reconciliation run, so this gives up after about a day.
	 */
	public const MAX_ATTEMPTS = 48;

	/**
	 * Well past the lock TTL and the delay of deferred webhooks, so only events whose processing died are retried.
	 */
	public const STUCK_AFTER = HOUR_IN_SECONDS;

	private const RECONCILE_INTERVAL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Matches how long Stripe lists events. Older records cannot prevent any duplicate processing.
	 */
	public const RETENTION_PERIOD = 30 * DAY_IN_SECONDS;

	public const PAGE_SIZE = 100;

	public const CURSOR_OPTION_PREFIX = 'wc_stripe_events_cursor_';

	public const LOCK_OPTION_PREFIX = 'wc_stripe_event_lock_';

	/**
	 * Longer than any handler run, so only a lock left behind by a request that died gets reclaimed.
	 */
	public const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Owners of the event locks held by this instance, keyed by event ID.
	 *
	 * @var array<string, string>
	 */
	private $lock_owners = [];

	/**
	 * Event store.
	 *
	 * @var WC_Stripe_Event_Store_Interface
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Event_Store_Interface|null $store Event store. Defaults to the post type store.
	 */
	public function __construct( ?WC_Stripe_Event_Store_Interface $store = null ) {
		$this->store = $store ?? WC_Stripe_Event_Post_Store::get_instance();
	}

	/**
	 * Registers the hooks.
	 */
	public function init(): void {
		// Registered even when the feature is disabled: Action Scheduler fails actions that have no callback,
		// so jobs queued before the feature was disabled need one to stop themselves.
		add_action( self::PROCESS_ACTION, [ $this, 'process_pending_events' ] );
		add_action( self::RECONCILE_ACTION, [ $this, 'reconcile' ], 10, 0 );
		add_action( self::CLEANUP_ACTION, [ $this, 'delete_expired_events' ], 10, 0 );

		if ( WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			add_action( 'action_scheduler_run_recurring_actions_schedule_hook', [ $this, 'maybe_schedule_reconciliation' ], 10, 0 );
		}
	}

	/**
	 * Schedules the deletion of expired records, then queues undelivered and stuck events and schedules their processing.
	 */
	public function reconcile(): void {
		if ( ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			self::unschedule();
			return;
		}

		$this->schedule_cleanup();
		$this->queue_undelivered_events();
		$this->requeue_stuck_events();
		$this->schedule_processing();
	}

	/**
	 * Schedules the recurring reconciliation, unless it is already scheduled.
	 */
	public function maybe_schedule_reconciliation(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() || as_has_scheduled_action( self::RECONCILE_ACTION, [], self::ACTION_GROUP ) ) {
			return;
		}

		as_schedule_recurring_action( time(), self::RECONCILE_INTERVAL, self::RECONCILE_ACTION, [], self::ACTION_GROUP );
	}

	/**
	 * Unschedules the recurring reconciliation and any pending processing.
	 */
	public static function unschedule(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::RECONCILE_ACTION, [], self::ACTION_GROUP );
		as_unschedule_all_actions( self::PROCESS_ACTION, [], self::ACTION_GROUP );
		as_unschedule_all_actions( self::CLEANUP_ACTION, [], self::ACTION_GROUP );
	}

	/**
	 * Deletes all event records, the listing cursors of both modes, and any event locks left behind.
	 *
	 * The cursors go too: without them, the next listing would start after the deleted events and never find them again.
	 *
	 * @return int Number of records deleted.
	 */
	public function reset(): int {
		global $wpdb;

		delete_option( self::CURSOR_OPTION_PREFIX . 'test' );
		delete_option( self::CURSOR_OPTION_PREFIX . 'live' );

		// Lock names include the event ID, so leftover locks can only be found by prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LOCK_OPTION_PREFIX ) . '%' ) );

		return $this->store->delete_all();
	}

	/**
	 * Records one page of events Stripe has not delivered as pending, starting after the previous page.
	 *
	 * The cursor is the ID of the last event listed, so pages are listed oldest first. The first call in each mode only
	 * starts the cursor, so events from before the feature was enabled are not processed.
	 *
	 * @return array {
	 *     Summary of the run.
	 *
	 *     @type string|null $cursor_before ID of the event the run started after.
	 *     @type string|null $cursor_after  ID of the event the next run will start after.
	 *     @type array[]     $events        Per event: id, type, created, status_before and result (queued, skipped, ignored or failed).
	 * }
	 */
	public function queue_undelivered_events(): array {
		$cursor_option = self::CURSOR_OPTION_PREFIX . ( WC_Stripe_Mode::is_test() ? 'test' : 'live' );
		$cursor        = get_option( $cursor_option );
		$cursor        = is_string( $cursor ) && 0 === strpos( $cursor, 'evt_' ) ? $cursor : null;
		$created_until = time() - $this->get_min_event_age();

		$summary = [
			'cursor_before' => $cursor,
			'cursor_after'  => $cursor,
			'events'        => [],
		];

		// Stripe lists the past 30 days of events, which may be a large backlog of events merchants already handled by hand.
		if ( null === $cursor ) {
			$summary['cursor_after'] = $this->move_cursor_to_newest_event( $cursor_option, $created_until );
			return $summary;
		}

		$response = WC_Stripe_API::retrieve(
			'events?' . http_build_query(
				[
					'delivery_success' => 'false',
					// The events right after the cursor, unlike starting_after, which pages towards older events.
					'ending_before'    => $cursor,
					'created[lte]'     => $created_until,
					'limit'            => self::PAGE_SIZE,
				]
			)
		);

		// A failed request must not look like an empty page, which would move the cursor past the events it missed.
		if ( ! $this->is_successful_response( $response ) || ! is_array( $response->data ?? null ) ) {
			// Stripe deletes events after 30 days, so the cursor is gone when this mode was not listed for that long.
			if ( is_object( $response ) && 'resource_missing' === ( $response->error->code ?? '' ) ) {
				$summary['cursor_after'] = $this->move_cursor_to_newest_event( $cursor_option, $created_until ) ?? $cursor;
			}
			return $summary;
		}

		$events = $response->data;

		// Otherwise a store without undelivered events would keep the cursor until Stripe deletes it.
		if ( ! $events ) {
			$summary['cursor_after'] = $this->move_cursor_to_newest_event( $cursor_option, $created_until ) ?? $cursor;
			return $summary;
		}

		// Retrieve the event records that are already registered to avoid duplicates.
		$registered_events = $this->store->get_many( wp_list_pluck( $events, 'id' ) );

		foreach ( $events as $event ) {
			$record        = $registered_events[ $event->id ] ?? null;
			$status_before = $record ? $record->status : null;

			if ( ! in_array( $event->type, WC_Stripe_Account::WEBHOOK_EVENTS, true ) ) {
				// Only process what the plugin's own endpoint subscribes to. The listing also returns events
				// that failed on other endpoints of the same account, which this store would never receive.
				$result = 'ignored';
			} elseif ( ! $record ) {
				$result = $this->queue_event( $event );
			} else {
				$result = 'skipped';
			}

			$summary['events'][] = [
				'id'            => $event->id,
				'type'          => $event->type,
				'created'       => (int) $event->created,
				'status_before' => $status_before,
				'result'        => $result,
			];
		}

		// The next run lists the page again, and only retries the failed events, since the others have records now.
		if ( in_array( 'failed', wp_list_pluck( $summary['events'], 'result' ), true ) ) {
			return $summary;
		}

		// Pages are still sorted newest first.
		$summary['cursor_after'] = $events[0]->id;
		update_option( $cursor_option, $summary['cursor_after'], false );

		// List the next page right away instead of in the next run, so a backlog does not wait. Enqueued unconditionally,
		// as in process_pending_events().
		if ( ! empty( $response->has_more ) ) {
			as_enqueue_async_action( self::RECONCILE_ACTION, [], self::ACTION_GROUP );
		}

		return $summary;
	}

	/**
	 * Records an undelivered event as pending, unless a delivery of the event recorded it since the page was listed.
	 *
	 * Stripe keeps retrying the delivery after the event is listed, and the merchant can resend it. Without the lock,
	 * a delivery recorded in the meantime would be reset to pending, or recorded twice when both are inserted at once.
	 *
	 * @param stdClass $event Stripe event.
	 * @return string `queued`, `skipped` when a delivery holds or has recorded the event, or `failed` when it could not be saved.
	 */
	private function queue_event( stdClass $event ): string {
		$lock_name = self::LOCK_OPTION_PREFIX . $event->id;
		$owner     = WC_Stripe_Option_Lock::acquire( $lock_name, self::LOCK_TTL );
		if ( null === $owner ) {
			return 'skipped';
		}

		try {
			if ( $this->store->get( $event->id ) ) {
				return 'skipped';
			}

			return $this->store->save( WC_Stripe_Event_Record::from_stripe_event( $event, WC_Stripe_Event_Store_Interface::STATUS_PENDING ) ) ? 'queued' : 'failed';
		} finally {
			WC_Stripe_Option_Lock::release( $lock_name, $owner );
		}
	}

	/**
	 * Moves the listing cursor to the newest event created up to the given time.
	 *
	 * @param string $cursor_option Name of the cursor option.
	 * @param int    $created_until Creation timestamp of the newest event to consider.
	 * @return string|null ID of the event, or null when there is none or Stripe could not be reached.
	 */
	private function move_cursor_to_newest_event( string $cursor_option, int $created_until ): ?string {
		$response = WC_Stripe_API::retrieve(
			'events?' . http_build_query(
				[
					'created[lte]' => $created_until,
					'limit'        => 1,
				]
			)
		);

		$event_id = $this->is_successful_response( $response ) ? $this->get_event_id( $response->data[0] ?? null ) : null;
		if ( null !== $event_id ) {
			update_option( $cursor_option, $event_id, false );
		}

		return $event_id;
	}

	/**
	 * Queues events again whose processing never finished, for example because the request died.
	 */
	public function requeue_stuck_events(): void {
		$records = $this->store->get_by_status(
			WC_Stripe_Event_Store_Interface::STATUS_PROCESSING,
			! WC_Stripe_Mode::is_test(),
			self::PAGE_SIZE,
			time() - self::STUCK_AFTER
		);

		foreach ( $records as $record ) {
			$record->status = WC_Stripe_Event_Store_Interface::STATUS_PENDING;
			$this->record_failed_attempt( $record );
		}
	}

	/**
	 * Schedules the deletion of expired records, unless it is already scheduled or running.
	 */
	public function schedule_cleanup(): void {
		if ( ! as_has_scheduled_action( self::CLEANUP_ACTION, [], self::ACTION_GROUP ) ) {
			as_enqueue_async_action( self::CLEANUP_ACTION, [], self::ACTION_GROUP );
		}
	}

	/**
	 * Deletes a batch of expired records and event locks, and schedules the next batch while there may be more.
	 */
	public function delete_expired_events(): void {
		if ( ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			return;
		}

		$deleted_records = $this->store->delete_older_than( time() - self::RETENTION_PERIOD, self::PAGE_SIZE );
		$deleted_locks   = $this->delete_expired_locks();

		// A full batch may have left more behind. Enqueued unconditionally, as in process_pending_events().
		if ( $deleted_records >= self::PAGE_SIZE || $deleted_locks >= self::PAGE_SIZE ) {
			as_enqueue_async_action( self::CLEANUP_ACTION, [], self::ACTION_GROUP );
		}
	}

	/**
	 * Deletes a batch of event locks left behind by requests that died before releasing them.
	 *
	 * A lock is only reclaimed by the next claim of the same event, which never comes for most events.
	 *
	 * @return int Number of locks deleted.
	 */
	private function delete_expired_locks(): int {
		global $wpdb;

		// Matches the lock's own expiry check on the timestamp before the colon, in the same statement as the delete,
		// so a lock reclaimed in the meantime carries a new timestamp and is kept.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST( SUBSTRING_INDEX( option_value, ':', 1 ) AS UNSIGNED ) <= %d LIMIT %d",
				$wpdb->esc_like( self::LOCK_OPTION_PREFIX ) . '%',
				time() - self::LOCK_TTL,
				self::PAGE_SIZE
			)
		);

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Schedules processing of pending events, unless it is already scheduled or running.
	 */
	public function schedule_processing(): void {
		if ( ! as_has_scheduled_action( self::PROCESS_ACTION, [], self::ACTION_GROUP ) ) {
			as_enqueue_async_action( self::PROCESS_ACTION, [], self::ACTION_GROUP );
		}
	}

	/**
	 * Processes a batch of pending events, then schedules the next batch if this one made progress.
	 *
	 * Without progress, for example while Stripe cannot be reached, the next reconciliation run starts the job again.
	 */
	public function process_pending_events(): void {
		if ( ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			return;
		}

		$records = $this->store->get_by_status( WC_Stripe_Event_Store_Interface::STATUS_PENDING, ! WC_Stripe_Mode::is_test(), self::BATCH_SIZE );

		if ( ! $records ) {
			return;
		}

		$handler   = $this->create_webhook_handler();
		$processed = false;

		foreach ( $records as $record ) {
			$event      = WC_Stripe_API::retrieve( 'events/' . $record->id );
			$event_json = $this->is_successful_response( $event ) && ! empty( $event->id ) ? wp_json_encode( $event ) : false;

			if ( false === $event_json ) {
				$this->record_failed_attempt( $record, is_object( $event ) && isset( $event->error->code ) ? (string) $event->error->code : '' );
				continue;
			}

			// A late delivery of the same event is being processed right now, and will update the record itself.
			if ( self::CLAIM_ACQUIRED !== $this->claim( $event ) ) {
				continue;
			}

			$processed = true;

			try {
				$handler->process_webhook( $event_json );
			} catch ( Throwable $e ) {
				$this->record_event( $event, WC_Stripe_Event_Store_Interface::STATUS_FAILED );
			} finally {
				$this->release( $event );
			}
		}

		// The current action is still marked in-progress, so as_has_scheduled_action() would block the next batch.
		if ( $processed ) {
			as_enqueue_async_action( self::PROCESS_ACTION, [], self::ACTION_GROUP );
		}
	}

	/**
	 * Creates the webhook handler that processes pending events, reporting back to this instance.
	 *
	 * @return WC_Stripe_Webhook_Handler
	 */
	protected function create_webhook_handler(): WC_Stripe_Webhook_Handler {
		return new WC_Stripe_Webhook_Handler( $this );
	}

	/**
	 * Counts a failed fetch or interrupted processing, and gives up on the event when it cannot succeed or has failed too often.
	 *
	 * @param WC_Stripe_Event_Record $record     Record of the event.
	 * @param string                 $error_code Stripe error code of a failed fetch, if any.
	 */
	private function record_failed_attempt( WC_Stripe_Event_Record $record, string $error_code = '' ): void {
		++$record->attempts;

		// Stripe only keeps events for 30 days, and only returns those of the account the keys belong to.
		if ( 'resource_missing' === $error_code || $record->attempts >= self::MAX_ATTEMPTS ) {
			$record->status = WC_Stripe_Event_Store_Interface::STATUS_ABANDONED;

			WC_Stripe_Logger::warning(
				'Gave up on processing a Stripe event.',
				[
					'event_id'   => $record->id,
					'event_type' => $record->type,
					'attempts'   => $record->attempts,
					'error_code' => $error_code,
				]
			);
		}

		$this->store->save( $record );
	}

	/**
	 * Claims an event for processing, so a webhook delivery and the processing job never process it at the same time.
	 *
	 * @param mixed $notification Stripe event.
	 * @return string One of the CLAIM_* constants.
	 */
	public function claim( $notification ): string {
		// Events without an ID cannot be tracked, so they are processed as before. The same goes for every event while the feature is disabled.
		$event_id = $this->get_event_id( $notification );
		if ( null === $event_id || ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			return self::CLAIM_ACQUIRED;
		}

		// Another request is processing the event right now.
		$owner = WC_Stripe_Option_Lock::acquire( self::LOCK_OPTION_PREFIX . $event_id, self::LOCK_TTL );
		if ( null === $owner ) {
			return self::CLAIM_LOCKED;
		}

		// Only processed events are skipped. Processing ones are either deferred, or their request died.
		$record = $this->store->get( $event_id );
		if ( $record && WC_Stripe_Event_Store_Interface::STATUS_PROCESSED === $record->status ) {
			WC_Stripe_Option_Lock::release( self::LOCK_OPTION_PREFIX . $event_id, $owner );
			return self::CLAIM_PROCESSED;
		}

		// Kept until release(), which the caller must call once the event is processed.
		$this->lock_owners[ $event_id ] = $owner;
		$this->record_event( $notification, WC_Stripe_Event_Store_Interface::STATUS_PROCESSING );

		return self::CLAIM_ACQUIRED;
	}

	/**
	 * Releases the lock taken by claim().
	 *
	 * @param mixed $notification Stripe event.
	 */
	public function release( $notification ): void {
		$event_id = $this->get_event_id( $notification );
		if ( null === $event_id || ! isset( $this->lock_owners[ $event_id ] ) ) {
			return;
		}

		WC_Stripe_Option_Lock::release( self::LOCK_OPTION_PREFIX . $event_id, $this->lock_owners[ $event_id ] );
		unset( $this->lock_owners[ $event_id ] );
	}

	/**
	 * Records an event as processed once the webhook handler is done with it.
	 *
	 * @param mixed $webhook_type Event type.
	 * @param mixed $notification Stripe event.
	 * @param mixed $order        Order the event was applied to, if any.
	 */
	public function mark_processed( $webhook_type, $notification, $order = null ): void {
		// Called by the webhook handler whether the feature is enabled or not.
		if ( ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			return;
		}

		$this->record_event( $notification, WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $order );
	}

	/**
	 * Sets the status of an event, creating its record when there is none.
	 *
	 * @param mixed  $notification Stripe event.
	 * @param string $status       One of the WC_Stripe_Event_Store_Interface::STATUS_* constants.
	 * @param mixed  $order        Order the event was applied to, if any.
	 */
	private function record_event( $notification, string $status, $order = null ): void {
		$event_id = $this->get_event_id( $notification );
		if ( null === $event_id ) {
			return;
		}

		$record = $this->store->get( $event_id ) ?? WC_Stripe_Event_Record::from_stripe_event( $notification, $status );

		$record->status = $status;
		if ( $order instanceof WC_Order ) {
			$record->order_id = $order->get_id();
		}

		$this->store->save( $record );
	}

	/**
	 * Whether a response of WC_Stripe_API::retrieve() is a successful one.
	 *
	 * Besides Stripe errors, retrieve() returns null while the API keys are known to be invalid, and a WP_Error when
	 * Stripe cannot be reached. WP_Error is an object without an `error` property, so it passes a check for one.
	 *
	 * @param mixed $response Response of WC_Stripe_API::retrieve().
	 * @return bool
	 */
	private function is_successful_response( $response ): bool {
		return is_object( $response ) && ! is_wp_error( $response ) && empty( $response->error );
	}

	/**
	 * Returns the ID of a Stripe event.
	 *
	 * @param mixed $notification Stripe event.
	 * @return string|null The ID, or null when the value is not an event with an ID.
	 */
	private function get_event_id( $notification ): ?string {
		$event_id = is_object( $notification ) ? ( $notification->id ?? null ) : null;

		return is_string( $event_id ) && '' !== $event_id ? $event_id : null;
	}

	/**
	 * Returns how old an event must be before it is listed as undelivered.
	 *
	 * Stripe retries failed deliveries on its own, first within minutes. Waiting leaves those retries
	 * to Stripe, instead of racing them.
	 *
	 * @return int Seconds.
	 */
	private function get_min_event_age(): int {
		return in_array( wp_get_environment_type(), [ 'local', 'development' ], true )
			// Development sites wait less, so the flow can be tested without waiting an hour.
			? 5 * MINUTE_IN_SECONDS
			: HOUR_IN_SECONDS;
	}
}
