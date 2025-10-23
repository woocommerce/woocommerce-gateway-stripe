<?php

/**
 * Class WC_Stripe_Transact_Account_Manager.
 *
 * Handles transact account management for WooCommerce Stripe integration.
 */
class WC_Stripe_Transact_Account_Manager {
	/**
	 * The API version for the proxy endpoint.
	 *
	 * @var int
	 */
	private const WPCOM_PROXY_ENDPOINT_API_VERSION = 2;

	/**
	 * The timeout for requests to the WPCOM proxy endpoint.
	 *
	 * @var int
	 */
	private const WPCOM_PROXY_REQUEST_TIMEOUT = 60;

	/**
	 * Transact provider type, for provider onboarding.
	 *
	 * @var string
	 */
	private const TRANSACT_PROVIDER_TYPE = 'wc_stripe';

	/**
	 * Cache keys for the merchant and provider accounts.
	 *
	 * @var string
	 */
	private const TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY_LIVE = 'transact_merchant_account_live';
	private const TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY_TEST = 'transact_merchant_account_test';
	private const TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY_LIVE = 'transact_provider_account_live';
	private const TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY_TEST = 'transact_provider_account_test';

	/**
	 * The expiry time for the Transact account cache.
	 *
	 * @var int
	 */
	private const TRANSACT_ACCOUNT_CACHE_EXPIRY = 60 * 60 * 24; // 24 hours.

	/**
	 * Stripe gateway object.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Singleton instance of the class.
	 *
	 * @var null|WC_Stripe_Transact_Account_Manager
	 */
	private static ?WC_Stripe_Transact_Account_Manager $instance = null;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_UPE_Payment_Gateway $gateway Stripe gateway instance.
	 */
	public function __construct( WC_Stripe_UPE_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Gets the singleton instance of the class.
	 *
	 * @param WC_Stripe_UPE_Payment_Gateway $gateway Stripe gateway instance.
	 * @return WC_Stripe_Transact_Account_Manager|null
	 */
	public static function get_instance( WC_Stripe_UPE_Payment_Gateway $gateway ): ?self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $gateway );
		}

		return self::$instance;
	}

	/**
	 * Sets the singleton instance of the class.
	 *
	 * @param WC_Stripe_Transact_Account_Manager|null $instance
	 * @return void
	 */
	public static function set_instance( ?self $instance ) {
		self::$instance = $instance;
	}

	/**
	 * Onboard the merchant with the Transact platform.
	 *
	 * @return void
	 */
	public function do_onboarding(): void {
		$stripe_connect  = woocommerce_gateway_stripe()->connect;
		$mode            = WC_Stripe_Mode::is_test() ? 'test' : 'live';
		$oauth_connected = (bool) $stripe_connect->is_connected_via_oauth( $mode );

		// Check that the merchant is connected via OAuth. Only begin onboarding if this minimum requirement is met.
		if ( ! $oauth_connected ) {
			return;
		}

		// Register with Jetpack if not already connected.
		$jetpack_connection_manager = $this->gateway->get_jetpack_connection_manager();
		if ( ! $jetpack_connection_manager ) {
			WC_Stripe_Logger::error( 'Jetpack connection manager not found.' );
			return;
		}

		if ( ! $jetpack_connection_manager->is_connected() ) {
			$result = $jetpack_connection_manager->try_registration();
			if ( is_wp_error( $result ) ) {
				WC_Stripe_Logger::error( 'Jetpack registration failed: ' . $result->get_error_message() );
				return;
			}
		}

		// Fetch (cached) or create the Transact merchant and provider accounts.
		$merchant_account_data = $this->get_transact_account_data( 'merchant' );
		if ( empty( $merchant_account_data ) ) {
			$merchant_account = $this->create_merchant_account();
			if ( empty( $merchant_account ) ) {
				WC_Stripe_Logger::error( 'Transact merchant onboarding failed.' );
				return;
			}

			// Cache the merchant account data.
			$this->update_transact_account_cache(
				$this->get_cache_key( 'merchant' ),
				$merchant_account
			);
		}

		wc_get_logger()->info( 'merchant_account_data: ' . wc_print_r( $merchant_account_data, true ) );

		$provider_account_data = $this->get_transact_account_data( 'provider' );
		if ( empty( $provider_account_data ) ) {
			$provider_account = $this->create_provider_account();
			if ( ! $provider_account ) {
				WC_Stripe_Logger::error( 'Transact provider onboarding failed.' );
				return;
			}

			// Cache the provider account data.
			$this->update_transact_account_cache(
				$this->get_cache_key( 'provider' ),
				$provider_account
			);
		}

		wc_get_logger()->info( 'provider_account_data: ' . wc_print_r( $provider_account_data, true ) );

		// Set an extra flag to indicate that we've completed onboarding.
		$this->gateway->set_transact_onboarding_complete();
	}

	/**
	 * Get the Transact account (merchant or provider) data. Performs a fetch if the account
	 * is not in cache or expired.
	 *
	 * @param string $account_type The type of account to get (merchant or provider).
	 * @return array|bool|null Returns null if the transact account cannot be retrieved.
	 */
	public function get_transact_account_data( $account_type ) {
		$cache_key = $this->get_cache_key( $account_type );

		// Get transact account from cache. If not found, fetch/create it.
		$transact_account = $this->get_transact_account_from_cache( $cache_key );
		if ( empty( $transact_account ) ) {
			$transact_account = 'merchant' === $account_type ? $this->fetch_merchant_account() : $this->fetch_provider_account();

			// Fetch failed.
			if ( empty( $transact_account ) ) {
				return null;
			}

			// Update cache.
			$this->update_transact_account_cache( $cache_key, $transact_account );
		}

		return $transact_account;
	}

	/**
	 * Get the cache key for the transact account.
	 *
	 * @param string $account_type The type of account to get (merchant or provider).
	 * @return string|null The cache key, or null if the account type is invalid.
	 */
	private function get_cache_key( $account_type ): ?string {
		if ( 'merchant' === $account_type ) {
			return WC_Stripe_Mode::is_test() ? self::TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY_TEST : self::TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY_LIVE;
		}

		if ( 'provider' === $account_type ) {
			return WC_Stripe_Mode::is_test() ? self::TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY_TEST : self::TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY_LIVE;
		}

		return null;
	}

	/**
	 * Fetch the merchant account from the Transact platform.
	 *
	 * @return array|null The API response body, or null if the request fails.
	 */
	private function fetch_merchant_account(): ?array {
		$site_id = \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return null;
		}

		$request_body = [
			'test_mode' => WC_Stripe_Mode::is_test(),
		];

		$response = $this->send_transact_api_request(
			'GET',
			sprintf( '/sites/%d/transact/account', $site_id ),
			$request_body
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $response_data['public_id'] ) ) {
			return null;
		}

		return [ 'public_id' => $response_data['public_id'] ];
	}

	/**
	 * Fetch the provider account from the Transact platform.
	 *
	 * @return bool True if the provider account exists, false otherwise.
	 */
	private function fetch_provider_account(): bool {
		$site_id = \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return false;
		}

		$request_body = [
			'test_mode'     => WC_Stripe_Mode::is_test(),
			'provider_type' => self::TRANSACT_PROVIDER_TYPE,
		];

		$response = $this->send_transact_api_request(
			'GET',
			sprintf( '/sites/%d/transact/account/%s', $site_id, self::TRANSACT_PROVIDER_TYPE ),
			$request_body
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		// Provider account response only returns an empty onboarding link,
		// which we do not need.
		return true;
	}

	/**
	 * Create the merchant account with the Transact platform.
	 *
	 * @return array|null The API response body, or null if the request fails.
	 */
	private function create_merchant_account(): ?array {
		$site_id = \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return null;
		}

		$request_body = [ 'test_mode' => WC_Stripe_Mode::is_test() ];

		$response = $this->send_transact_api_request(
			'POST',
			sprintf( '/sites/%d/transact/account', $site_id ),
			$request_body
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $response_data['public_id'] ) ) {
			WC_Stripe_Logger::error( 'Transact merchant account creation failed. Response body: ' . wc_print_r( $response_data, true ) );
			return null;
		}

		return [ 'public_id' => $response_data['public_id'] ];
	}

	/**
	 * Create the provider account with the Transact platform.
	 *
	 * @return bool True if the provider account creation was successful, false otherwise.
	 */
	private function create_provider_account(): bool {
		$site_id = \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return false;
		}

		$request_body = [
			'test_mode'     => WC_Stripe_Mode::is_test(),
			'provider_type' => self::TRANSACT_PROVIDER_TYPE,
		];
		$response     = $this->send_transact_api_request(
			'POST',
			sprintf( '/sites/%d/transact/account/%s/onboard', $site_id, self::TRANSACT_PROVIDER_TYPE ),
			$request_body
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		// Provider account response only returns an empty onboarding link,
		// which we do not need.
		return true;
	}

	/**
	 * Update the transact account (merchant or provider) cache.
	 *
	 * @param string $cache_key The cache key to update.
	 * @param array  $account_data The transact account data.
	 */
	private function update_transact_account_cache( $cache_key, $account_data ): void {
		$expires = time() + self::TRANSACT_ACCOUNT_CACHE_EXPIRY;
		WC_Stripe_Database_Cache::set(
			$cache_key,
			[
				'account' => $account_data,
				'expiry'  => $expires,
			],
			self::TRANSACT_ACCOUNT_CACHE_EXPIRY
		);
	}

	/**
	 * Get the transact account (merchant or provider) from the database cache.
	 *
	 * @param string $cache_key The cache key to get the account.
	 * @return array|bool|null The transact account data, or null if the cache is
	 *                    empty or expired.
	 */
	private function get_transact_account_from_cache( $cache_key ) {
		$transact_account = WC_Stripe_Database_Cache::get( $cache_key );

		if ( empty( $transact_account ) || ( isset( $transact_account['expiry'] ) && $transact_account['expiry'] < time() ) ) {
			return null;
		}

		return $transact_account['account'] ?? null;
	}

	/**
	 * Send a request to the Transact platform.
	 *
	 * @param string $method The HTTP method to use.
	 * @param string $endpoint The endpoint to request.
	 * @param array  $request_body The request body.
	 *
	 * @return array|null The API response body, or null if the request fails.
	 */
	private function send_transact_api_request( $method, $endpoint, $request_body ) {
		if ( 'GET' === $method ) {
			$endpoint .= '?' . http_build_query( $request_body );
		}

		$response = \Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_blog(
			$endpoint,
			self::WPCOM_PROXY_ENDPOINT_API_VERSION,
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'method'  => $method,
				'timeout' => self::WPCOM_PROXY_REQUEST_TIMEOUT,
			],
			'GET' === $method ? null : wp_json_encode( $request_body ),
			'wpcom'
		);

		return $response;
	}
}
