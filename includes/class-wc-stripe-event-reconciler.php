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
 */
class WC_Stripe_Event_Reconciler {
	public const PROCESS_ACTION = 'wc_stripe_process_pending_events';

	public const RECONCILE_ACTION = 'wc_stripe_reconcile_events';

	public const CLEANUP_ACTION = 'wc_stripe_delete_expired_events';

	public const CLAIM_ACQUIRED = 'acquired';

	public const CLAIM_PROCESSED = 'processed';

	public const CLAIM_LOCKED = 'locked';

	private const ACTION_GROUP = 'woocommerce-gateway-stripe';

	private const BATCH_SIZE = 5;

	private const RECONCILE_INTERVAL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Matches how long Stripe lists events. Older records cannot prevent any duplicate processing.
	 */
	private const RETENTION_PERIOD = 30 * DAY_IN_SECONDS;

	private const PAGE_SIZE = 100;

	private const CURSOR_OPTION_PREFIX = 'wc_stripe_events_cursor_';

	private const LOCK_OPTION_PREFIX = 'wc_stripe_event_lock_';

	/**
	 * Longer than any handler run, so only a lock left behind by a request that died gets reclaimed.
	 */
	private const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

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
		add_action( 'wc_stripe_webhook_received', [ $this, 'mark_processed' ], 10, 3 );
		add_action( self::PROCESS_ACTION, [ $this, 'process_pending_events' ] );
		add_action( self::RECONCILE_ACTION, [ $this, 'reconcile' ], 10, 0 );
		add_action( self::CLEANUP_ACTION, [ $this, 'delete_expired_events' ], 10, 0 );
		add_action( 'action_scheduler_run_recurring_actions_schedule_hook', [ $this, 'maybe_schedule_reconciliation' ], 10, 0 );
	}

	/**
	 * Schedules the deletion of expired records, then queues undelivered events and schedules their processing.
	 */
	public function reconcile(): void {
		$this->schedule_cleanup();
		$this->queue_undelivered_events();
		$this->schedule_processing();
	}

	/**
	 * Schedules the recurring reconciliation, unless it is already scheduled.
	 */
	public function maybe_schedule_reconciliation(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::RECONCILE_ACTION, [], self::ACTION_GROUP ) ) {
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
	 * Deletes all event records and the listing cursors of both modes.
	 *
	 * The cursors go too: without them, the next listing would start after the deleted events and never find them again.
	 *
	 * @return int Number of records deleted.
	 */
	public function reset(): int {
		delete_option( self::CURSOR_OPTION_PREFIX . 'test' );
		delete_option( self::CURSOR_OPTION_PREFIX . 'live' );

		return $this->store->delete_all();
	}

	/**
	 * Records events Stripe has not delivered as pending, starting from where the previous call stopped.
	 *
	 * @return array {
	 *     Summary of the run.
	 *
	 *     @type int|null $cursor_before Creation timestamp the run started from.
	 *     @type int|null $cursor_after  Creation timestamp the next run will start from.
	 *     @type array[]  $events        Per event: id, type, created, status_before and result (queued or skipped).
	 * }
	 */
	public function queue_undelivered_events(): array {
		$cursor_option  = self::CURSOR_OPTION_PREFIX . ( WC_Stripe_Mode::is_test() ? 'test' : 'live' );
		$cursor         = (int) get_option( $cursor_option, 0 );
		$max_created    = $cursor;
		$starting_after = '';
		$results        = [];

		do {
			$query = [
				'delivery_success' => 'false',
				'created[lte]'     => time() - $this->get_min_event_age(),
				'limit'            => self::PAGE_SIZE,
			];
			// gte, not gt: other events may share the cursor's second. Already-recorded ones are skipped below.
			if ( $cursor ) {
				$query['created[gte]'] = $cursor;
			}
			if ( $starting_after ) {
				$query['starting_after'] = $starting_after;
			}

			$response = WC_Stripe_API::retrieve( 'events?' . http_build_query( $query ) );
			if ( ! is_object( $response ) || ! empty( $response->error ) ) {
				// Leave the cursor alone so the next run covers this window again.
				$max_created = $cursor;
				break;
			}

			$events = $response->data ?? [];
			if ( ! $events ) {
				break;
			}

			// Retrieve the event records that are already registered to avoid duplicates.
			$registered_events = $this->store->get_many( wp_list_pluck( $events, 'id' ) );

			foreach ( $events as $event ) {
				$record        = $registered_events[ $event->id ] ?? null;
				$status_before = $record ? $record->status : null;

				if ( ! $record ) {
					$this->store->save( WC_Stripe_Event_Record::from_stripe_event( $event, WC_Stripe_Event_Store_Interface::STATUS_PENDING ) );
					$result = 'queued';
				} else {
					$result = 'skipped';
				}

				$max_created = max( $max_created, (int) $event->created );

				$results[] = [
					'id'            => $event->id,
					'type'          => $event->type,
					'created'       => (int) $event->created,
					'status_before' => $status_before,
					'result'        => $result,
				];
			}

			$starting_after = end( $events )->id;
		} while ( ! empty( $response->has_more ) );

		if ( $max_created > $cursor ) {
			update_option( $cursor_option, $max_created, false );
		}

		return [
			'cursor_before' => $cursor ? $cursor : null,
			'cursor_after'  => $max_created ? $max_created : null,
			'events'        => $results,
		];
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
	 * Deletes a batch of expired records, and schedules the next batch while there may be more.
	 */
	public function delete_expired_events(): void {
		$deleted = $this->store->delete_older_than( time() - self::RETENTION_PERIOD, self::PAGE_SIZE );

		// A full batch may have left more behind. Enqueued unconditionally, as in process_pending_events().
		if ( $deleted >= self::PAGE_SIZE ) {
			as_enqueue_async_action( self::CLEANUP_ACTION, [], self::ACTION_GROUP );
		}
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
	 * Processes a batch of pending events, then schedules the next batch.
	 */
	public function process_pending_events(): void {
		$records = $this->store->get_by_status( WC_Stripe_Event_Store_Interface::STATUS_PENDING, ! WC_Stripe_Mode::is_test(), self::BATCH_SIZE );

		if ( ! $records ) {
			return;
		}

		$handler = new WC_Stripe_Webhook_Handler( $this );

		foreach ( $records as $record ) {
			$event      = WC_Stripe_API::retrieve( 'events/' . $record->id );
			$event_json = is_object( $event ) && empty( $event->error ) && ! empty( $event->id ) ? wp_json_encode( $event ) : false;

			if ( false === $event_json ) {
				$record->status = WC_Stripe_Event_Store_Interface::STATUS_FAILED;
				$this->store->save( $record );
				continue;
			}

			// A late delivery of the same event is being processed right now, and will update the record itself.
			if ( self::CLAIM_ACQUIRED !== $this->claim( $event ) ) {
				continue;
			}

			try {
				$handler->process_webhook( $event_json );
			} catch ( Throwable $e ) {
				$this->record_event( $event, WC_Stripe_Event_Store_Interface::STATUS_FAILED );
			} finally {
				$this->release( $event );
			}
		}

		// The current action is still marked in-progress, so as_has_scheduled_action() would block the next batch.
		as_enqueue_async_action( self::PROCESS_ACTION, [], self::ACTION_GROUP );
	}

	/**
	 * Claims an event for processing, so a webhook delivery and the batch job never process it at the same time.
	 *
	 * @param mixed $notification Stripe event.
	 * @return string One of the CLAIM_* constants.
	 */
	public function claim( $notification ): string {
		// Events without an ID cannot be tracked, so they are processed as before.
		$event_id = $this->get_event_id( $notification );
		if ( null === $event_id ) {
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
