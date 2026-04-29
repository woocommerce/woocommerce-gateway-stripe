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
		parent::tear_down();
	}

	private function clear_state() {
		$this->store->delete_all();
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

	public function test_ingest_evicts_oldest_trace_when_store_is_full() {
		for ( $i = 0; $i < WC_Stripe_Diagnostics_Trace_Store::MAX_TRACES; $i++ ) {
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
