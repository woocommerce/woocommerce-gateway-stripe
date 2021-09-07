<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings.
 */
class WC_REST_Stripe_Settings_Controller extends WC_Stripe_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/settings';

	/**
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

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
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_payment_request_enabled'        => [
						'description'       => __( 'If WooCommerce Payments express checkouts should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response( [
			'is_payment_request_enabled' => 'yes' === $this->gateway->get_option( 'payment_request' ),
		] );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$this->update_is_payment_request_enabled( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates the "payment request" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_payment_request_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_payment_request_enabled' ) ) {
			return;
		}

		$is_payment_request_enabled = $request->get_param( 'is_payment_request_enabled' );

		$this->gateway->update_option( 'payment_request', $is_payment_request_enabled ? 'yes' : 'no' );
	}
}
