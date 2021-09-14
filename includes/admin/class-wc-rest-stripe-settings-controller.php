<?php
/**
 * Class WC_REST_Stripe_Settings_Controller
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
	 * Stripe payment gateway.
	 *
	 * @var WC_Stripe_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * Injected gateway should be an instance of WC_Gateway_Stripe or WC_Stripe_UPE_Payment_Gateway.
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway Stripe payment gateway.
	 */
	public function __construct( WC_Stripe_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		$wcpay_form_fields = $this->gateway->get_form_fields();

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
					'is_payment_request_enabled' => [
						'description'       => __( 'If Stripe express checkouts should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_enabled_locations' => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ),
						],
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
		return new WP_REST_Response(
			[
				'is_payment_request_enabled'        => 'yes' === $this->gateway->get_option( 'payment_request' ),
				'payment_request_enabled_locations' => $this->gateway->get_option( 'payment_request_button_locations' ),
			]
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$this->update_is_payment_request_enabled( $request );
		$this->update_payment_request_enabled_locations( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates the "payment request" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_payment_request_enabled( WP_REST_Request $request ) {
		$is_payment_request_enabled = $request->get_param( 'is_payment_request_enabled' );

		if ( null === $is_payment_request_enabled ) {
			return;
		}

		$this->gateway->update_option( 'payment_request', $is_payment_request_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates the list of locations that will show the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_payment_request_enabled_locations( WP_REST_Request $request ) {
		$payment_request_enabled_locations = $request->get_param( 'payment_request_enabled_locations' );

		if ( null === $payment_request_enabled_locations ) {
			return;
		}

		$this->gateway->update_option( 'payment_request_button_locations', $payment_request_enabled_locations );
	}
}
