<?php
/**
 * Class WC_REST_Stripe_Account_Keys_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for saving Stripe's test/live account keys. This includes Live Publishable Key,
 * Live Secret Key, Webhook Secret.
 */
class WC_REST_Stripe_Account_Keys_Controller extends WP_REST_Controller {
	const STRIPE_GATEWAY_SETTINGS_OPTION_NAME = 'woocommerce_stripe_settings';

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
	protected $rest_base = 'wc_stripe/account_keys';

	/**
	 * Verify access to request.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	private function get_stripe_settings() {
		return get_option( self::STRIPE_GATEWAY_SETTINGS_OPTION_NAME, [] );
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
				'callback'            => [ $this, 'get_account_keys' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_account_keys' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'publishable_key'      => [
						'description'       => __( 'Your Stripe API Publishable key, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'secret_key'           => [
						'description'       => __( 'Your Stripe API Secret, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'webhook_secret'       => [
						'description'       => __( 'Your Stripe webhook endpoint URL, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'test_publishable_key' => [
						'description'       => __( 'Your Stripe testing API Publishable key, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'test_secret_key'      => [
						'description'       => __( 'Your Stripe testing API Secret, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'test_webhook_secret'  => [
						'description'       => __( 'Your Stripe testing webhook endpoint URL, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
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
	public function get_account_keys() {
		//TODO: Debug/testing only, remove this end point.
		return new WP_REST_Response(
			[
				'settings' => $this->get_stripe_settings(),
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function set_account_keys( WP_REST_Request $request ) {
		$publishable_key      = $request->get_param( 'publishable_key' );
		$secret_key           = $request->get_param( 'secret_key' );
		$webhook_secret       = $request->get_param( 'webhook_secret' );
		$test_publishable_key = $request->get_param( 'test_publishable_key' );
		$test_secret_key      = $request->get_param( 'test_secret_key' );
		$test_webhook_secret  = $request->get_param( 'test_webhook_secret' );

		//TODO: validate keys starts with pk_live and sk_live or rk_live

		$settings                         = $this->get_stripe_settings();
		$settings['publishable_key']      = empty( $publishable_key ) ? $settings['publishable_key'] : $publishable_key;
		$settings['secret_key']           = empty( $secret_key ) ? $settings['secret_key'] : $secret_key;
		$settings['webhook_secret']       = empty( $webhook_secret ) ? $settings['webhook_secret'] : $webhook_secret;
		$settings['test_publishable_key'] = empty( $test_publishable_key ) ? $settings['test_publishable_key'] : $test_publishable_key;
		$settings['test_secret_key']      = empty( $test_secret_key ) ? $settings['test_secret_key'] : $test_secret_key;
		$settings['test_webhook_secret']  = empty( $test_webhook_secret ) ? $settings['test_webhook_secret'] : $test_webhook_secret;

		update_option( self::STRIPE_GATEWAY_SETTINGS_OPTION_NAME, $settings );

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
