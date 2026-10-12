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
	 *
	 * @return void
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
	 * @param mixed           $param            The parameter value to validate.
	 * @param WP_REST_Request $request          The request object.
	 * @param string          $key              The parameter key.
	 * @param array           $validate_options Validation options with 'regex' and 'error_message'.
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

	/**
	 * Validates a publishable key.
	 *
	 * @param mixed           $param The parameter value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $key The parameter key.
	 * @return true|WP_Error
	 */
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

	/**
	 * Validates a secret key.
	 *
	 * @param mixed           $param The parameter value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $key The parameter key.
	 * @return true|WP_Error
	 */
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

	/**
	 * Validates a test publishable key.
	 *
	 * @param mixed           $param   The parameter value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $key     The parameter key.
	 * @return true|WP_Error
	 */
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

	/**
	 * Validates a test secret key.
	 *
	 * @param mixed           $param   The parameter value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $key     The parameter key.
	 * @return true|WP_Error
	 */
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
	 * @return WP_REST_Response
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
			// Ensure we record the disconnect before we overwrite any settings.
			$this->record_manual_account_keys_tracks_event( 'wcstripe_stripe_disconnected', WC_Stripe_Mode::is_test() ? 'test' : 'live', $settings );

			$settings['enabled']              = 'no';
			$settings['connection_type']      = '';
			$settings['pmc_enabled']          = '';
			$settings['test_connection_type'] = '';
			$settings['refresh_token']        = '';
			$settings['test_refresh_token']   = '';
		}

		// Before saving the settings, decommission any previously automatically configured webhook endpoint.
		$settings = $this->decommission_configured_webhook_after_key_update( $settings, $current_account_keys );

		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// Disable all payment methods if all keys are different from the current ones
		if ( $current_account_keys['publishable_key'] !== $settings['publishable_key']
			|| $current_account_keys['secret_key'] !== $settings['secret_key']
			|| $current_account_keys['test_publishable_key'] !== $settings['test_publishable_key']
			|| $current_account_keys['test_secret_key'] !== $settings['test_secret_key'] ) {

			$upe_gateway  = new WC_Stripe_UPE_Payment_Gateway();
			$reset_result = $upe_gateway->update_enabled_payment_methods( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ] );
			if ( is_wp_error( $reset_result ) ) {
				WC_Stripe_Logger::error(
					'Failed to reset payment methods after API key change',
					[ 'error' => $reset_result->get_error_message() ]
				);
			}

			WC_Stripe::get_instance()->connect->clear_caches_after_key_update();
		}

		$this->account->clear_cache();

		// Gives an instant reply if the connection was successful or not + rebuild the cache for the next request
		$account = $this->account->get_cached_account_data();

		// Record separate events for updated live and test keys after we have the keys in the database.
		if ( ! $is_deleting_account ) {
			foreach ( $this->get_modes_with_changed_keys( $current_account_keys, $settings ) as $mode ) {
				$this->record_manual_account_keys_tracks_event( 'wcstripe_stripe_keys_updated', $mode, $settings );
			}
		}

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
	 * @return WP_REST_Response
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

		// Use wp_remote_post() instead of wp_safe_remote_post() as we have a hard-coded URL
		// and the safe version fails when there are DNS resolution issues.
		// See https://github.com/woocommerce/woocommerce-gateway-stripe/issues/4801
		$response = wp_remote_post(
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

		// Use wp_remote_get() instead of wp_safe_remote_get() as we have a hard-coded URL
		// and the safe version fails when there are DNS resolution issues.
		// See https://github.com/woocommerce/woocommerce-gateway-stripe/issues/4801
		$response = wp_remote_get(
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
	 * @return WP_REST_Response
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

		try {
			$response = $this->account->configure_webhooks( $environment );
		} catch ( Exception $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 400 );
		} finally {
			// Ensure we reset the key before we do anything else.
			WC_Stripe_API::set_secret_key( '' );
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

			// If the user is removing or changing their secret key, decommission the
			// webhook on the previously connected account to avoid leaving orphaned webhooks in Stripe.
			if ( $this->account->maybe_decommission_webhook( $keys['webhook_data'], $keys['secret_key'] ) ) {
				// Update the webhook settings now that the webhook has been decommissioned.
				$settings[ 'live' === $mode ? 'webhook_data' : 'test_webhook_data' ]     = [];
				$settings[ 'live' === $mode ? 'webhook_secret' : 'test_webhook_secret' ] = '';
			}
		}

		return $settings;
	}

	/**
	 * Whether a Tracks event recorded now would actually be sent.
	 *
	 * Checks whether the WooCommerce wc_admin_record_tracks_event() function is available
	 * and whether WooCommerce usage tracking is enabled. This allows us to skip lookups and
	 * other expensive operations when usage tracking is disabled.
	 *
	 * @return bool
	 */
	private function can_record_tracks_events(): bool {
		return function_exists( 'wc_admin_record_tracks_event' )
			&& class_exists( 'WC_Site_Tracking' )
			&& method_exists( WC_Site_Tracking::class, 'is_tracking_enabled' ) // @phpstan-ignore function.alreadyNarrowedType (Defensive check to make sure the function is available in WooCommerce.)
			&& WC_Site_Tracking::is_tracking_enabled();
	}

	/**
	 * Returns the modes whose publishable or secret key differs between two key sets.
	 *
	 * Falls back to the active mode when neither mode's keys changed, e.g. when only a webhook
	 * secret was submitted, so the keys-updated event still describes the account in use.
	 *
	 * @param array $keys_before Key settings before the update.
	 * @param array $keys_after  Key settings after the update.
	 * @return string[] Any of 'live' and 'test'.
	 */
	private function get_modes_with_changed_keys( array $keys_before, array $keys_after ): array {
		$modes = [];

		foreach ( [
			'live' => '',
			'test' => 'test_',
		] as $mode => $prefix ) {
			foreach ( [ 'publishable_key', 'secret_key' ] as $key ) {
				if ( ( $keys_before[ $prefix . $key ] ?? '' ) !== ( $keys_after[ $prefix . $key ] ?? '' ) ) {
					$modes[] = $mode;
					break;
				}
			}
		}

		if ( [] !== $modes ) {
			return $modes;
		}

		return [ WC_Stripe_Mode::is_test() ? 'test' : 'live' ];
	}

	/**
	 * Records a Tracks event for a manual account keys change, describing the given mode: whether
	 * it is test mode, its connection type as stored in $settings, and the account its keys
	 * currently resolve to.
	 *
	 * @param string $event_name 'wcstripe_stripe_disconnected' when the keys were removed, 'wcstripe_stripe_keys_updated' otherwise.
	 * @param string $mode       The mode the event is about, 'live' or 'test'.
	 * @param array  $settings   The Stripe settings holding the connection type for the mode.
	 * @return void
	 */
	private function record_manual_account_keys_tracks_event( string $event_name, string $mode, array $settings ) {
		if ( ! $this->can_record_tracks_events() ) {
			return;
		}

		$connection_type = $settings[ 'test' === $mode ? 'test_connection_type' : 'connection_type' ] ?? '';

		$event_props = [
			'is_test_mode'    => 'test' === $mode,
			'connection_type' => is_string( $connection_type ) ? $connection_type : '',
			'stripe_version'  => WC_STRIPE_VERSION,
		];

		$account_data = $this->account->get_cached_account_data( $mode );
		if ( is_string( $account_data['id'] ?? null ) ) {
			$event_props['account_id'] = $account_data['id'];
		}

		wc_admin_record_tracks_event( $event_name, $event_props );
	}
}
