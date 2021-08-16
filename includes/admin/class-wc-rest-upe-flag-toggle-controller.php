<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for UPE feature flag.
 */
class WC_REST_UPE_Flag_Toggle_Controller extends WP_REST_Controller {
	const UPE_FLAG_NAME = '_wcpay_feature_upe';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/upe_flag_toggle';

	/**
	 * Verify access to request.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Configure REST API routes.
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
	 * @return WP_REST_Response
	 */
	public function get_flag(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'is_upe_enabled' =>  '1' === get_option( self::UPE_FLAG_NAME, '0' )
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function set_flag( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_upe_enabled' ) ) {
			return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
		}

		$is_upe_enabled = $request->get_param( 'is_upe_enabled' );

		if ( $is_upe_enabled ) {
			update_option( self::UPE_FLAG_NAME, '1' );
		} else {
			update_option( self::UPE_FLAG_NAME, '0' );
		}

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
