<?php
/**
 * Class WC_Stripe_REST_UPE_Flag_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the legacy UPE feature flag.
 *
 * This class is fully deprecated, but is being kept in the code for backwards compatibility.
 * It will be fully removed in an upcoming release.
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
	 * Deprecated no-op. Kept in the code for backwards compatibility.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Full data about the request.
	 *
	 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
	 *
	 * @return WP_REST_Response
	 */
	public function set_flag( WP_REST_Request $request ) {
		wc_deprecated_function( __METHOD__, '10.2.0' );

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
