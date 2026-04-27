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
		$this->controller = new WC_REST_Stripe_Diagnostics_Controller(
			$this->store,
			new WC_Stripe_Diagnostics_Redactor()
		);
		update_option( WC_REST_Stripe_Diagnostics_Controller::ENABLED_OPTION, 'yes' );
		$this->clear_state();
	}

	public function tear_down() {
		$this->clear_state();
		delete_option( WC_REST_Stripe_Diagnostics_Controller::ENABLED_OPTION );
		parent::tear_down();
	}

	private function clear_state() {
		$this->store->delete_all();
	}

	private function make_request( array $body, ?string $nonce = null ): WP_REST_Request {
		$nonce   = $nonce ?? wp_create_nonce( WC_REST_Stripe_Diagnostics_Controller::NONCE_ACTION );
		$request = new WP_REST_Request( 'POST', '/wc/v3/wc_stripe/diagnostics/events' );
		$request->set_header( 'X-WP-Nonce', $nonce );
		foreach ( $body as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return $request;
	}

	public function test_permission_denied_when_toggle_off() {
		update_option( WC_REST_Stripe_Diagnostics_Controller::ENABLED_OPTION, 'no' );
		$request = $this->make_request(
			[
				'sessionId' => 'abc',
				'events'    => [],
			]
		);
		$result  = $this->controller->permissions_check( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_disabled', $result->get_error_code() );
	}

	public function test_permission_denied_when_nonce_invalid() {
		$request = $this->make_request(
			[
				'sessionId' => 'abc',
				'events'    => [],
			],
			'not-a-nonce'
		);
		$result  = $this->controller->permissions_check( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_bad_nonce', $result->get_error_code() );
	}

	public function test_permission_granted_with_valid_nonce_and_toggle_on() {
		$request = $this->make_request(
			[
				'sessionId' => 'abc',
				'events'    => [],
			]
		);
		$this->assertTrue( $this->controller->permissions_check( $request ) );
	}

	public function test_ingest_rejects_invalid_session_id() {
		$request = $this->make_request(
			[
				'sessionId' => '@@@',
				'events'    => [],
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_bad_session', $result->get_error_code() );
	}

	public function test_ingest_rejects_non_array_events() {
		$request = $this->make_request(
			[
				'sessionId' => 'abc',
				'events'    => 'nope',
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_diagnostics_bad_events', $result->get_error_code() );
	}

	public function test_ingest_creates_trace_on_first_event() {
		$request = $this->make_request(
			[
				'sessionId' => 'first-event',
				'events'    => [
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

	public function test_ingest_redacts_events_before_writing() {
		$request = $this->make_request(
			[
				'sessionId' => 'redact',
				'events'    => [
					[
						'kind'              => 'consoleMessage',
						'level'             => 'warn',
						'message_truncated' => 'Error from shopper@example.com',
						'secret_header'     => 'sk_live_abcdef1234567890',
					],
				],
			]
		);
		$this->controller->ingest_events( $request );
		$events = $this->store->get( 'redact' )['events'];
		$this->assertCount( 1, $events );
		$this->assertArrayNotHasKey( 'secret_header', $events[0] );
		$this->assertStringNotContainsString( 'shopper@example.com', wp_json_encode( $events[0] ) );
	}

	public function test_ingest_drops_unknown_event_kinds() {
		$request = $this->make_request(
			[
				'sessionId' => 'mixed',
				'events'    => [
					[
						'kind'              => 'consoleMessage',
						'level'             => 'warn',
						'message_truncated' => 'ok',
					],
					[ 'kind' => 'mystery' ],
				],
			]
		);
		$result  = $this->controller->ingest_events( $request );
		$this->assertSame( 1, $result->get_data()['written'] );
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
				'sessionId' => 'big',
				'events'    => $events,
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
				'sessionId' => 'overflow',
				'events'    => [
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
