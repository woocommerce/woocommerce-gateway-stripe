<?php

/**
 * Class WC_Stripe_Transact_Account_Manager.
 *
 * Handles transact account management for WooCommerce Stripe integration.
 */
final class WC_Stripe_Transact_Account_Manager {

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
	private const TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY = 'transact_merchant_account';
	private const TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY = 'transact_provider_account';

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
	 * Constructor.
	 *
	 * @param WC_Stripe_UPE_Payment_Gateway $gateway Stripe gateway instance.
	 */
	public function __construct( WC_Stripe_UPE_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Onboard the merchant with the Transact platform.
	 *
	 * @return void
	 */
	public function do_onboarding(): void {
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

		// Fetch (cached) or create the Transact merchant account.
		$merchant_account_data = $this->maybe_create_merchant_account();
		wc_get_logger()->info( 'merchant_account_data: ' . wc_print_r( $merchant_account_data, true ) );
		if ( empty( $merchant_account_data ) ) {
			WC_Stripe_Logger::error( 'Transact merchant onboarding failed.' );
			return;
		}

		// Fetch (cached) or create the Transact provider account.
		$provider_account_data = $this->maybe_create_provider_account();
		wc_get_logger()->info( 'provider_account_data: ' . wc_print_r( $provider_account_data, true ) );
		if ( empty( $provider_account_data ) ) {
			WC_Stripe_Logger::error( 'Transact provider onboarding failed.' );
			return;
		}

		// Set an extra flag to indicate that we've completed onboarding.
		$this->gateway->set_transact_onboarding_complete();
	}

	/**
	 * Maybe create the merchant account.
	 *
	 * @return array|null The merchant account data, or null if the merchant account cannot be created.
	 */
	private function maybe_create_merchant_account(): ?array {
		// Get the merchant account from cache.
		$merchant_account = WC_Stripe_Database_Cache::get( self::TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY );
		if ( ! empty( $merchant_account ) ) {
			return $merchant_account;
		}

		// Fetch the merchant account from the Transact platform.
		$merchant_account = $this->fetch_merchant_account();
		if ( empty( $merchant_account ) ) {
			// Create the merchant account on the Transact platform.
			$merchant_account = $this->create_merchant_account();
		}

		// Cache the merchant account data.
		WC_Stripe_Database_Cache::set(
			self::TRANSACT_MERCHANT_ACCOUNT_CACHE_KEY,
			$merchant_account,
			self::TRANSACT_ACCOUNT_CACHE_EXPIRY
		);

		return $merchant_account;
	}

	/**
	 * Maybe create the provider account.
	 *
	 * @return bool|null Ture if the provider account is valid. Provider account response only returns an empty onboarding link, so we map it to true.
	 */
	private function maybe_create_provider_account(): ?bool {
		// Get the provider account from cache.
		$provider_account = WC_Stripe_Database_Cache::get( self::TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY );
		if ( ! empty( $provider_account ) ) {
			return $provider_account;
		}

		// Fetch the provider account from the Transact platform.
		$provider_account = $this->fetch_provider_account();
		if ( empty( $provider_account ) ) {
			// Create the provider account on the Transact platform.
			$provider_account = $this->create_provider_account();
		}

		// Cache the merchant account data.
		WC_Stripe_Database_Cache::set(
			self::TRANSACT_PROVIDER_ACCOUNT_CACHE_KEY,
			$provider_account,
			self::TRANSACT_ACCOUNT_CACHE_EXPIRY
		);

		return $provider_account;
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
			WC_Stripe_Transact_API::WPCOM_PROXY_ENDPOINT_API_VERSION,
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'method'  => $method,
				'timeout' => WC_Stripe_Transact_API::WPCOM_PROXY_REQUEST_TIMEOUT,
			],
			'GET' === $method ? null : wp_json_encode( $request_body ),
			'wpcom'
		);

		return $response;
	}
}
