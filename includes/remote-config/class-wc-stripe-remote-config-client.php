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
			[
				'mode'           => 'all',
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
		// The combined envelope carries one payload per mode; each is validated
		// against MAX_PAYLOAD_BYTES individually in WC_Stripe_Remote_Config::apply(),
		// so the wire-level bound is twice the per-mode cap.
		if ( strlen( $body ) > 2 * WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES ) {
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
