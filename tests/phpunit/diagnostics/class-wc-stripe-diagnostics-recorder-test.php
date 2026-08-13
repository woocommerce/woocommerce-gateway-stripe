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

	/**
	 * Boot WC sessions before instantiating the recorder. Without
	 * `initialize_session()`, `WC()->session` is null and the recorder's
	 * WC-session-backed session id storage silently no-ops.
	 */
	public function set_up() {
		parent::set_up();
		WC()->initialize_session();
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

	/**
	 * Reset the trace store and clear the recorder's WC-session entry so
	 * tests don't observe leakage from each other.
	 */
	private function clear_state() {
		$this->store->delete_all();
		if ( WC()->session instanceof WC_Session ) {
			WC()->session->set( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Recorder::class, 'SESSION_KEY', 'string' ), null );
		}
	}

	/**
	 * When no session is active, the request body must pass through
	 * untouched — no metadata injection and no recorded event.
	 */
	public function test_request_body_is_untouched_when_no_session_is_active() {
		$request = [ 'amount' => 1000 ];
		$this->assertSame( $request, $this->recorder->on_request_body( $request, 'payment_intents' ) );
	}

	/**
	 * With an active session, payment_intents requests carry the session id
	 * as `metadata[wc_diag_session_id]` so the matching webhook can be
	 * correlated back to the same trace.
	 */
	public function test_request_body_injects_session_id_metadata_for_pi() {
		$this->recorder->start_session( 'smoke-xyz' );
		$out = $this->recorder->on_request_body( [ 'amount' => 1000 ], 'payment_intents' );
		$this->assertSame( 'smoke-xyz', $out['metadata']['wc_diag_session_id'] );
	}

	/**
	 * Injection must merge with — not replace — existing caller metadata
	 * (e.g. the order_id the gateway already attaches).
	 */
	public function test_request_body_preserves_existing_metadata() {
		$this->recorder->start_session( 'abc' );
		$out = $this->recorder->on_request_body(
			[ 'metadata' => [ 'order_id' => '42' ] ],
			'setup_intents'
		);
		$this->assertSame( '42', $out['metadata']['order_id'] );
		$this->assertSame( 'abc', $out['metadata']['wc_diag_session_id'] );
	}

	/**
	 * Metadata injection is scoped to PI / SI / customers — the resources
	 * whose metadata survives in webhook payloads. Unrelated APIs (here,
	 * `balance`) must not have a metadata bag bolted on.
	 */
	public function test_request_body_does_not_inject_for_unrelated_apis() {
		$this->recorder->start_session( 'abc' );
		$out = $this->recorder->on_request_body( [ 'foo' => 'bar' ], 'balance' );
		$this->assertArrayNotHasKey( 'metadata', $out );
	}

	/**
	 * Each request emits one `stripe.api.request` event into the matching
	 * trace, tagged with the api the call hit.
	 */
	public function test_request_body_records_api_request_event() {
		$this->recorder->start_session( 'rec1' );
		$this->recorder->on_request_body( [ 'amount' => 100 ], 'payment_intents' );
		$events = $this->store->get( 'rec1' )['events'];
		$this->assertCount( 1, $events );
		$this->assertSame( 'stripe.api.request', $events[0]['kind'] );
		$this->assertSame( 'payment_intents', $events[0]['api'] );
	}

	/**
	 * Each round trip produces a request + response pair, with the response
	 * carrying a `latency_ms` computed from the matching request's start time.
	 */
	public function test_request_response_records_api_response_event_with_latency() {
		$this->recorder->start_session( 'rec2' );
		$this->recorder->on_request_body( [ 'amount' => 100 ], 'payment_intents' );

		$response         = new stdClass();
		$response->id     = 'pi_123';
		$response->status = 'succeeded';
		$this->recorder->on_request_response( $response, 'payment_intents', 'POST', [ 'amount' => 100 ], 'req_abc123' );

		$events = $this->store->get( 'rec2' )['events'];
		$this->assertCount( 2, $events );
		$this->assertSame( 'stripe.api.response', $events[1]['kind'] );
		$this->assertSame( 'payment_intents', $events[1]['api'] );
		$this->assertArrayHasKey( 'latency_ms', $events[1] );
		// request_id comes from the Stripe Request-Id response header,
		// not the body, so it must arrive via the 5th hook argument.
		$this->assertSame( 'req_abc123', $events[1]['request_id'] );
	}

	/**
	 * Stripe error responses are normalized into the `error.code/type/decline_code`
	 * shape on the response event so traces show declines without the raw payload.
	 */
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

	/**
	 * Webhooks are routed back to the originating trace by reading
	 * `metadata.wc_diag_session_id` off the webhook's data object.
	 */
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
		$this->assertSame( 'webhook.received', $events[0]['kind'] );
		$this->assertSame( 'payment_intent.succeeded', $events[0]['type'] );
		$this->assertSame( 'pi_xyz', $events[0]['intent_id'] );
	}

	/**
	 * If a webhook arrives before any other activity on its session, the
	 * recorder lazily creates the trace — matches the parent issue's
	 * "first-wins decrement" rule.
	 */
	public function test_webhook_received_creates_trace_when_missing() {
		$notification               = new stdClass();
		$notification->data         = new stdClass();
		$notification->data->object = (object) [
			'metadata' => (object) [ 'wc_diag_session_id' => 'fresh-from-webhook' ],
			'status'   => 'processing',
		];
		$this->recorder->on_webhook_received( 'payment_intent.processing', $notification, null );

		$this->assertNotNull( $this->store->get( 'fresh-from-webhook' ) );
	}

	/**
	 * Webhooks that don't carry our session metadata key are unrelated
	 * activity (e.g. PIs created outside diagnostics mode) and must not
	 * cause a stray trace to be created.
	 */
	public function test_webhook_without_session_metadata_is_ignored() {
		$notification               = new stdClass();
		$notification->data         = new stdClass();
		$notification->data->object = (object) [ 'metadata' => new stdClass() ];
		$this->recorder->on_webhook_received( 'payment_intent.succeeded', $notification, null );
		$this->assertSame( [], $this->store->get_all_ids() );
	}

	/**
	 * When the trace store is at its FIFO cap, `start_session()` returns
	 * false so the REST endpoint can react instead of letting an in-flight
	 * session silently fail later when the first event tries to land.
	 */
	public function test_start_session_fails_when_store_is_full() {
		for ( $i = 0; $i < WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_TRACES', 'int' ); $i++ ) {
			$this->store->create( 'f' . $i );
		}
		$this->assertFalse( $this->recorder->start_session( 'too-late' ) );
	}

	/**
	 * Reader side of the resume-across-requests contract: a fresh recorder
	 * (no in-memory session) reads the active id from the WC session that
	 * an earlier request persisted.
	 */
	public function test_current_session_id_reads_from_wc_session_between_requests() {
		WC()->session->set( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Recorder::class, 'SESSION_KEY', 'string' ), 'resumed' );
		$recorder = new WC_Stripe_Diagnostics_Recorder( $this->store, new WC_Stripe_Diagnostics_Redactor() );
		$this->assertSame( 'resumed', $recorder->current_session_id() );
	}

	/**
	 * Writer side of the resume contract: start_session() persists the id
	 * onto the WC session so the next request can resume it.
	 */
	public function test_start_session_persists_id_in_wc_session() {
		$this->recorder->start_session( 'persisted' );
		$this->assertSame( 'persisted', WC()->session->get( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Recorder::class, 'SESSION_KEY', 'string' ) ) );
	}

	/**
	 * Production wiring smoke test: registering the recorder via init() and
	 * then firing the same hook the API class fires must populate the trace.
	 * Catches drift between the recorder's hook subscriptions and the actual
	 * action names used in includes/class-wc-stripe-api.php.
	 */
	public function test_init_subscribes_to_real_api_hooks() {
		// init() is gated on the diagnostics toggle, so flip it on for this test.
		$settings = get_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, [] );
		$settings[ WC_REST_Stripe_Diagnostics_Controller::SETTINGS_KEY ] = 'yes';
		update_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, $settings );

		try {
			$this->recorder->init();
			$this->recorder->start_session( 'wired' );

			$response         = new stdClass();
			$response->id     = 'pi_wired';
			$response->status = 'succeeded';
			do_action( 'wc_stripe_api_response_received', $response, 'payment_intents', 'POST', [ 'amount' => 1 ] );

			$events = $this->store->get( 'wired' )['events'];
			$this->assertNotEmpty( $events );
			$this->assertSame( 'stripe.api.response', end( $events )['kind'] );
		} finally {
			remove_action( 'wc_stripe_api_response_received', [ $this->recorder, 'on_request_response' ], 10 );
			remove_filter( 'wc_stripe_request_body', [ $this->recorder, 'on_request_body' ], 10 );
			remove_action( 'wc_stripe_webhook_received', [ $this->recorder, 'on_webhook_received' ], 10 );
			// init() also registers the snapshotter on trace_finalized.
			// remove_all_actions clears it without needing a handle to the
			// snapshotter instance (which is private to the recorder).
			remove_all_actions( 'wc_stripe_diagnostics_trace_finalized' );
			delete_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION );
		}
	}

	/**
	 * The toggle gate: when the diagnostics feature is disabled (default),
	 * init() must not register any of the Stripe API hooks — otherwise we'd
	 * be paying the dispatch cost of the recorder for every Stripe request
	 * even though nothing is being recorded.
	 */
	public function test_init_skips_hook_registration_when_toggle_off() {
		delete_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION );

		$this->recorder->init();

		$this->assertFalse( has_filter( 'wc_stripe_request_body', [ $this->recorder, 'on_request_body' ] ) );
		$this->assertFalse( has_action( 'wc_stripe_api_response_received', [ $this->recorder, 'on_request_response' ] ) );
		$this->assertFalse( has_action( 'wc_stripe_webhook_received', [ $this->recorder, 'on_webhook_received' ] ) );
	}
}
