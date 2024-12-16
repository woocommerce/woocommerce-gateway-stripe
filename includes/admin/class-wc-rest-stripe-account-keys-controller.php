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
	/**
	 * The option name for the Stripe gateway settings.
	 *
	 * @deprecated 8.7.0
	 */
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
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'test_account_keys' ],
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
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/configure_webhooks',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'configure_webhooks' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'live_mode'  => [
						'description'       => __( 'Whether the account is in live mode.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'secret_key' => [
						'description'       => __( 'Your Stripe API Secret, obtained from your Stripe dashboard.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_secret_key' ],
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
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		// Filter only the fields we want to return
		$account_keys = array_intersect_key( $stripe_settings, array_flip( $allowed_params ) );

		// Mask the keys
		foreach ( $account_keys as $key => $value ) {
			if ( ! empty( $value ) ) {
				$account_keys[ $key ] = $this->mask_key_value( $value );
			}
		}

		return new WP_REST_Response( $account_keys );
	}

	private function mask_key_value( $value ): string {
		return substr( $value, 0, 10 ) . str_repeat( '*', 50 ) . substr( $value, -2 );
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
		$settings       = WC_Stripe_Helper::get_stripe_settings();
		$allowed_params = [ 'publishable_key', 'secret_key', 'webhook_secret', 'test_publishable_key', 'test_secret_key', 'test_webhook_secret' ];

		$current_account_keys = array_intersect_key( $settings, array_flip( $allowed_params ) );
		foreach ( $current_account_keys as $key => $value ) {
			$new_value = wc_clean( wp_unslash( $request->get_param( $key ) ) );
			if ( ! is_null( $new_value ) && $new_value !== $this->mask_key_value( $value ) ) {
				$settings[ $key ] = $new_value;
			}
		}

		// If all new keys are empty, then account is being disconnected. We should disable the payment gateway.
		$is_deleting_account = ! $settings['publishable_key']
							&& ! $settings['secret_key']
							&& ! $settings['test_publishable_key']
							&& ! $settings['test_secret_key'];

		if ( $is_deleting_account ) {
			$settings['enabled']              = 'no';
			$settings['connection_type']      = '';
			$settings['test_connection_type'] = '';
			$settings['refresh_token']        = '';
			$settings['test_refresh_token']   = '';
			$this->record_manual_account_disconnect_track_event( WC_Stripe_Mode::is_test() );
		} else {
			$this->record_manual_account_key_update_track_event( WC_Stripe_Mode::is_test() );
		}

		// Before saving the settings, decommission any previously automatically configured webhook endpoint.
		$settings = $this->decommission_configured_webhook_after_key_update( $settings, $current_account_keys );

		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// Disable all payment methods if all keys are different from the current ones
		if ( $current_account_keys['publishable_key'] !== $settings['publishable_key']
			|| $current_account_keys['secret_key'] !== $settings['secret_key']
			|| $current_account_keys['test_publishable_key'] !== $settings['test_publishable_key']
			|| $current_account_keys['test_secret_key'] !== $settings['test_secret_key'] ) {

			$is_upe_enabled = 'yes' === $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ];
			if ( ! $is_upe_enabled ) {
				$payment_gateways = WC_Stripe_Helper::get_legacy_payment_methods();
				foreach ( $payment_gateways as $gateway ) {
					$gateway->update_option( 'enabled', 'no' );
				}
			} else {
				$upe_gateway = new WC_Stripe_UPE_Payment_Gateway();
				$upe_gateway->update_option( 'upe_checkout_experience_accepted_payments', [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ] );

				// Handle Multibanco separately as it is a non UPE method but it is part of the same settings page.
				$multibanco = WC_Stripe_Helper::get_legacy_payment_method( 'stripe_multibanco' );
				$multibanco->update_option( 'enabled', 'no' );
			}
		}

		$this->account->clear_cache();

		// Gives an instant reply if the connection was successful or not + rebuild the cache for the next request
		$account = $this->account->get_cached_account_data();

		return new WP_REST_Response( $account, 200 );
	}

	/**
	 * Tests the Stripe API Keys, using the received keys, or the unmasked version if they were not changed.
	 *
	 * This code was ported from the frontend as we no longer have the unmasked keys available in the client.
	 * It uses the Stripe token API to check the keys, and it DOES NOT use the current saved keys, it uses the received
	 * data (except for masked keys, for those the original value from settings is used)
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function test_account_keys( WP_REST_Request $request ) {
		$live_mode   = wc_clean( wp_unslash( $request->get_param( 'live_mode' ) ) );
		$publishable = wc_clean( wp_unslash( $request->get_param( 'publishable' ) ) );
		$secret      = wc_clean( wp_unslash( $request->get_param( 'secret' ) ) );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		if ( $publishable === $this->mask_key_value( $publishable ) ) {
			$publishable = $settings[ $live_mode ? 'publishable_key' : 'test_publishable_key' ];
		}
		if ( $secret === $this->mask_key_value( $secret ) ) {
			$secret = $settings[ $live_mode ? 'secret_key' : 'test_secret_key' ];
		}

		$response = wp_safe_remote_post(
			'https://api.stripe.com/v1/tokens',
			[
				'method'  => 'POST',
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => 'pii[personal_id_number]=connection_test&key=' . $publishable,
				'timeout' => 60,
			]
		);
		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			return new WP_REST_Response( [], 422 );
		}

		$response_data = json_decode( $response['body'] );
		$token_id      = $response_data->id;

		$response = wp_safe_remote_get(
			'https://api.stripe.com/v1/tokens/' . $token_id,
			[
				'method'  => 'GET',
				'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $secret . ':' ) ],
				'timeout' => 60,
			]
		);
		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			return new WP_REST_Response( [], 422 );
		}

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Configure webhooks for the Stripe Account.
	 *
	 * This will create a webhook endpoint in the Stripe account with the events we need to listen to at the correct URL.
	 * The webhook secret will be stored in the settings.
	 *
	 * @param WP_REST_Request $request Data about the request.
	 */
	public function configure_webhooks( WP_REST_Request $request ) {
		$live_mode      = wc_clean( wp_unslash( $request->get_param( 'live_mode' ) ) );
		$environment    = $live_mode ? 'live' : 'test';
		$rate_limit_key = "wc-stripe-configure-{$environment}-webhooks-" . get_current_user_id();

		// Prevent users from setting up webhooks too frequently.
		if ( WC_Rate_Limiter::retried_too_soon( $rate_limit_key ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Please wait at least 1 minute before trying to configure webhooks again.', 'woocommerce-gateway-stripe' ) ], 400 );
		}

		WC_Rate_Limiter::set_rate_limit( $rate_limit_key, 60 );

		$settings     = WC_Stripe_Helper::get_stripe_settings();
		$secret       = wc_clean( wp_unslash( $request->get_param( 'secret' ) ) );
		$saved_secret = $settings[ $live_mode ? 'secret_key' : 'test_secret_key' ];

		// If the secret received is masked and it matches the saved secret, use the raw saved secret.
		if ( $secret === $this->mask_key_value( $saved_secret ) ) {
			$secret = $saved_secret;
		}

		try {
			$response = $this->account->configure_webhooks( $environment, $secret );
		} catch ( Exception $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 400 );
		}

		return new WP_REST_Response(
			[
				'message'       => __( 'Webhooks have been setup successfully.', 'woocommerce-gateway-stripe' ),
				'webhookURL'    => rawurlencode( $response->url ),
				'webhookSecret' => $this->mask_key_value( $response->secret ),
			]
		);
	}

	/**
	 * Decommissions the configured Webhook if the user is removing their secret key.
	 * This is to avoid leaving orphaned Webhooks in the Stripe account.
	 *
	 * @param array $settings             The current settings.
	 * @param array $current_account_keys The current account keys.
	 *
	 * @return array The updated settings. The webhook data will be removed if the webhook was decommissioned.
	 */
	private function decommission_configured_webhook_after_key_update( $settings, $current_account_keys ) {
		$key_data = [
			'live' => [
				'secret_key'     => $settings['secret_key'] ?? '',
				'webhook_data'   => $settings['webhook_data'] ?? '',
				'current_secret' => $current_account_keys['secret_key'] ?? '',
			],
			'test' => [
				'secret_key'     => $settings['test_secret_key'] ?? '',
				'webhook_data'   => $settings['test_webhook_data'] ?? '',
				'current_secret' => $current_account_keys['test_secret_key'] ?? '',
			],
		];

		foreach ( $key_data as $mode => $keys ) {
			// If there's no webhook ID or secret key, we can skip.
			if ( empty( $keys['webhook_data'] ) || empty( $keys['current_secret'] ) ) {
				continue;
			}

			// If the user is removing or changing their secret key, we should decommission the webhook.
			if ( empty( $keys['secret_key'] ) || $keys['secret_key'] !== $keys['webhook_data']['secret'] ) {
				// Set the appropriate secret key to the mode (live vs test) so we can send the request.
				WC_Stripe_API::set_secret_key( $keys['webhook_data']['secret'] );
				WC_Stripe_API::request( [], 'webhook_endpoints/' . $keys['webhook_data']['id'], 'DELETE' );

				// Update the webhook settings now that the webhook has been decommissioned.
				$settings[ 'live' === $mode ? 'webhook_data' : 'test_webhook_data' ]     = [];
				$settings[ 'live' === $mode ? 'webhook_secret' : 'test_webhook_secret' ] = '';
			}
		}

		return $settings;
	}

	/**
	 * Records a track event when the keys of an account are manually removed (account disconnected).
	 *
	 * @param bool $is_test_mode Whether the keys are test ones.
	 */
	private function record_manual_account_disconnect_track_event( bool $is_test_mode ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		wc_admin_record_tracks_event( 'wcstripe_stripe_disconnected', [ 'is_test_mode' => $is_test_mode ] );
	}

	/**
	 * Records a track event when the keys of an account are manually updated.
	 *
	 * @param bool $is_test_mode Whether the keys are test ones.
	 */
	private function record_manual_account_key_update_track_event( bool $is_test_mode ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		wc_admin_record_tracks_event( 'wcstripe_stripe_keys_updated', [ 'is_test_mode' => $is_test_mode ] );
	}
}
