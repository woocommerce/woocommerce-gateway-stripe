<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wc-stripe-remote-config-flags.php';

/**
 * Outbound HTTP client for the Stripe remote-config endpoint.
 *
 * Built directly on `wp_remote_get` to avoid entering any plugin-specific
 * request-mutation filter chain. In particular, this client does NOT reuse
 * `WC_Stripe_Connect_API`, whose `wc_connect_server_url`,
 * `wc_connect_request_args`, and `wc_connect_api_client_body` filters could
 * otherwise be hooked by third parties to re-target the URL or disable TLS
 * verification.
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
	 * Fetches the current remote-config payload for the given mode.
	 *
	 * @param string $mode 'live' or 'test'.
	 *
	 * @return array|WP_Error Decoded JSON array on success, WP_Error on failure
	 *                        (including opt-out short-circuit).
	 */
	public function fetch( string $mode ) {
		if ( ! WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() ) {
			return new WP_Error(
				'wc_stripe_remote_config_disabled',
				'Remote config is disabled via constant or filter.'
			);
		}

		$url = add_query_arg(
			[
				'mode'           => $mode,
				'plugin_version' => WC_STRIPE_VERSION,
			],
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
		if ( strlen( $body ) > WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES ) {
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
}
