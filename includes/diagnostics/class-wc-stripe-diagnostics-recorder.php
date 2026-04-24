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
 * - Injects a `wcStripeDiag` global alongside the plugin's existing localized
 *   script params so the frontend recorder can boot.
 *
 * The recorder is a singleton so the running session id can be shared across
 * `wc_stripe_request_body` (request capture) and
 * `wc_stripe_api_response_received` (response capture) without threading state
 * through every call site.
 */
class WC_Stripe_Diagnostics_Recorder {

	const SESSION_ID_META_KEY   = 'wc_diag_session_id';
	const SESSION_OPTION        = 'wc_stripe_diag_active_session';
	const SESSION_TTL           = 30 * MINUTE_IN_SECONDS;
	const METADATA_ENABLED_APIS = [ 'payment_intents', 'setup_intents', 'customers' ];

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
	 * Whether the wcStripeDiag global has already been emitted for this
	 * request. The wc_stripe_localized_data filter fires from up to four
	 * call sites per checkout (gateway, UPE gateway, express checkout x2);
	 * without this guard each pass would print a redundant inline script.
	 *
	 * @var bool
	 */
	private $has_localized_diag = false;

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
			self::$instance = new self(
				new WC_Stripe_Diagnostics_Trace_Store(),
				new WC_Stripe_Diagnostics_Redactor()
			);
		}
		return self::$instance;
	}

	/**
	 * Construct with explicit dependencies. Tests inject fakes via this ctor;
	 * production callers should use {@see self::get_instance()}.
	 *
	 * @param WC_Stripe_Diagnostics_Trace_Store $store    Trace store.
	 * @param WC_Stripe_Diagnostics_Redactor    $redactor Redaction engine.
	 */
	public function __construct( WC_Stripe_Diagnostics_Trace_Store $store, WC_Stripe_Diagnostics_Redactor $redactor ) {
		$this->store    = $store;
		$this->redactor = $redactor;
	}

	/**
	 * Wire up the filter subscriptions. Called once during plugin init.
	 */
	public function init(): void {
		add_filter( 'wc_stripe_request_body', [ $this, 'on_request_body' ], 10, 2 );
		add_action( 'wc_stripe_api_response_received', [ $this, 'on_request_response' ], 10, 4 );
		add_action( 'wc_stripe_webhook_received', [ $this, 'on_webhook_received' ], 10, 3 );
		add_filter( 'wc_stripe_localized_data', [ $this, 'on_localized_data' ], 10, 3 );
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
		// Persist so later requests within the TTL window can resume.
		set_transient( self::SESSION_OPTION, $session_id, self::SESSION_TTL );
		return true;
	}

	/**
	 * Return the currently active session id, reading from the persisted
	 * transient when this request didn't explicitly start one.
	 */
	public function current_session_id(): ?string {
		if ( null !== $this->current_session_id ) {
			return $this->current_session_id;
		}
		$persisted = get_transient( self::SESSION_OPTION );
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

		$this->request_start_times[ $this->request_key( $api ) ] = microtime( true );

		$this->store->append_event(
			$session_id,
			$this->redactor->redact(
				[
					'kind'         => 'apiRequest',
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
	 */
	public function on_request_response( $response_body, $api, $method, $request ): void {
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

		$event = [
			'kind'       => 'apiResponse',
			'ts'         => time(),
			'api'        => (string) $api,
			'method'     => (string) $method,
			'latency_ms' => $latency,
			'request_id' => self::extract_request_id( $response_body ),
			'error'      => self::extract_error( $response_body ),
		];
		$this->store->append_event( $session_id, $this->redactor->redact( $event ) );
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

		$object = self::webhook_object( $notification );

		$this->store->append_event(
			$session_id,
			$this->redactor->redact(
				[
					'kind'       => 'webhookReceived',
					'ts'         => time(),
					'type'       => (string) $webhook_type,
					'status'     => is_object( $object ) && isset( $object->status ) ? (string) $object->status : null,
					'intent_id'  => is_object( $object ) && isset( $object->object, $object->id ) && 'payment_intent' === $object->object ? (string) $object->id : null,
					'charge_id'  => is_object( $object ) && isset( $object->object, $object->id ) && 'charge' === $object->object ? (string) $object->id : null,
					'order_id'   => is_object( $resolved_order ) && method_exists( $resolved_order, 'get_id' ) ? (int) $resolved_order->get_id() : null,
					'session_id' => $session_id,
				]
			)
		);
	}

	/**
	 * Publish the `wcStripeDiag` global to the frontend recorder whenever a
	 * Stripe script is being localized. Only runs when a session is active.
	 *
	 * The filter fires from multiple call sites per checkout; we attach the
	 * global to the first invocation only to avoid emitting duplicate inline
	 * `<script>` tags.
	 *
	 * @param array  $data          The existing localized data.
	 * @param string $script_handle The script handle being localized.
	 * @param string $object_name   The JS variable name.
	 * @return array
	 */
	public function on_localized_data( $data, $script_handle, $object_name ) {
		if ( $this->has_localized_diag ) {
			return $data;
		}
		$session_id = $this->current_session_id();
		if ( null === $session_id ) {
			return $data;
		}

		$this->has_localized_diag = true;
		wp_localize_script(
			(string) $script_handle,
			'wcStripeDiag',
			[
				'active'    => true,
				'sessionId' => $session_id,
				'nonce'     => wp_create_nonce( 'wc_stripe_diagnostics' ),
				'endpoint'  => esc_url_raw( rest_url( 'wc/v3/wc_stripe/diagnostics/events' ) ),
			]
		);

		return $data;
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
	 * Pull Stripe's request_id off a decoded response body.
	 *
	 * @param mixed $response Decoded response body.
	 * @return string|null
	 */
	private static function extract_request_id( $response ): ?string {
		if ( is_object( $response ) && isset( $response->request_id ) && is_string( $response->request_id ) ) {
			return $response->request_id;
		}
		return null;
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
