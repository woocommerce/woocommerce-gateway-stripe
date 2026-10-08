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

	private const ACTION_GROUP = 'woocommerce-gateway-stripe';

	private const BATCH_SIZE = 5;

	private const PAGE_SIZE = 100;

	private const CURSOR_OPTION_PREFIX = 'wc_stripe_events_cursor_';

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
		add_action( 'wc_stripe_before_process_webhook', [ $this, 'mark_processing' ], 10, 2 );
		add_action( 'wc_stripe_webhook_received', [ $this, 'mark_processed' ], 10, 3 );
		add_action( self::PROCESS_ACTION, [ $this, 'process_pending_events' ] );
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
			$registered_events = $this->store->get( wp_list_pluck( $events, 'id' ) );

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
		$records = $this->store->get_by_status( WC_Stripe_Event_Store_Interface::STATUS_PENDING, self::BATCH_SIZE );

		if ( ! $records ) {
			return;
		}

		$handler = new WC_Stripe_Webhook_Handler();

		foreach ( $records as $record ) {
			$event      = WC_Stripe_API::retrieve( 'events/' . $record->id );
			$event_json = is_object( $event ) && empty( $event->error ) && ! empty( $event->id ) ? wp_json_encode( $event ) : false;

			if ( false === $event_json ) {
				$record->status = WC_Stripe_Event_Store_Interface::STATUS_FAILED;
				$this->store->save( $record );
				continue;
			}

			try {
				$handler->process_webhook( $event_json );
			} catch ( Throwable $e ) {
				$this->record_event( $event, WC_Stripe_Event_Store_Interface::STATUS_FAILED );
			}
		}

		// The current action is still marked in-progress, so as_has_scheduled_action() would block the next batch.
		as_enqueue_async_action( self::PROCESS_ACTION, [], self::ACTION_GROUP );
	}

	/**
	 * Records an event as processing when the webhook handler starts on it.
	 *
	 * @param mixed $webhook_type Event type.
	 * @param mixed $notification Stripe event.
	 */
	public function mark_processing( $webhook_type, $notification ): void {
		$this->record_event( $notification, WC_Stripe_Event_Store_Interface::STATUS_PROCESSING );
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
		if ( ! is_object( $notification ) ) {
			return;
		}

		$event_id = $notification->id ?? '';
		if ( ! is_string( $event_id ) || '' === $event_id ) {
			return;
		}

		$record = $this->store->get( [ $event_id ] )[ $event_id ] ?? WC_Stripe_Event_Record::from_stripe_event( $notification, $status );

		$record->status = $status;
		if ( $order instanceof WC_Order ) {
			$record->order_id = $order->get_id();
		}

		$this->store->save( $record );
	}
}
