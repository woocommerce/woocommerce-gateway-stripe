<?php

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the diagnostics events ingest endpoint.
 *
 * Takes the frontend recorder's batched events and writes them to the
 * trace store.
 *
 * Route: POST /wc/v3/wc_stripe/diagnostics/events
 */
class WC_REST_Stripe_Diagnostics_Controller extends WP_REST_Controller {

	const ENABLED_OPTION = 'wc_stripe_diagnostics_enabled';
	const NONCE_ACTION   = 'wc_stripe_diagnostics';

	protected $namespace = 'wc/v3';
	protected $rest_base = 'wc_stripe/diagnostics';

	/**
	 * Trace store dependency.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	public function __construct( ?WC_Stripe_Diagnostics_Trace_Store $store = null ) {
		$this->store = $store ?? new WC_Stripe_Diagnostics_Trace_Store();
	}

	/**
	 * Register this controller's routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/events',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ingest_events' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'sessionId' => [
						'required' => true,
						'type'     => 'string',
					],
					'events'    => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);
	}

	/**
	 * Permission callback. Requires:
	 * 1. The diagnostics feature is enabled.
	 * 2. A valid `wc_stripe_diagnostics` nonce in either an HTTP header or the body.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'wc_stripe_diagnostics_disabled', 'Diagnostics mode is not enabled.', [ 'status' => 403 ] );
		}

		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = (string) $request->get_param( 'nonce' );
		}
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'wc_stripe_diagnostics_bad_nonce', 'Invalid diagnostics nonce.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Ingest a batch of events.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_events( $request ) {
		$session_id = WC_Stripe_Diagnostics_Trace_Store::sanitize_id( (string) $request->get_param( 'sessionId' ) );
		if ( '' === $session_id ) {
			return new WP_Error( 'wc_stripe_diagnostics_bad_session', 'Invalid sessionId.', [ 'status' => 400 ] );
		}

		$raw_events = $request->get_param( 'events' );
		if ( ! is_array( $raw_events ) ) {
			return new WP_Error( 'wc_stripe_diagnostics_bad_events', 'events must be an array.', [ 'status' => 400 ] );
		}
		if ( count( $raw_events ) > 200 ) {
			$raw_events = array_slice( $raw_events, 0, 200 );
		}

		// Lazily create the trace on the first event for this session. When
		// the store is at its trace cap, the store itself evicts the oldest
		// entry (FIFO) to make room.
		if ( null === $this->store->get( $session_id ) ) {
			$this->store->create( $session_id, [ 'source' => 'client' ] );
		}

		$written = 0;
		foreach ( $raw_events as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			if ( $this->store->append_event( $session_id, $raw ) ) {
				++$written;
			}
		}

		return new WP_REST_Response( [ 'written' => $written ], 200 );
	}

	/**
	 * Whether the diagnostics feature is currently enabled.
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( self::ENABLED_OPTION, 'no' );
	}
}
