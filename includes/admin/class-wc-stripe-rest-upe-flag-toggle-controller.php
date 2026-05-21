<?php
/**
 * Class WC_Stripe_REST_UPE_Flag_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the legacy UPE feature flag.
 *
 * UPE has been generally available for several releases and the underlying
 * feature flag was already a no-op; the constants this endpoint used to
 * toggle have since been removed from the codebase. The class is kept as a
 * deprecation shim so any external dashboard / extension still calling the
 * `wc_stripe/upe_flag_toggle` endpoint continues to receive the long-standing
 * "always on" answer while emitting a loud deprecation notice that tooling
 * (Query Monitor, `WP_DEBUG_LOG`, error log scrapers) actually surfaces. The
 * `@deprecated` PHPDoc alone was too quiet to be caught.
 *
 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
 */
class WC_Stripe_REST_UPE_Flag_Toggle_Controller extends WC_Stripe_REST_Base_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/upe_flag_toggle';

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_flag' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_flag' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_upe_enabled' => [
						'description'       => __( 'Determines if the UPE feature flag is enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve flag status.
	 *
	 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
	 *
	 * @return WP_REST_Response
	 */
	public function get_flag() {
		wc_deprecated_function( __METHOD__, '10.2.0' );

		return new WP_REST_Response(
			[
				'is_upe_enabled' => true,
			]
		);
	}

	/**
	 * Update the flag. No-op shim: UPE is already generally available, and the
	 * note-cleanup side-effects this endpoint used to perform run from elsewhere
	 * now. Always reports success so legacy callers don't break.
	 *
	 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function set_flag( WP_REST_Request $request ) {
		wc_deprecated_function( __METHOD__, '10.2.0' );

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
