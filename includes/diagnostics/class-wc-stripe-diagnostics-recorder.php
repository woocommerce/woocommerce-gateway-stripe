<?php

defined( 'ABSPATH' ) || exit;

/**
 * Server-side diagnostics recorder.
 *
 * Subscribes to the plugin's existing request and webhook hooks. When a
 * diagnostics session is active it:
 *
 * - Injects `metadata[wc_diag_session_id]` into outgoing payment-intent,
 *   setup-intent, and customer create/update requests so webhooks can be
 *   correlated back to the originating trace.
 * - Records a redacted event for each Stripe API request/response round trip.
 * - Records a redacted event for each webhook received.
 *
 * Frontend `wcStripeDiag` localization is owned by
 * {@see WC_Stripe_Diagnostics_Frontend_Loader} (#5372).
 *
 * The recorder is a singleton so the running session id can be shared across
 * `wc_stripe_request_body` (request capture) and
 * `wc_stripe_api_response_received` (response capture) without threading state
 * through every call site.
 */
class WC_Stripe_Diagnostics_Recorder {

	protected const SESSION_ID_META_KEY   = 'wc_diag_session_id';
	protected const SESSION_KEY           = 'wc_stripe_diag_session_id';
	protected const METADATA_ENABLED_APIS = [ 'payment_intents', 'setup_intents', 'customers' ];

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Trace store dependency.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * Redactor dependency.
	 *
	 * @var WC_Stripe_Diagnostics_Redactor
	 */
	private $redactor;

	/**
	 * Outcome promoter — keeps the trace's stored status field in sync with
	 * the events as they're recorded so the merchant-facing "Copy failed
	 * traces" filter can read a single field instead of re-walking events.
	 *
	 * @var WC_Stripe_Diagnostics_Outcome_Promoter
	 */
	private $promoter;

	/**
	 * The current session id for this request, if any.
	 *
	 * @var string|null
	 */
	private $current_session_id;

	/**
	 * Track start times for in-flight requests keyed by api:method so the
	 * response hook can compute latency. The request filter fires before the
	 * HTTP call and the response filter fires after it, but the api name and
	 * method are stable identifiers across both.
	 *
	 * @var array<string, float>
	 */
	private $request_start_times = [];

	/**
	 * Listens for trace_finalized actions to append an order_snapshot event
	 * with the order's state at the moment the trace ended.
	 *
	 * @var WC_Stripe_Diagnostics_Order_Snapshotter
	 */
	private $snapshotter;

	/**
	 * Return the shared recorder instance, lazily constructing it (and the
	 * trace store + redactor it depends on) on first access.
	 *
	 * Production callers should use this; tests should use the constructor
	 * directly so they can inject fakes.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			$store          = new WC_Stripe_Diagnostics_Trace_Store();
			self::$instance = new self(
				$store,
				new WC_Stripe_Diagnostics_Redactor(),
				new WC_Stripe_Diagnostics_Outcome_Promoter( $store )
			);
		}
		return self::$instance;
	}

	/**
	 * Construct with explicit dependencies. Tests inject fakes via this ctor;
	 * production callers should use {@see self::get_instance()}.
	 *
	 * @param WC_Stripe_Diagnostics_Trace_Store           $store    Trace store.
	 * @param WC_Stripe_Diagnostics_Redactor              $redactor Redaction engine.
	 * @param WC_Stripe_Diagnostics_Outcome_Promoter|null $promoter Optional outcome promoter; defaults to one wrapping $store.
	 */
	public function __construct(
		WC_Stripe_Diagnostics_Trace_Store $store,
		WC_Stripe_Diagnostics_Redactor $redactor,
		?WC_Stripe_Diagnostics_Outcome_Promoter $promoter = null,
		?WC_Stripe_Diagnostics_Order_Snapshotter $snapshotter = null
	) {
		$this->store       = $store;
		$this->redactor    = $redactor;
		$this->promoter    = $promoter ?? new WC_Stripe_Diagnostics_Outcome_Promoter( $store );
		$this->snapshotter = $snapshotter ?? new WC_Stripe_Diagnostics_Order_Snapshotter( $store, $redactor );
	}

	/**
	 * Wire up the filter subscriptions. Called once during plugin init.
	 *
	 * Gated on the diagnostics toggle: when the merchant has the feature
	 * disabled, no hooks register and the recorder is effectively dormant.
	 */
	public function init(): void {
		if ( ! class_exists( 'WC_REST_Stripe_Diagnostics_Controller' ) || ! WC_REST_Stripe_Diagnostics_Controller::is_enabled() ) {
			return;
		}

		add_filter( 'wc_stripe_request_body', [ $this, 'on_request_body' ], 10, 2 );
		add_action( 'wc_stripe_api_response_received', [ $this, 'on_request_response' ], 10, 5 );
		add_action( 'wc_stripe_webhook_received', [ $this, 'on_webhook_received' ], 10, 3 );
		$this->snapshotter->register();
	}

	/**
	 * Start a new diagnostics session. Used by the REST endpoint that takes
	 * the handoff from the client recorder.
	 *
	 * @param string $session_id Client-generated session identifier.
	 * @return bool True when the session was accepted.
	 */
	public function start_session( string $session_id ): bool {
		$session_id = WC_Stripe_Diagnostics_Trace_Store::sanitize_id( $session_id );
		if ( '' === $session_id ) {
			return false;
		}
		if ( $this->store->is_full() ) {
			return false;
		}
		$this->current_session_id = $session_id;
		$this->store->create( $session_id, [ 'started_at' => time() ] );
		// Persist on the shopper's WC session so subsequent requests in the
		// same checkout (Stripe API hooks, redirects) resume the same id.
		// Per-shopper storage; concurrent shoppers don't share state.
		$session = WC()->session;
		if ( $session instanceof WC_Session_Handler ) {
			$session->set( self::SESSION_KEY, $session_id );
		}
		return true;
	}

	/**
	 * Return the currently active session id, reading from the WC session
	 * when this request didn't explicitly start one. WC Sessions scope state
	 * to the shopper, so concurrent shoppers don't read each other's ids.
	 */
	public function current_session_id(): ?string {
		if ( null !== $this->current_session_id ) {
			return $this->current_session_id;
		}
		$session = WC()->session;
		if ( ! $session instanceof WC_Session_Handler ) {
			return null;
		}
		$persisted = $session->get( self::SESSION_KEY );
		if ( is_string( $persisted ) && '' !== $persisted ) {
			$this->current_session_id = $persisted;
			return $persisted;
		}
		return null;
	}

	/**
	 * Inject session id into outgoing metadata and record the request event.
	 *
	 * @param array  $request The request body about to be sent to Stripe.
	 * @param string $api     The Stripe endpoint.
	 * @return array
	 */
	public function on_request_body( $request, $api ) {
		if ( ! is_array( $request ) ) {
			return $request;
		}
		$session_id = $this->current_session_id();
		if ( null === $session_id ) {
			return $request;
		}

		if ( in_array( $api, self::METADATA_ENABLED_APIS, true ) ) {
			if ( ! isset( $request['metadata'] ) || ! is_array( $request['metadata'] ) ) {
				$request['metadata'] = [];
			}
			$request['metadata'][ self::SESSION_ID_META_KEY ] = $session_id;
		}

		// Pin order id for the snapshotter. Skipped silently if the call
		// site doesn't set metadata.order_id
		if ( isset( $request['metadata']['order_id'] ) ) {
			$this->store->set_order_id( $session_id, (int) $request['metadata']['order_id'] );
		}

		$this->request_start_times[ $this->request_key( $api ) ] = microtime( true );

		$this->store->append_event(
			$session_id,
			$this->redactor->redact(
				[
					'kind'         => 'stripe.api.request',
					'ts'           => time(),
					'api'          => (string) $api,
					'method'       => 'POST',
					'has_metadata' => ! empty( $request['metadata'] ),
					'metadata'     => $request['metadata'] ?? [],
					'fields'       => array_keys( $request ),
				]
			)
		);

		return $request;
	}

	/**
	 * Record the response event and attach latency.
	 *
	 * @param mixed  $response_body The decoded response body.
	 * @param string $api           The Stripe endpoint.
	 * @param string $method        HTTP method.
	 * @param array  $request       The request body.
	 * @param string $request_id    Stripe Request-Id response header (empty when absent).
	 */
	public function on_request_response( $response_body, $api, $method, $request, $request_id = '' ): void {
		$session_id = $this->current_session_id();
		if ( null === $session_id ) {
			return;
		}

		$start_key = $this->request_key( $api );
		$latency   = null;
		if ( isset( $this->request_start_times[ $start_key ] ) ) {
			$latency = (int) ( ( microtime( true ) - $this->request_start_times[ $start_key ] ) * 1000 );
			unset( $this->request_start_times[ $start_key ] );
		}

		$event    = [
			'kind'       => 'stripe.api.response',
			'ts'         => time(),
			'api'        => (string) $api,
			'method'     => (string) $method,
			'latency_ms' => $latency,
			'request_id' => is_string( $request_id ) && '' !== $request_id ? $request_id : null,
			'error'      => self::extract_error( $response_body ),
		];
		$redacted = $this->redactor->redact( $event );
		$this->store->append_event( $session_id, $redacted );
		$this->promoter->maybe_promote( $session_id, $redacted );
	}

	/**
	 * Record a webhook event. Correlation happens via
	 * `metadata[wc_diag_session_id]` that on_request_body() injected into the
	 * original PI / SI / Customer request.
	 *
	 * @param string $webhook_type  e.g. payment_intent.succeeded.
	 * @param object $notification  The decoded Stripe webhook payload.
	 * @param mixed  $resolved_order The order that was resolved for this webhook, if any.
	 */
	public function on_webhook_received( $webhook_type, $notification, $resolved_order ): void {
		$session_id = self::extract_session_id_from_webhook( $notification );
		if ( null === $session_id ) {
			return;
		}
		$this->store->create( $session_id, [ 'source' => 'webhook' ] );

		$order_id = $resolved_order instanceof WC_Order ? (int) $resolved_order->get_id() : null;
		if ( null !== $order_id ) {
			$this->store->set_order_id( $session_id, $order_id );
		}

		$object = self::webhook_object( $notification );

		$redacted = $this->redactor->redact(
			[
				'kind'       => 'webhook.received',
				'ts'         => time(),
				'type'       => (string) $webhook_type,
				'status'     => is_object( $object ) && isset( $object->status ) ? (string) $object->status : null,
				'intent_id'  => is_object( $object ) && isset( $object->object, $object->id ) && 'payment_intent' === $object->object ? (string) $object->id : null,
				'charge_id'  => is_object( $object ) && isset( $object->object, $object->id ) && 'charge' === $object->object ? (string) $object->id : null,
				'order_id'   => $order_id,
				'session_id' => $session_id,
			]
		);
		$this->store->append_event( $session_id, $redacted );
		$this->promoter->maybe_promote( $session_id, $redacted );

		// Webhook-only traces also spend a slot from the capture budget.
		if ( class_exists( 'WC_REST_Stripe_Diagnostics_Controller' ) ) {
			WC_REST_Stripe_Diagnostics_Controller::enforce_capture_limit( $this->store );
		}
	}

	/**
	 * Build the lookup key used to pair a request with its response in
	 * {@see self::$request_start_times}. Stripe API calls are POST-only,
	 * so api alone uniquely identifies a round trip.
	 *
	 * @param string $api The Stripe endpoint (for example `payment_intents`).
	 * @return string
	 */
	private function request_key( string $api ): string {
		return $api . ':POST';
	}

	/**
	 * Extract the Stripe error shape from a decoded response body, if present.
	 *
	 * @param mixed $response Decoded response body.
	 * @return array|null Normalized error fields, or null when the response isn't an error.
	 */
	private static function extract_error( $response ): ?array {
		if ( ! is_object( $response ) || ! isset( $response->error ) || ! is_object( $response->error ) ) {
			return null;
		}
		$err = $response->error;
		return [
			'code'         => isset( $err->code ) ? (string) $err->code : null,
			'type'         => isset( $err->type ) ? (string) $err->type : null,
			'decline_code' => isset( $err->decline_code ) ? (string) $err->decline_code : null,
		];
	}

	/**
	 * Extract the data.object from a Stripe webhook notification payload.
	 *
	 * @param mixed $notification Decoded webhook payload.
	 * @return object|null
	 */
	private static function webhook_object( $notification ): ?object {
		if ( is_object( $notification ) && isset( $notification->data, $notification->data->object ) ) {
			return is_object( $notification->data->object ) ? $notification->data->object : null;
		}
		return null;
	}

	/**
	 * Pull the diagnostics session id out of a webhook's metadata.
	 *
	 * @param mixed $notification Decoded webhook payload.
	 * @return string|null Sanitized session id, or null when absent.
	 */
	private static function extract_session_id_from_webhook( $notification ): ?string {
		$object = self::webhook_object( $notification );
		if ( ! is_object( $object ) || ! isset( $object->metadata ) ) {
			return null;
		}
		$metadata = $object->metadata;
		$raw      = null;
		if ( is_object( $metadata ) && isset( $metadata->{self::SESSION_ID_META_KEY} ) ) {
			$raw = $metadata->{self::SESSION_ID_META_KEY};
		} elseif ( is_array( $metadata ) && isset( $metadata[ self::SESSION_ID_META_KEY ] ) ) {
			$raw = $metadata[ self::SESSION_ID_META_KEY ];
		}
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$clean = WC_Stripe_Diagnostics_Trace_Store::sanitize_id( $raw );
		return '' === $clean ? null : $clean;
	}
}
