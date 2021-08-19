<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for UPE feature flag.
 */
class WC_REST_UPE_Flag_Toggle_Controller extends WP_REST_Controller {
	const UPE_CHECKOUT_EXPERIENCE_FLAG = 'upe_checkout_experience_enabled';

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
						'type'              => 'string',
						'enum'              => [ 'yes', 'no' ],
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
	public function get_flag() {
		$settings       = get_option( 'woocommerce_stripe_settings' );
		$is_upe_enabled = 'no';

		if ( ! empty( $settings[ self::UPE_CHECKOUT_EXPERIENCE_FLAG ] ) ) {
			$is_upe_enabled = $settings[ self::UPE_CHECKOUT_EXPERIENCE_FLAG ];
		}

		return new WP_REST_Response(
			[
				'is_upe_enabled' => $is_upe_enabled,
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function set_flag( WP_REST_Request $request ) {
		$is_upe_enabled = $request->get_param( 'is_upe_enabled' );

		if ( null === $is_upe_enabled ) {
			return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
		}

		$settings = get_option( 'woocommerce_stripe_settings' );

		if ( 'yes' === $is_upe_enabled ) {
			$settings[ self::UPE_CHECKOUT_EXPERIENCE_FLAG ] = 'yes';
		} else {
			$settings[ self::UPE_CHECKOUT_EXPERIENCE_FLAG ] = 'no';
		}

		update_option( 'woocommerce_stripe_settings', $settings );

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
