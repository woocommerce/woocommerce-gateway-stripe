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
 * `wc_stripe_request_body` (request capture) and `wc_stripe_request_response`
 * (response capture) without threading state through every call site.
 */
class WC_Stripe_Diagnostics_Recorder {

	const SESSION_ID_META_KEY   = 'wc_diag_session_id';
	const SESSION_OPTION        = 'wc_stripe_diag_active_session';
	const SESSION_TTL           = 30 * MINUTE_IN_SECONDS;
	const METADATA_ENABLED_APIS = [ 'payment_intents', 'setup_intents', 'customers' ];

	/**
	 * WC session key under which each shopper's diagnostics session id is
	 * stored. Per-shopper (not a global transient) so different shoppers
	 * don't share a trace.
	 *
	 * @var string
	 */
	const WC_SESSION_KEY = 'wc_stripe_diag_session_id';

	/**
	 * Number of traces the trace store may hold before the toggle
	 * auto-disables. Bounds the merchant's exposure and keeps a debugging
	 * window from running indefinitely if they forget to flip it back off.
	 *
	 * @var int
	 */
	const CAPTURE_LIMIT = 20;

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
		add_filter( 'wc_stripe_request_response', [ $this, 'on_request_response' ], 10, 4 );
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
	 * Return the currently active session id for this request, checking (in
	 * order): in-memory field, the WC session key that the enqueue path
	 * stores a per-shopper id in, and the admin-initiated transient.
	 */
	public function current_session_id(): ?string {
		if ( null !== $this->current_session_id ) {
			return $this->current_session_id;
		}
		$from_wc = self::read_wc_session();
		if ( null !== $from_wc ) {
			$this->current_session_id = $from_wc;
			return $from_wc;
		}
		$persisted = get_transient( self::SESSION_OPTION );
		if ( is_string( $persisted ) && '' !== $persisted ) {
			$this->current_session_id = $persisted;
			return $persisted;
		}
		return null;
	}

	/**
	 * Ensure this shopper has a diagnostics session id when the feature is
	 * toggled on. Called from the localized-data filter during script
	 * enqueue, so every checkout page load gets a fresh id (per shopper,
	 * not per hit — the id is cached on the WC session).
	 *
	 * Auto-disables the toggle when the store already holds
	 * {@see self::CAPTURE_LIMIT} traces, so merchants don't have to babysit it.
	 *
	 * @return string|null The session id to publish to the frontend, or null
	 *                     when the feature is off / the limit was reached.
	 */
	public function ensure_shopper_session(): ?string {
		if ( ! self::is_enabled() ) {
			return null;
		}

		// Budget check runs first — when the merchant has their N traces the
		// toggle flips off even for shoppers who were already mid-session.
		if ( $this->store->count() >= self::CAPTURE_LIMIT ) {
			update_option( 'wc_stripe_diagnostics_enabled', 'no' );
			return null;
		}

		$existing = self::read_wc_session();
		if ( null !== $existing ) {
			$this->current_session_id = $existing;
			return $existing;
		}

		$new_id = WC_Stripe_Diagnostics_Trace_Store::sanitize_id( 'diag-' . wp_generate_password( 12, false, false ) );
		if ( '' === $new_id ) {
			return null;
		}
		self::write_wc_session( $new_id );
		$this->current_session_id = $new_id;
		return $new_id;
	}

	/**
	 * Read this shopper's diagnostics session id from WC session, if any.
	 */
	private static function read_wc_session(): ?string {
		if ( ! function_exists( 'WC' ) || null === WC()->session ) {
			return null;
		}
		$value = WC()->session->get( self::WC_SESSION_KEY );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Write this shopper's diagnostics session id into WC session.
	 */
	private static function write_wc_session( string $session_id ): void {
		if ( ! function_exists( 'WC' ) || null === WC()->session ) {
			return;
		}
		WC()->session->set( self::WC_SESSION_KEY, $session_id );
	}

	/**
	 * Whether the diagnostics feature is currently enabled.
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( 'wc_stripe_diagnostics_enabled', 'no' );
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
	 * @return mixed The unchanged response body.
	 */
	public function on_request_response( $response_body, $api, $method, $request ) {
		$session_id = $this->current_session_id();
		if ( null === $session_id ) {
			return $response_body;
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

		return $response_body;
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
	 * @param array  $data          The existing localized data.
	 * @param string $script_handle The script handle being localized.
	 * @param string $object_name   The JS variable name.
	 * @return array
	 */
	public function on_localized_data( $data, $script_handle, $object_name ) {
		$session_id = $this->current_session_id() ?? $this->ensure_shopper_session();
		if ( null === $session_id ) {
			return $data;
		}

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
