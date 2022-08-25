<?php
/**
 * Class WC_REST_Stripe_Payment_Gateway_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic REST controller for payment gateway settings.
 */
class WC_REST_Stripe_Payment_Gateway_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/payment-gateway';

	/**
	 * Stripe payment gateway.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 *  Gateway match array.
	 *
	 * @var array
	 */
	private $gateways = [
		'stripe_sepa'       => WC_Gateway_Stripe_Sepa::class,
		'stripe_giropay'    => WC_Gateway_Stripe_Giropay::class,
		'stripe_ideal'      => WC_Gateway_Stripe_Ideal::class,
		'stripe_bancontact' => WC_Gateway_Stripe_Bancontact::class,
		'stripe_eps'        => WC_Gateway_Stripe_Eps::class,
		'stripe_sofort'     => WC_Gateway_Stripe_Sofort::class,
		'stripe_p24'        => WC_Gateway_Stripe_P24::class,
		'stripe_alipay'     => WC_Gateway_Stripe_Alipay::class,
		'stripe_multibanco' => WC_Gateway_Stripe_Multibanco::class,
		'stripe_oxxo'       => WC_Gateway_Stripe_Oxxo::class,
		'stripe_boleto'     => WC_Gateway_Stripe_Boleto::class,
	];

	/**
	 * Returns an instance of some WC_Gateway_Stripe.
	 *
	 * @return void
	 */
	private function instantiate_gateway( $gateway_id ) {
		$this->gateway = new $this->gateways[ $gateway_id ]();
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<payment_gateway_id>[a-z0-9_]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_gateway_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<payment_gateway_id>[a-z0-9_]+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_payment_gateway_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve payment gateway settings.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_payment_gateway_settings( $request = null ) {
		try {
			$id = $request->get_param( 'payment_gateway_id' );
			$this->instantiate_gateway( $id );
			$settings = [
				'is_' . $id . '_enabled' => $this->gateway->is_enabled(),
				$id . '_name'            => $this->gateway->get_option( 'title' ),
				$id . '_description'     => $this->gateway->get_option( 'description' ),
			];
			if ( method_exists( $this->gateway, 'get_unique_settings' ) ) {
				$settings = $this->gateway->get_unique_settings( $settings );
			}
			return new WP_REST_Response( $settings );
		} catch ( Exception $exception ) {
			return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
		}
	}

	/**
	 * Update payment gateway settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function update_payment_gateway_settings( WP_REST_Request $request ) {
		try {
			$id = $request->get_param( 'payment_gateway_id' );
			$this->instantiate_gateway( $id );
			$this->update_is_gateway_enabled( $request );
			$this->update_gateway_name( $request );
			$this->update_gateway_description( $request );
			if ( method_exists( $this->gateway, 'update_unique_settings' ) ) {
				$this->gateway->update_unique_settings( $request );
			}
			return new WP_REST_Response( [], 200 );
		} catch ( Exception $exception ) {
			return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
		}
	}

	/**
	 * Updates payment gateway enabled status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_gateway_enabled( WP_REST_Request $request ) {
		$field_name = 'is_' . $this->gateway->id . '_enabled';
		$is_enabled = $request->get_param( $field_name );

		if ( null === $is_enabled || ! is_bool( $is_enabled ) ) {
			return;
		}

		if ( $is_enabled ) {
			$this->gateway->enable();
		} else {
			$this->gateway->disable();
		}
	}

	/**
	 * Updates payment gateway title.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_gateway_name( WP_REST_Request $request ) {
		$field_name = $this->gateway->id . '_name';
		$name       = $request->get_param( $field_name );

		if ( null === $name ) {
			return;
		}

		$value = sanitize_text_field( $name );
		$this->gateway->update_option( 'title', $value );
	}

	/**
	 * Updates payment gateway description.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_gateway_description( WP_REST_Request $request ) {
		$field_name  = $this->gateway->id . '_description';
		$description = $request->get_param( $field_name );

		if ( null === $description ) {
			return;
		}

		$value = sanitize_text_field( $description );
		$this->gateway->update_option( 'description', $value );
	}
}
