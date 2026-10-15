<?php
/**
 * Tests for WC_Stripe_Event_Reconciler.
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once __DIR__ . '/helpers/class-wc-stripe-in-memory-event-store.php';

/**
 * WC_Stripe_Event_Reconciler_Test class.
 */
class WC_Stripe_Event_Reconciler_Test extends WP_UnitTestCase {

	/**
	 * Private in the reconciler, so repeated here.
	 */
	private const LOCK_PREFIX   = 'wc_stripe_event_lock_';
	private const CURSOR_OPTION = 'wc_stripe_events_cursor_test';
	private const GROUP         = 'woocommerce-gateway-stripe';

	/**
	 * @var WC_Stripe_In_Memory_Event_Store
	 */
	private $store;

	/**
	 * @var WC_Stripe_Event_Reconciler
	 */
	private $reconciler;

	/**
	 * Responses served to Stripe API requests, keyed by request path (without query). Each is a list consumed in order.
	 *
	 * @var array<string, array[]>
	 */
	private $responses = [];

	/**
	 * URLs of every Stripe API request.
	 *
	 * @var string[]
	 */
	private $requests = [];

	public function set_up() {
		parent::set_up();

		$settings             = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, 'yes' );
		// Left behind by other tests, it makes WC_Stripe_API::retrieve() return null without a request.
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY, 'test' );

		$this->store      = new WC_Stripe_In_Memory_Event_Store();
		$this->reconciler = new WC_Stripe_Event_Reconciler( $this->store );
		$this->responses  = [];
		$this->requests   = [];

		add_filter( 'pre_http_request', [ $this, 'serve_stripe_response' ], 10, 3 );
		$this->unschedule_actions();
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'serve_stripe_response' ], 10 );
		$this->unschedule_actions();
		parent::tear_down();
	}

	/**
	 * Each claim outcome, and what it leaves behind.
	 *
	 * @param string|null $stored_status Status of the stored record, or null for none.
	 * @param bool        $locked        Whether another request holds the event lock.
	 * @param string      $expected      Expected claim result.
	 * @param string|null $status_after  Expected stored status afterwards.
	 * @param bool        $lock_held     Whether this instance should hold the lock afterwards.
	 * @dataProvider provide_claim_cases
	 */
	public function test_claim( ?string $stored_status, bool $locked, string $expected, ?string $status_after, bool $lock_held ): void {
		$event = $this->make_event( 'evt_1Claim' );
		if ( null !== $stored_status ) {
			$this->store->seed( WC_Stripe_Event_Record::from_stripe_event( $event, $stored_status ) );
		}
		if ( $locked ) {
			WC_Stripe_Option_Lock::acquire( self::LOCK_PREFIX . 'evt_1Claim', 600 );
		}

		$this->assertSame( $expected, $this->reconciler->claim( $event ) );

		$record = $this->store->get( 'evt_1Claim' );
		$this->assertSame( $status_after, $record ? $record->status : null );

		if ( ! $locked ) {
			$this->assertSame( $lock_held, false !== $this->read_lock( 'evt_1Claim' ) );
		}
	}

	/**
	 * @return array
	 */
	public function provide_claim_cases(): array {
		$s = 'WC_Stripe_Event_Store_Interface';
		return [
			'unknown event'                => [ null, false, WC_Stripe_Event_Reconciler::CLAIM_ACQUIRED, $s::STATUS_PROCESSING, true ],
			'pending event'                => [ $s::STATUS_PENDING, false, WC_Stripe_Event_Reconciler::CLAIM_ACQUIRED, $s::STATUS_PROCESSING, true ],
			'processing event (deferred)'  => [ $s::STATUS_PROCESSING, false, WC_Stripe_Event_Reconciler::CLAIM_ACQUIRED, $s::STATUS_PROCESSING, true ],
			'processed event'              => [ $s::STATUS_PROCESSED, false, WC_Stripe_Event_Reconciler::CLAIM_PROCESSED, $s::STATUS_PROCESSED, false ],
			'locked by another request'    => [ null, true, WC_Stripe_Event_Reconciler::CLAIM_LOCKED, null, false ],
			'locked and already processed' => [ $s::STATUS_PROCESSED, true, WC_Stripe_Event_Reconciler::CLAIM_LOCKED, $s::STATUS_PROCESSED, false ],
		];
	}

	/**
	 * Events without an ID, and every event while the feature is disabled, are processed as before and not tracked.
	 *
	 * @param mixed $event        Event.
	 * @param bool  $flag_enabled Whether the feature is enabled.
	 * @dataProvider provide_untracked_events
	 */
	public function test_claim_does_not_track( $event, bool $flag_enabled ): void {
		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, $flag_enabled ? 'yes' : 'no' );

		$this->assertSame( WC_Stripe_Event_Reconciler::CLAIM_ACQUIRED, $this->reconciler->claim( $event ) );
		$this->assertSame( [], $this->store->records );
	}

	/**
	 * @return array
	 */
	public function provide_untracked_events(): array {
		return [
			'event without ID'    => [ (object) [ 'type' => 'charge.succeeded' ], true ],
			'empty event ID'      => [ (object) [ 'id' => '' ], true ],
			'not an object'       => [ [ 'id' => 'evt_1Array' ], true ],
			'feature flag is off' => [ (object) [ 'id' => 'evt_1Off' ], false ],
		];
	}

	/**
	 * release() frees the lock taken by claim(), and leaves locks it does not own alone.
	 */
	public function test_release(): void {
		$event = $this->make_event( 'evt_1Release' );
		$this->reconciler->claim( $event );
		$this->reconciler->release( $event );
		$this->assertFalse( $this->read_lock( 'evt_1Release' ) );

		WC_Stripe_Option_Lock::acquire( self::LOCK_PREFIX . 'evt_1Other', 600 );
		$this->reconciler->release( $this->make_event( 'evt_1Other' ) );
		$this->assertNotFalse( $this->read_lock( 'evt_1Other' ) );
	}

	/**
	 * A completed event is recorded as processed with its order, a deferred one keeps its status, and a failed one is
	 * abandoned unless it was processed already. All are no-ops while the feature is disabled.
	 *
	 * @param bool        $flag_enabled  Whether the feature is enabled.
	 * @param string|null $stored_status Status of the stored record, or null for none.
	 * @param string      $outcome       Reported outcome.
	 * @param string|null $expected      Expected stored status.
	 * @dataProvider provide_record_outcome_cases
	 */
	public function test_record_outcome( bool $flag_enabled, ?string $stored_status, string $outcome, ?string $expected ): void {
		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, $flag_enabled ? 'yes' : 'no' );
		if ( null !== $stored_status ) {
			$this->store->seed( $this->make_record( 'evt_1Outcome', $stored_status ) );
		}
		$order = WC_Helper_Order::create_order();

		$this->reconciler->record_outcome( $outcome, $this->make_event( 'evt_1Outcome' ), $order );

		$record = $this->store->get( 'evt_1Outcome' );
		$this->assertSame( $expected, $record ? $record->status : null );
		if ( WC_Stripe_Event_Reconciler::OUTCOME_COMPLETED === $outcome && $record ) {
			$this->assertSame( $order->get_id(), $record->order_id );
		}
	}

	/**
	 * @return array
	 */
	public function provide_record_outcome_cases(): array {
		$processing = WC_Stripe_Event_Store_Interface::STATUS_PROCESSING;
		$processed  = WC_Stripe_Event_Store_Interface::STATUS_PROCESSED;
		$abandoned  = WC_Stripe_Event_Store_Interface::STATUS_ABANDONED;
		$completed  = WC_Stripe_Event_Reconciler::OUTCOME_COMPLETED;
		$deferred   = WC_Stripe_Event_Reconciler::OUTCOME_DEFERRED;
		$failed     = WC_Stripe_Event_Reconciler::OUTCOME_FAILED;

		return [
			'completed'                  => [ true, $processing, $completed, $processed ],
			'completed without a record' => [ true, null, $completed, $processed ],
			'deferred keeps processing'  => [ true, $processing, $deferred, $processing ],
			'failed is abandoned'        => [ true, $processing, $failed, $abandoned ],
			'failed after processed'     => [ true, $processed, $failed, $processed ],
			'disabled'                   => [ false, null, $completed, null ],
			'disabled failure'           => [ false, $processing, $failed, $processing ],
		];
	}

	/**
	 * While the feature is disabled, scheduled jobs neither call Stripe nor touch records.
	 */
	public function test_jobs_are_no_ops_while_disabled(): void {
		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, 'no' );
		update_option( self::CURSOR_OPTION, 'evt_1Cursor' );
		$this->store->seed( $this->make_record( 'evt_1Pending', WC_Stripe_Event_Store_Interface::STATUS_PENDING, time() - 40 * DAY_IN_SECONDS ) );
		as_schedule_recurring_action( time(), HOUR_IN_SECONDS, WC_Stripe_Event_Reconciler::RECONCILE_ACTION, [], self::GROUP );

		$this->reconciler->reconcile();
		$this->reconciler->process_event( 'evt_1Pending' );
		$this->reconciler->delete_expired_events();

		$this->assertSame( [], $this->requests );
		$this->assertSame( WC_Stripe_Event_Store_Interface::STATUS_PENDING, $this->store->get( 'evt_1Pending' )->status );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::RECONCILE_ACTION, [], self::GROUP ), 'reconcile() unschedules itself.' );
	}

	/**
	 * Without a cursor, the listing only anchors at the newest event, so older events are never processed.
	 */
	public function test_queue_undelivered_events_without_cursor_anchors_and_stops(): void {
		$this->queue_response( 'events', $this->make_list( [ $this->make_event( 'evt_1Newest' ) ] ) );

		$summary = $this->reconciler->queue_undelivered_events();

		$this->assertNull( $summary['cursor_before'] );
		$this->assertSame( 'evt_1Newest', $summary['cursor_after'] );
		$this->assertSame( [], $summary['events'] );
		$this->assertSame( 'evt_1Newest', get_option( self::CURSOR_OPTION ) );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( '1', $this->query_of( $this->requests[0] )['limit'] );
		$this->assertEqualsWithDelta( time(), (int) $this->query_of( $this->requests[0] )['created']['lte'], 5, 'Starts at the newest event, not the newest one old enough to list.' );
		$this->assertSame( [], $this->store->records );
	}

	/**
	 * Disabling the feature forgets the cursors of both modes, so enabling it again does not list the events from
	 * the time no deliveries were recorded.
	 */
	public function test_disabling_the_feature_forgets_the_cursors(): void {
		update_option( self::CURSOR_OPTION, 'evt_1Cursor' );
		update_option( 'wc_stripe_events_cursor_live', 'evt_1Live' );

		update_option( WC_Stripe_Feature_Flags::EVENT_RECONCILIATION_FEATURE_FLAG_NAME, 'no' );
		$this->reconciler->handle_feature_flag_change();

		$this->assertFalse( get_option( self::CURSOR_OPTION ) );
		$this->assertFalse( get_option( 'wc_stripe_events_cursor_live' ) );
	}

	/**
	 * A page of undelivered events queues new subscribed events, skips known ones, ignores other types, and moves the cursor.
	 */
	public function test_queue_undelivered_events_queues_a_page(): void {
		update_option( self::CURSOR_OPTION, 'evt_1Cursor' );
		$this->store->seed( $this->make_record( 'evt_1Known', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED ) );
		$this->queue_response(
			'events',
			$this->make_list(
				[
					$this->make_event( 'evt_1New', 'charge.succeeded' ),
					$this->make_event( 'evt_1Known', 'charge.failed' ),
					$this->make_event( 'evt_1Foreign', 'invoice.paid' ),
				]
			)
		);

		$summary = $this->reconciler->queue_undelivered_events();

		$this->assertSame(
			[
				'evt_1New'     => 'queued',
				'evt_1Known'   => 'skipped',
				'evt_1Foreign' => 'ignored',
			],
			array_column( $summary['events'], 'result', 'id' )
		);
		$this->assertSame( 'evt_1New', get_option( self::CURSOR_OPTION ) );
		$this->assertSame( WC_Stripe_Event_Store_Interface::STATUS_PENDING, $this->store->get( 'evt_1New' )->status );
		$this->assertSame( WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $this->store->get( 'evt_1Known' )->status );
		$this->assertNull( $this->store->get( 'evt_1Foreign' ) );
		$this->assertTrue( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1New' ], self::GROUP ) );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1Known' ], self::GROUP ) );

		$query = $this->query_of( $this->requests[0] );
		$this->assertSame( 'false', $query['delivery_success'] );
		$this->assertSame( 'evt_1Cursor', $query['ending_before'] );
		$this->assertEqualsWithDelta( time() - HOUR_IN_SECONDS, (int) $query['created']['lte'], 5 );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::RECONCILE_ACTION, [], self::GROUP ) );
	}

	/**
	 * A full page lists the next one right away.
	 */
	public function test_queue_undelivered_events_with_more_pages_enqueues_the_next_run(): void {
		update_option( self::CURSOR_OPTION, 'evt_1Cursor' );
		$this->queue_response( 'events', $this->make_list( [ $this->make_event( 'evt_1New' ) ], true ) );

		$this->reconciler->queue_undelivered_events();

		$this->assertTrue( as_has_scheduled_action( WC_Stripe_Event_Reconciler::RECONCILE_ACTION, [], self::GROUP ) );
	}

	/**
	 * When the page is empty or the cursor is gone, the cursor moves to the newest event, or stays when that listing fails.
	 *
	 * @param array       $page_response   Response to the undelivered events listing.
	 * @param array       $anchor_response Response to the newest event listing.
	 * @param string      $expected_cursor Expected cursor afterwards.
	 * @param int         $request_count   Expected number of requests.
	 * @dataProvider provide_cursor_recovery_cases
	 */
	public function test_queue_undelivered_events_moves_the_cursor( array $page_response, array $anchor_response, string $expected_cursor, int $request_count ): void {
		update_option( self::CURSOR_OPTION, 'evt_1Cursor' );
		$this->queue_response( 'events', $page_response );
		$this->queue_response( 'events', $anchor_response );

		$summary = $this->reconciler->queue_undelivered_events();

		$this->assertSame( $expected_cursor, $summary['cursor_after'] );
		$this->assertSame( $expected_cursor, get_option( self::CURSOR_OPTION ) );
		$this->assertCount( $request_count, $this->requests );
		$this->assertSame( [], $this->store->records );
	}

	/**
	 * @return array
	 */
	public function provide_cursor_recovery_cases(): array {
		$newest  = $this->make_list( [ $this->make_event( 'evt_1Newest' ) ] );
		$empty   = $this->make_list( [] );
		$missing = $this->make_error( 404, 'resource_missing' );
		$outage  = $this->make_error( 500, 'api_error' );

		return [
			'empty page'                        => [ $empty, $newest, 'evt_1Newest', 2 ],
			'empty page, anchor listing fails'  => [ $empty, $outage, 'evt_1Cursor', 2 ],
			'cursor no longer exists'           => [ $missing, $newest, 'evt_1Newest', 2 ],
			'cursor gone, anchor listing fails' => [ $missing, $outage, 'evt_1Cursor', 2 ],
			'other error keeps the cursor'      => [ $this->make_error( 400, 'parameter_invalid' ), $newest, 'evt_1Cursor', 1 ],
			'outage keeps the cursor'           => [ $outage, $newest, 'evt_1Cursor', 1 ],
		];
	}

	/**
	 * A pending event is fetched and processed through the webhook handler, without a retry.
	 */
	public function test_process_event_processes_a_fetched_event(): void {
		$this->store->seed( $this->make_record( 'evt_1Pending', WC_Stripe_Event_Store_Interface::STATUS_PENDING ) );
		// process_webhook() has no case for invoice.paid, so it completes without an order or further API calls.
		$this->queue_response( 'events/evt_1Pending', [ 200, (array) $this->make_event( 'evt_1Pending', 'invoice.paid' ) ] );

		$this->reconciler->process_event( 'evt_1Pending' );

		$this->assertSame( WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $this->store->get( 'evt_1Pending' )->status );
		$this->assertFalse( $this->read_lock( 'evt_1Pending' ) );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1Pending' ], self::GROUP ) );
	}

	/**
	 * A failed fetch counts an attempt and schedules a retry, and the event is abandoned when it no longer exists or after too many attempts.
	 *
	 * @param array  $response         Response to the event fetch.
	 * @param int    $attempts_before  Attempts before this run.
	 * @param string $expected_status  Expected status afterwards.
	 * @dataProvider provide_failed_fetch_cases
	 */
	public function test_process_event_failed_fetch( array $response, int $attempts_before, string $expected_status ): void {
		$record           = $this->make_record( 'evt_1Fails', WC_Stripe_Event_Store_Interface::STATUS_PENDING );
		$record->attempts = $attempts_before;
		$this->store->seed( $record );
		$this->queue_response( 'events/evt_1Fails', $response );

		$this->reconciler->process_event( 'evt_1Fails' );

		$after = $this->store->get( 'evt_1Fails' );
		$this->assertSame( $expected_status, $after->status );
		$this->assertSame( $attempts_before + 1, $after->attempts );
		$this->assertSame(
			WC_Stripe_Event_Store_Interface::STATUS_PENDING === $expected_status,
			(bool) as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1Fails' ], self::GROUP ),
			'Only an event that is still pending gets a retry.'
		);
	}

	/**
	 * @return array
	 */
	public function provide_failed_fetch_cases(): array {
		$pending   = WC_Stripe_Event_Store_Interface::STATUS_PENDING;
		$abandoned = WC_Stripe_Event_Store_Interface::STATUS_ABANDONED;

		return [
			'outage counts an attempt'        => [ $this->make_error( 500, 'api_error' ), 0, $pending ],
			'rate limit counts an attempt'    => [ $this->make_error( 429, 'rate_limit' ), 3, $pending ],
			'event deleted by Stripe'         => [ $this->make_error( 404, 'resource_missing' ), 0, $abandoned ],
			'last attempt abandons the event' => [ $this->make_error( 500, 'api_error' ), 47, $abandoned ],
		];
	}

	/**
	 * Events left processing for longer than an hour are queued again with an attempt counted, and events left pending
	 * for longer than an hour get a new action.
	 */
	public function test_requeue_stuck_events(): void {
		$processing = WC_Stripe_Event_Store_Interface::STATUS_PROCESSING;
		$pending    = WC_Stripe_Event_Store_Interface::STATUS_PENDING;
		$this->store->seed( $this->make_record( 'evt_1Stuck', $processing ), time() - 2 * HOUR_IN_SECONDS );
		$this->store->seed( $this->make_record( 'evt_1Deferred', $processing ), time() - 5 * MINUTE_IN_SECONDS );
		$this->store->seed( $this->make_record( 'evt_1Lost', $pending ), time() - 2 * HOUR_IN_SECONDS );
		$this->store->seed( $this->make_record( 'evt_1Waiting', $pending ), time() - 5 * MINUTE_IN_SECONDS );

		$this->reconciler->requeue_stuck_events();

		$this->assertSame( $pending, $this->store->get( 'evt_1Stuck' )->status );
		$this->assertSame( 1, $this->store->get( 'evt_1Stuck' )->attempts );
		$this->assertNotFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1Stuck' ], self::GROUP ) );
		$this->assertSame( $processing, $this->store->get( 'evt_1Deferred' )->status );
		$this->assertTrue( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1Lost' ], self::GROUP ) );
		$this->assertSame( 0, $this->store->get( 'evt_1Lost' )->attempts );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::PROCESS_ACTION, [ 'evt_1Waiting' ], self::GROUP ) );
	}

	/**
	 * Cleanup deletes records older than 30 days, and keeps the rest.
	 */
	public function test_delete_expired_events(): void {
		$this->store->seed( $this->make_record( 'evt_1Expired', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, time() - 31 * DAY_IN_SECONDS ) );
		$this->store->seed( $this->make_record( 'evt_1Recent', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, time() - DAY_IN_SECONDS ) );

		$this->reconciler->delete_expired_events();

		$this->assertSame( [ 'evt_1Recent' ], array_keys( $this->store->records ) );
		$this->assertEqualsWithDelta( time() - 30 * DAY_IN_SECONDS, $this->store->delete_older_than_calls[0][0], 5 );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Event_Reconciler::CLEANUP_ACTION, [], self::GROUP ), 'A partial batch schedules no follow-up.' );
	}

	/**
	 * Serves the queued response for a Stripe API request, and blocks every other outbound request.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 * @return array|WP_Error
	 */
	public function serve_stripe_response( $preempt, $args, $url ) {
		$this->requests[] = $url;

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = preg_replace( '#^/v1/#', '', $path );

		if ( empty( $this->responses[ $path ] ) ) {
			return new WP_Error( 'unexpected_request', 'Unexpected request in test: ' . $url );
		}

		[ $code, $body ] = array_shift( $this->responses[ $path ] );

		return [
			'headers'  => [],
			'cookies'  => [],
			'response' => [
				'code'    => $code,
				'message' => '',
			],
			'body'     => wp_json_encode( $body ),
		];
	}

	private function queue_response( string $path, array $response ): void {
		$this->responses[ $path ][] = $response;
	}

	private function make_list( array $events, bool $has_more = false ): array {
		return [
			200,
			[
				'object'   => 'list',
				'data'     => $events,
				'has_more' => $has_more,
			],
		];
	}

	private function make_error( int $code, string $error_code ): array {
		return [ $code, [ 'error' => [ 'code' => $error_code ] ] ];
	}

	private function make_event( string $event_id, string $type = 'charge.succeeded' ): object {
		return (object) [
			'id'       => $event_id,
			'object'   => 'event',
			'type'     => $type,
			'created'  => time() - 2 * HOUR_IN_SECONDS,
			'livemode' => false,
			'data'     => (object) [ 'object' => (object) [] ],
		];
	}

	private function make_record( string $event_id, string $status, ?int $created = null ): WC_Stripe_Event_Record {
		return new WC_Stripe_Event_Record( $event_id, $status, 'charge.succeeded', $created ?? time() - 2 * HOUR_IN_SECONDS, false );
	}

	/**
	 * @param string $url URL.
	 * @return array
	 */
	private function query_of( string $url ): array {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		return $query;
	}

	/**
	 * Reads a lock row directly: locks are written with $wpdb, bypassing the options cache.
	 *
	 * @param string $event_id Event ID.
	 * @return string|false
	 */
	private function read_lock( string $event_id ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_PREFIX . $event_id ) );
		return null === $value ? false : $value;
	}

	private function unschedule_actions(): void {
		foreach ( [ WC_Stripe_Event_Reconciler::RECONCILE_ACTION, WC_Stripe_Event_Reconciler::PROCESS_ACTION, WC_Stripe_Event_Reconciler::CLEANUP_ACTION ] as $hook ) {
			// Without a group, so actions with any arguments match.
			as_unschedule_all_actions( $hook );
		}
	}
}
