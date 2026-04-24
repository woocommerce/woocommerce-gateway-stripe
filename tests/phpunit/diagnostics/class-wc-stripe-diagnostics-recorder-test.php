<?php

/**
 * Tests for WC_Stripe_Diagnostics_Recorder.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Recorder_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * @var WC_Stripe_Diagnostics_Recorder
	 */
	private $recorder;

	public function set_up() {
		parent::set_up();
		$this->store    = new WC_Stripe_Diagnostics_Trace_Store();
		$this->recorder = new WC_Stripe_Diagnostics_Recorder(
			$this->store,
			new WC_Stripe_Diagnostics_Redactor()
		);
		$this->clear_state();
	}

	public function tear_down() {
		$this->clear_state();
		parent::tear_down();
	}

	private function clear_state() {
		foreach ( $this->store->get_all_ids() as $id ) {
			$this->store->delete( $id );
		}
		delete_option( WC_Stripe_Diagnostics_Trace_Store::INDEX_OPTION );
		delete_transient( WC_Stripe_Diagnostics_Recorder::SESSION_OPTION );
	}

	public function test_request_body_is_untouched_when_no_session_is_active() {
		$request = [ 'amount' => 1000 ];
		$this->assertSame( $request, $this->recorder->on_request_body( $request, 'payment_intents' ) );
	}

	public function test_request_body_injects_session_id_metadata_for_pi() {
		$this->recorder->start_session( 'smoke-xyz' );
		$out = $this->recorder->on_request_body( [ 'amount' => 1000 ], 'payment_intents' );
		$this->assertSame( 'smoke-xyz', $out['metadata']['wc_diag_session_id'] );
	}

	public function test_request_body_preserves_existing_metadata() {
		$this->recorder->start_session( 'abc' );
		$out = $this->recorder->on_request_body(
			[ 'metadata' => [ 'order_id' => '42' ] ],
			'setup_intents'
		);
		$this->assertSame( '42', $out['metadata']['order_id'] );
		$this->assertSame( 'abc', $out['metadata']['wc_diag_session_id'] );
	}

	public function test_request_body_does_not_inject_for_unrelated_apis() {
		$this->recorder->start_session( 'abc' );
		$out = $this->recorder->on_request_body( [ 'foo' => 'bar' ], 'balance' );
		$this->assertArrayNotHasKey( 'metadata', $out );
	}

	public function test_request_body_records_apiRequest_event() {
		$this->recorder->start_session( 'rec1' );
		$this->recorder->on_request_body( [ 'amount' => 100 ], 'payment_intents' );
		$events = $this->store->get( 'rec1' )['events'];
		$this->assertCount( 1, $events );
		$this->assertSame( 'apiRequest', $events[0]['kind'] );
		$this->assertSame( 'payment_intents', $events[0]['api'] );
	}

	public function test_request_response_records_apiResponse_event_with_latency() {
		$this->recorder->start_session( 'rec2' );
		$this->recorder->on_request_body( [ 'amount' => 100 ], 'payment_intents' );

		$response         = new stdClass();
		$response->id     = 'pi_123';
		$response->status = 'succeeded';
		$this->recorder->on_request_response( $response, 'payment_intents', 'POST', [ 'amount' => 100 ] );

		$events = $this->store->get( 'rec2' )['events'];
		$this->assertCount( 2, $events );
		$this->assertSame( 'apiResponse', $events[1]['kind'] );
		$this->assertSame( 'payment_intents', $events[1]['api'] );
		$this->assertArrayHasKey( 'latency_ms', $events[1] );
	}

	public function test_response_with_error_captures_error_shape() {
		$this->recorder->start_session( 'err1' );
		$this->recorder->on_request_body( [ 'amount' => 1 ], 'payment_intents' );

		$response                      = new stdClass();
		$response->error               = new stdClass();
		$response->error->code         = 'card_declined';
		$response->error->type         = 'card_error';
		$response->error->decline_code = 'insufficient_funds';
		$this->recorder->on_request_response( $response, 'payment_intents', 'POST', [ 'amount' => 1 ] );

		$events = $this->store->get( 'err1' )['events'];
		$this->assertSame( 'card_declined', $events[1]['error']['code'] );
		$this->assertSame( 'insufficient_funds', $events[1]['error']['decline_code'] );
	}

	public function test_webhook_received_correlates_by_metadata_session_id() {
		$this->store->create( 'webhook-session' );

		$notification               = new stdClass();
		$notification->data         = new stdClass();
		$notification->data->object = (object) [
			'object'   => 'payment_intent',
			'id'       => 'pi_xyz',
			'status'   => 'succeeded',
			'metadata' => (object) [ 'wc_diag_session_id' => 'webhook-session' ],
		];

		$this->recorder->on_webhook_received( 'payment_intent.succeeded', $notification, null );

		$events = $this->store->get( 'webhook-session' )['events'];
		$this->assertCount( 1, $events );
		$this->assertSame( 'webhookReceived', $events[0]['kind'] );
		$this->assertSame( 'payment_intent.succeeded', $events[0]['type'] );
		$this->assertSame( 'pi_xyz', $events[0]['intent_id'] );
	}

	public function test_webhook_received_creates_trace_when_missing() {
		// Simulate a webhook that arrives before any other activity on this session.
		$notification               = new stdClass();
		$notification->data         = new stdClass();
		$notification->data->object = (object) [
			'metadata' => (object) [ 'wc_diag_session_id' => 'fresh-from-webhook' ],
			'status'   => 'processing',
		];
		$this->recorder->on_webhook_received( 'payment_intent.processing', $notification, null );

		$this->assertNotNull( $this->store->get( 'fresh-from-webhook' ) );
	}

	public function test_webhook_without_session_metadata_is_ignored() {
		$notification               = new stdClass();
		$notification->data         = new stdClass();
		$notification->data->object = (object) [ 'metadata' => new stdClass() ];
		$this->recorder->on_webhook_received( 'payment_intent.succeeded', $notification, null );
		$this->assertSame( [], $this->store->get_all_ids() );
	}

	public function test_start_session_fails_when_store_is_full() {
		// Force the store to saturation using the real store instance.
		for ( $i = 0; $i < WC_Stripe_Diagnostics_Trace_Store::MAX_TRACES; $i++ ) {
			$this->store->create( 'f' . $i );
		}
		$this->assertFalse( $this->recorder->start_session( 'too-late' ) );
	}

	public function test_current_session_id_reads_from_transient_between_requests() {
		set_transient( WC_Stripe_Diagnostics_Recorder::SESSION_OPTION, 'resumed', 60 );
		// Fresh recorder: no in-memory session.
		$recorder = new WC_Stripe_Diagnostics_Recorder( $this->store, new WC_Stripe_Diagnostics_Redactor() );
		$this->assertSame( 'resumed', $recorder->current_session_id() );
	}
}
