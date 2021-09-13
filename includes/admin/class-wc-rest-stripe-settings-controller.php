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
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * Injected gateway should be an instance of WC_Stripe_Payment_Gateway or WC_Stripe_UPE_Payment_Gateway.
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
		$form_fields = $this->gateway->get_form_fields();

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
					'is_payment_request_enabled'       => [
						'description'       => __( 'If Stripe express checkouts should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_locations' => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $form_fields['payment_request_button_locations']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_type'      => [
						'description'       => __( 'Express checkout button types.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $form_fields['payment_request_button_type']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_size'      => [
						'description'       => __( 'Express checkout button sizes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							// it can happen that `$form_fields['payment_request_button_size']` is empty (in tests) - fixing temporarily.
							'enum' => array_keys( isset( $form_fields['payment_request_button_size']['options'] ) ? $form_fields['payment_request_button_size']['options'] : [] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_theme'     => [
						'description'       => __( 'Express checkout button themes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $form_fields['payment_request_button_theme']['options'] ),
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
				'is_payment_request_enabled'       => 'yes' === $this->gateway->get_option( 'payment_request' ),
				'payment_request_button_type'      => $this->gateway->get_option( 'payment_request_button_type' ),
				'payment_request_button_theme'     => $this->gateway->get_option( 'payment_request_button_theme' ),
				'payment_request_button_size'      => $this->gateway->get_option( 'payment_request_button_size' ),
				'payment_request_button_locations' => $this->gateway->get_option( 'payment_request_button_locations' ),
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
		$this->update_payment_request_settings( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates the "payment request" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_payment_request_enabled( WP_REST_Request $request ) {
		if ( null === $request->get_param( 'is_payment_request_enabled' ) ) {
			return;
		}

		$is_payment_request_enabled = $request->get_param( 'is_payment_request_enabled' );

		$this->gateway->update_option( 'payment_request', $is_payment_request_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates appearance attributes of the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_payment_request_settings( WP_REST_Request $request ) {
		$attributes = [
			'payment_request_button_type'      => 'payment_request_button_type',
			'payment_request_button_size'      => 'payment_request_button_size',
			'payment_request_button_theme'     => 'payment_request_button_theme',
			'payment_request_button_locations' => 'payment_request_button_locations',
		];
		foreach ( $attributes as $request_key => $attribute ) {
			if ( ! $request->has_param( $request_key ) ) {
				continue;
			}

			$value = $request->get_param( $request_key );
			$this->gateway->update_option( $attribute, $value );
		}
	}
}
