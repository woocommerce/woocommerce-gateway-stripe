<?php

require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-diagnostics-controller.php';

/**
 * Tests for WC_REST_Stripe_Diagnostics_Controller.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_REST_Stripe_Diagnostics_Controller_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * @var WC_REST_Stripe_Diagnostics_Controller
	 */
	private $controller;

	public function set_up() {
		parent::set_up();
		$this->store      = new WC_Stripe_Diagnostics_Trace_Store();
		$this->controller = new WC_REST_Stripe_Diagnostics_Controller( $this->store );
		$this->set_diagnostics_enabled( true );
		$this->clear_state();
	}

	public function tear_down() {
		$this->clear_state();
		$this->set_diagnostics_enabled( false );
		$this->set_capture_limit( null );
		parent::tear_down();
	}

	private function clear_state() {
		$this->store->delete_all();
	}

	/**
	 * Writes the capture limit into the shared settings option; null removes it.
	 *
	 * @param int|null $limit Preset value, any int for negative-path tests, or null to unset.
	 */
	private function set_capture_limit( ?int $limit ): void {
		$settings = get_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		if ( null === $limit ) {
			unset( $settings[ WC_REST_Stripe_Diagnostics_Controller::CAPTURE_LIMIT_KEY ] );
		} else {
			$settings[ WC_REST_Stripe_Diagnostics_Controller::CAPTURE_LIMIT_KEY ] = $limit;
		}
		update_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, $settings );
	}

	/**
	 * Toggle the merchant-facing diagnostics setting (lives inside the
	 * shared `woocommerce_stripe_settings` option group).
	 */
	private function set_diagnostics_enabled( bool $enabled ): void {
		$settings = get_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$settings[ WC_REST_Stripe_Diagnostics_Controller::SETTINGS_KEY ] = $enabled ? 'yes' : 'no';
		update_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, $settings );
	}

	private function make_request( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wc/v3/wc_stripe/diagnostics/events' );
		// The recorder posts via sendBeacon with a Blob typed as
		// `application/json`, so WP auto-decodes the body. Mirror that here
		// to exercise the same parameter-parsing path in tests.
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return $request;
	}

	public function test_permission_denied_when_toggle_off() {
		$this->set_diagnostics_enabled( false );
		$request = $this->make_request(
			[
				'diag_session_id' => 'abc',
				'events'          => [],
			]
		);
		$result  = $this->controller->permissions_check( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_disabled', $result->get_error_code() );
	}

	public function test_permission_granted_when_toggle_on() {
		$request = $this->make_request(
			[
				'diag_session_id' => 'abc',
				'events'          => [],
			]
		);
		$this->assertTrue( $this->controller->permissions_check( $request ) );
	}

	public function test_ingest_rejects_invalid_session_id() {
		$request = $this->make_request(
			[
				'diag_session_id' => '@@@',
				'events'          => [],
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_bad_session', $result->get_error_code() );
	}

	public function test_ingest_rejects_non_array_events() {
		$request = $this->make_request(
			[
				'diag_session_id' => 'abc',
				'events'          => 'nope',
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_bad_events', $result->get_error_code() );
	}

	public function test_ingest_creates_trace_on_first_event() {
		$request = $this->make_request(
			[
				'diag_session_id' => 'first-event',
				'events'          => [
					[
						'kind'              => 'consoleMessage',
						'level'             => 'warn',
						'message_truncated' => 'hello',
					],
				],
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertSame( 1, $result->get_data()['written'] );
		$this->assertNotNull( $this->store->get( 'first-event' ) );
	}

	public function test_ingest_trims_batch_at_200_events() {
		$events = [];
		for ( $i = 0; $i < 300; $i++ ) {
			$events[] = [
				'kind'   => 'sdkCall',
				'method' => 'retrievePaymentIntent',
				'ok'     => true,
			];
		}
		$request = $this->make_request(
			[
				'diag_session_id' => 'big',
				'events'          => $events,
			]
		);
		$result  = $this->controller->ingest_events( $request );
		// The store caps at 200 events/trace too; both caps enforce the ceiling.
		$this->assertLessThanOrEqual( 200, $result->get_data()['written'] );
		$this->assertSame( 200, count( $this->store->get( 'big' )['events'] ) );
	}

	/**
	 * Admin endpoints require `manage_woocommerce` regardless of whether
	 * the diagnostics toggle is on or off (a merchant who disabled capture
	 * must still be able to copy traces from when it was on).
	 *
	 * @dataProvider admin_permission_matrix
	 */
	public function test_admin_permissions_check( bool $is_admin, bool $toggle_on, bool $expect_allowed ) {
		$this->set_diagnostics_enabled( $toggle_on );
		if ( $is_admin ) {
			wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		} else {
			wp_set_current_user( 0 );
		}

		$result = $this->controller->admin_permissions_check();

		if ( $expect_allowed ) {
			$this->assertTrue( $result );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'wc_stripe_diagnostics_forbidden', $result->get_error_code() );
		}
	}

	public function admin_permission_matrix(): array {
		return [
			'anon user, toggle on  → denied' => [ false, true, false ],
			'anon user, toggle off → denied' => [ false, false, false ],
			'admin user, toggle on  → allow' => [ true, true, true ],
			'admin user, toggle off → allow' => [ true, false, true ],
		];
	}

	/**
	 * Summary always returns every status bucket as a key (zero-counted
	 * included) plus a total — the frontend renders the breakdown row from
	 * this shape directly.
	 */
	public function test_get_summary_returns_per_status_counts_and_total() {
		$this->store->create( 'p1' );
		$this->store->create( 'f1' );
		$this->store->set_status( 'f1', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED );
		$this->store->create( 'c1' );
		$this->store->set_status( 'c1', WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED );

		$data = $this->controller->get_summary()->get_data();

		$this->assertSame( 1, $data['counts'][ WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING ] );
		$this->assertSame( 1, $data['counts'][ WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED ] );
		$this->assertSame( 1, $data['counts'][ WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED ] );
		$this->assertSame( 0, $data['counts'][ WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED ] );
		$this->assertSame( 3, $data['total'] );
	}

	/**
	 * Filter behavior of GET /traces. Single fixture (one trace per
	 * terminal status) exercised from each call site:
	 *   - "Copy failed traces" (status=failed,abandoned)
	 *   - "Copy all instead"   (no status param)
	 *   - empty filter result  (status=completed when no completed exists)
	 *
	 * @dataProvider get_traces_filter_matrix
	 */
	public function test_get_traces_filter_behavior( ?array $statuses, array $expected_ids, int $expected_count ) {
		$this->store->create( 'p' );
		$this->store->create( 'f' );
		$this->store->set_status( 'f', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED );
		$this->store->create( 'a' );
		$this->store->set_status( 'a', WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED );

		$request = new WP_REST_Request( 'GET', '/wc/v3/wc_stripe/diagnostics/traces' );
		if ( null !== $statuses ) {
			$request->set_query_params( [ 'status' => $statuses ] );
		}
		$data = $this->controller->get_traces( $request )->get_data();
		$ids  = array_column( $data['traces'], 'id' );
		sort( $ids );

		$this->assertSame( $expected_ids, $ids );
		$this->assertSame( $expected_count, $data['count'] );
	}

	public function get_traces_filter_matrix(): array {
		$failed    = WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
		$abandoned = WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED;
		$completed = WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED;

		return [
			'Copy failed (failed+abandoned filter)' => [ [ $failed, $abandoned ], [ 'a', 'f' ], 2 ],
			'Copy all (no filter → all 3 traces)'   => [ null, [ 'a', 'f', 'p' ], 3 ],
			'no matches → empty list'               => [ [ $completed ], [], 0 ],
		];
	}

	public function test_delete_traces_clears_store() {
		$this->store->create( 'a' );
		$this->store->create( 'b' );
		$this->store->create( 'c' );

		$response = $this->controller->delete_traces();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 3, $data['deleted'] );
		$this->assertSame( 0, $data['total'] );
		$this->assertSame( 0, $this->store->count() );
	}

	/**
	 * Mirrors {@see test_admin_permissions_check}: clearing a captured bundle
	 * must work after the merchant has switched recording off.
	 */
	public function test_delete_traces_works_when_toggle_off() {
		$this->set_diagnostics_enabled( false );
		$this->store->create( 'kept-from-prior-session' );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( $this->controller->admin_permissions_check() );

		$response = $this->controller->delete_traces();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 0, $this->store->count() );
	}

	/**
	 * @dataProvider capture_limit_value_matrix
	 */
	public function test_capture_limit_falls_back_to_default_when_value_is_invalid( $stored, int $expected ) {
		$this->set_capture_limit( $stored );
		$this->assertSame( $expected, WC_REST_Stripe_Diagnostics_Controller::capture_limit() );
	}

	public function capture_limit_value_matrix(): array {
		return [
			'unset → DEFAULT'       => [ null, WC_REST_Stripe_Diagnostics_Controller::DEFAULT_CAPTURE_LIMIT ],
			'preset 5 → 5'          => [ 5, 5 ],
			'preset 25 → 25'        => [ 25, 25 ],
			'legacy 0 → DEFAULT'    => [ 0, WC_REST_Stripe_Diagnostics_Controller::DEFAULT_CAPTURE_LIMIT ],
			'off-menu 7 → DEFAULT'  => [ 7, WC_REST_Stripe_Diagnostics_Controller::DEFAULT_CAPTURE_LIMIT ],
			'negative -1 → DEFAULT' => [ -1, WC_REST_Stripe_Diagnostics_Controller::DEFAULT_CAPTURE_LIMIT ],
		];
	}

	public function test_enforce_capture_limit_flips_toggle_when_store_reaches_limit() {
		$this->set_capture_limit( 5 );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->store->create( 'trace_' . $i );
		}

		$this->assertTrue( WC_REST_Stripe_Diagnostics_Controller::is_enabled() );
		$flipped = WC_REST_Stripe_Diagnostics_Controller::enforce_capture_limit( $this->store );
		$this->assertTrue( $flipped );
		$this->assertFalse( WC_REST_Stripe_Diagnostics_Controller::is_enabled() );
	}

	public function test_enforce_capture_limit_is_a_noop_below_the_limit() {
		$this->set_capture_limit( 5 );
		$this->store->create( 'trace_0' );

		$flipped = WC_REST_Stripe_Diagnostics_Controller::enforce_capture_limit( $this->store );
		$this->assertFalse( $flipped );
		$this->assertTrue( WC_REST_Stripe_Diagnostics_Controller::is_enabled() );
	}

	public function test_ingest_events_flips_toggle_when_new_trace_hits_limit() {
		$this->set_capture_limit( 5 );
		for ( $i = 0; $i < 4; $i++ ) {
			$this->store->create( 'seed_' . $i );
		}

		$request  = $this->make_request(
			[
				'diag_session_id' => 'fifth',
				'events'          => [
					[
						'kind'              => 'consoleMessage',
						'level'             => 'warn',
						'message_truncated' => 'hello',
					],
				],
			]
		);
		$response = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 5, $this->store->count() );
		$this->assertFalse( WC_REST_Stripe_Diagnostics_Controller::is_enabled() );
	}

	public function test_ingest_evicts_oldest_trace_when_store_is_full() {
		for ( $i = 0; $i < WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_TRACES', 'int' ); $i++ ) {
			$this->store->create( 'full' . $i );
		}
		$request = $this->make_request(
			[
				'diag_session_id' => 'overflow',
				'events'          => [
					[
						'kind'   => 'sdkCall',
						'method' => 'x',
						'ok'     => true,
					],
				],
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		// Oldest entry evicted, new session accepted.
		$this->assertNull( $this->store->get( 'full0' ) );
		$this->assertNotNull( $this->store->get( 'overflow' ) );
	}
}
