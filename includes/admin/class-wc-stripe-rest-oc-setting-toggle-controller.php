<?php
/**
 * Class WC_Stripe_REST_OC_Setting_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for OC setting.
 */
class WC_Stripe_REST_OC_Setting_Toggle_Controller extends WC_Stripe_REST_Base_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/oc_setting_toggle';

	/**
	 * Stripe payment gateway.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_Stripe $gateway Stripe payment gateway.
	 */
	public function __construct( WC_Gateway_Stripe $gateway ) {
		$this->gateway = $gateway;
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
				'callback'            => [ $this, 'get_setting' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_setting' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_oc_enabled' => [
						'description'       => __( 'Determines if the OC setting is enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve the setting status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_setting() {
		return new WP_REST_Response(
			[
				'is_oc_enabled' => $this->gateway->is_oc_enabled(),
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function set_setting( WP_REST_Request $request ) {
		$is_oc_enabled = $request->get_param( 'is_oc_enabled' );

		if ( null === $is_oc_enabled ) {
			return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
		}

		$current_value                          = $this->gateway->is_oc_enabled();
		$settings                               = WC_Stripe_Helper::get_stripe_settings();
		$value                                  = $is_oc_enabled ? 'yes' : 'no';
		$settings['optimized_checkout_element'] = $value;

		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		if ( $is_oc_enabled !== $current_value ) {
			wc_admin_record_tracks_event(
				$is_oc_enabled ? 'wcstripe_oc_enabled' : 'wcstripe_oc_disabled',
				[
					'test_mode' => WC_Stripe_Mode::is_test() ? 1 : 0,
					'source'    => 'oc_setting_toggle_rest_api',
				]
			);
		}

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
