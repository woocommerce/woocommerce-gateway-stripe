<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outbound HTTP client for the Stripe remote-config endpoint.
 */
class WC_Stripe_Remote_Config_Client {

	/**
	 * WPCOM endpoint.
	 */
	private const BASE_URL = 'https://public-api.wordpress.com';

	/**
	 * Endpoint path, appended to BASE_URL.
	 */
	private const PATH = '/wpcom/v2/woocommerce/stripe/remote-config';

	/**
	 * Request timeout in seconds.
	 */
	private const TIMEOUT = 10;

	/**
	 * Largest response body we decode, in bytes.
	 *
	 * Only a coarse guard against decoding a huge body. The exact per-mode
	 * limit is checked in WC_Stripe_Remote_Config::apply(). This bound must
	 * stay well above two full-size payloads, because the wire body also holds
	 * the envelope keys and its JSON escaping can differ from ours.
	 */
	private const MAX_RESPONSE_BYTES = 4 * WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES;

	/**
	 * Fetches the combined remote-config envelope covering both modes.
	 *
	 * `mode=all` returns `{ modes: { live: <envelope>, test: <envelope> }, generated_at }`,
	 * where each per-mode envelope is byte-for-byte the single-mode response shape.
	 *
	 * @return array|WP_Error Decoded JSON array on success, WP_Error on failure
	 *                        (including the disabled short-circuit).
	 */
	public function fetch_all() {
		if ( ! WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() ) {
			return new WP_Error(
				'wc_stripe_remote_config_disabled',
				'Remote config is disabled on this site.'
			);
		}

		$url = add_query_arg(
			$this->build_query_args(),
			self::BASE_URL . self::PATH
		);

		$response = wp_remote_get(
			$url,
			[
				'method'    => 'GET',
				'timeout'   => self::TIMEOUT,
				'sslverify' => true,
				'headers'   => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error(
				'wc_stripe_remote_config_http_error',
				sprintf( 'Unexpected HTTP status %d from remote-config endpoint.', $status ),
				[ 'status' => $status ]
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			return new WP_Error(
				'wc_stripe_remote_config_payload_too_large',
				'Remote-config payload exceeds maximum allowed size.'
			);
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'wc_stripe_remote_config_invalid_json',
				'Remote-config response is not a JSON object.'
			);
		}

		return $decoded;
	}

	/**
	 * Builds the query arguments for the combined `mode=all` request.
	 *
	 * Mode-independent signals travel unprefixed; the account country can
	 * differ between a dual-keyed store's live and test accounts, so it is
	 * sent per mode under the prefixed names the endpoint contract reserves
	 * for diverging params (`live_account_country` / `test_account_country`).
	 * Empty values are dropped from the request entirely.
	 *
	 * @return array<string, string>
	 */
	private function build_query_args(): array {
		$args = [
			'mode'                  => 'all',
			'plugin_version'        => WC_STRIPE_VERSION,
			'wc_version'            => WC_VERSION,
			'live_account_country'  => $this->get_account_country( 'live' ),
			'test_account_country'  => $this->get_account_country( 'test' ),
			'store_currency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'subscriptions_enabled' => $this->bool_param( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ),
			'pre_orders_enabled'    => $this->bool_param( class_exists( 'WC_Pre_Orders' ) ),
		];

		return array_filter(
			$args,
			static function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Reads the account country from the mode-prefixed cache.
	 *
	 * @param string $mode 'live' or 'test'.
	 */
	private function get_account_country( string $mode ): string {
		$cached = WC_Stripe_Database_Cache::get_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $mode );
		if ( ! is_array( $cached ) || empty( $cached['country'] ) ) {
			return '';
		}

		return (string) $cached['country'];
	}

	private function bool_param( bool $value ): string {
		return $value ? '1' : '0';
	}
}
