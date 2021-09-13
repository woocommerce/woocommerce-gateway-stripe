<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for UPE feature flag.
 */
class WC_REST_UPE_Flag_Toggle_Controller extends WP_REST_Controller {
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
	public function get_flag() {
		return new WP_REST_Response(
			[
				'is_upe_enabled' => WC_Stripe_Feature_Flags::is_upe_checkout_enabled(),
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

		$settings = get_option( 'woocommerce_stripe_settings', [] );
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = $is_upe_enabled ? 'yes' : 'disabled';

		update_option( 'woocommerce_stripe_settings', $settings );

		// including the class again because otherwise it's not present.
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
			WC_Stripe_UPE_Availability_Note::possibly_delete_note();
		}

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
