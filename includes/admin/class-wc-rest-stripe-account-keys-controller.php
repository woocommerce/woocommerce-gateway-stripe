<?php
/**
 * Class WC_REST_Stripe_Account_Keys_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for saving Stripe's test/live account keys.
 *
 * This includes Live Publishable Key, Live Secret Key, Webhook Secret.
 *
 * @since 5.6.0
 */
class WC_REST_Stripe_Account_Keys_Controller extends WC_Stripe_REST_Base_Controller {
	const STRIPE_GATEWAY_SETTINGS_OPTION_NAME = 'woocommerce_stripe_settings';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/account_keys';

	/**
	 * The instance of the Stripe account.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Account $account The instance of the Stripe account.
	 */
	public function __construct( WC_Stripe_Account $account ) {
		$this->account = $account;
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
						'validate_callback' => [ $this, 'validate_publishable_key' ],
					],
					'secret_key'           => [
						'description'       => __( 'Your Stripe API Secret, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_secret_key' ],
					],
					'webhook_secret'       => [
						'description'       => __( 'Your Stripe webhook endpoint URL, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'test_publishable_key' => [
						'description'       => __( 'Your Stripe testing API Publishable key, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_test_publishable_key' ],
					],
					'test_secret_key'      => [
						'description'       => __( 'Your Stripe testing API Secret, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_test_secret_key' ],
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
		$allowed_params  = [ 'publishable_key', 'secret_key', 'webhook_secret', 'test_publishable_key', 'test_secret_key', 'test_webhook_secret' ];
		$stripe_settings = get_option( self::STRIPE_GATEWAY_SETTINGS_OPTION_NAME, [] );
		// Filter only the fields we want to return
		$account_keys = array_intersect_key( $stripe_settings, array_flip( $allowed_params ) );

		return new WP_REST_Response( $account_keys );
	}

	/**
	 * Validate stripe publishable keys and secrets. Allow empty string to erase key.
	 * Also validates against explicit key prefixes based on live/test environment.
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @param array $validate_options
	 * @return true|WP_Error
	 */
	private function validate_stripe_param( $param, $request, $key, $validate_options ) {
		if ( empty( $param ) ) {
			return true;
		}
		$result = rest_validate_request_arg( $param, $request, $key );
		if ( ! empty( $result ) && ! preg_match( $validate_options['regex'], $param ) ) {
			return new WP_Error( 400, $validate_options['error_message'] );
		}
		return true;
	}

	public function validate_publishable_key( $param, $request, $key ) {
		return $this->validate_stripe_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^pk_live_/',
				'error_message' => __( 'The "Live Publishable Key" should start with "pk_live", enter the correct key.', 'woocommerce-gateway-stripe' ),
			]
		);
	}

	public function validate_secret_key( $param, $request, $key ) {
		return $this->validate_stripe_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^[rs]k_live_/',
				'error_message' => __( 'The "Live Secret Key" should start with "sk_live" or "rk_live", enter the correct key.', 'woocommerce-gateway-stripe' ),
			]
		);
	}

	public function validate_test_publishable_key( $param, $request, $key ) {
		return $this->validate_stripe_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^pk_test_/',
				'error_message' => __( 'The "Test Publishable Key" should start with "pk_test", enter the correct key.', 'woocommerce-gateway-stripe' ),
			]
		);
	}

	public function validate_test_secret_key( $param, $request, $key ) {
		return $this->validate_stripe_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^[rs]k_test_/',
				'error_message' => __( 'The "Test Secret Key" should start with "sk_test" or "rk_test", enter the correct key.', 'woocommerce-gateway-stripe' ),
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

		$settings = get_option( self::STRIPE_GATEWAY_SETTINGS_OPTION_NAME, [] );

		// If all keys were empty, then is a new account; we need to set the test/live mode.
		$new_account = ! trim( $settings['publishable_key'] )
					&& ! trim( $settings['secret_key'] )
					&& ! trim( $settings['test_publishable_key'] )
					&& ! trim( $settings['test_secret_key'] );
		// If all new keys are empty, then account is being disconnected. We should disable the payment gateway.
		$is_deleting_account = ! trim( $publishable_key )
							&& ! trim( $secret_key )
							&& ! trim( $test_publishable_key )
							&& ! trim( $test_secret_key );

		$settings['publishable_key']      = is_null( $publishable_key ) ? $settings['publishable_key'] : $publishable_key;
		$settings['secret_key']           = is_null( $secret_key ) ? $settings['secret_key'] : $secret_key;
		$settings['webhook_secret']       = is_null( $webhook_secret ) ? $settings['webhook_secret'] : $webhook_secret;
		$settings['test_publishable_key'] = is_null( $test_publishable_key ) ? $settings['test_publishable_key'] : $test_publishable_key;
		$settings['test_secret_key']      = is_null( $test_secret_key ) ? $settings['test_secret_key'] : $test_secret_key;
		$settings['test_webhook_secret']  = is_null( $test_webhook_secret ) ? $settings['test_webhook_secret'] : $test_webhook_secret;

		if ( $new_account ) {
			$settings['enabled'] = 'yes';
			if ( trim( $settings['publishable_key'] ) && trim( $settings['secret_key'] ) ) {
				$settings['testmode'] = 'no';
			} elseif ( trim( $settings['test_publishable_key'] ) && trim( $settings['test_secret_key'] ) ) {
				$settings['testmode'] = 'yes';
			}
		} elseif ( $is_deleting_account ) {
			$settings['enabled'] = 'no';
		}

		update_option( self::STRIPE_GATEWAY_SETTINGS_OPTION_NAME, $settings );
		$this->account->clear_cache();

		// Gives an instant reply if the connection was succesful or not + rebuild the cache for the next request
		$account = $this->account->get_cached_account_data();

		return new WP_REST_Response( $account, 200 );
	}
}
