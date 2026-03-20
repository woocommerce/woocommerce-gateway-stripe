<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress HTTP transport adapter for the Stripe PHP SDK.
 *
 * Implements \Stripe\HttpClient\ClientInterface so that the Stripe SDK routes
 * all HTTP traffic through the WordPress HTTP API (wp_safe_remote_request).
 * This ensures WordPress proxy settings, SSL configuration, and request
 * filters are respected for every Stripe API call made via the SDK.
 */
class WC_Stripe_WP_HTTP_Client implements \Stripe\HttpClient\ClientInterface {

	/**
	 * Execute an HTTP request on behalf of the Stripe SDK.
	 *
	 * @param string   $method    HTTP method (get, post, delete, …).
	 * @param string   $abs_url   Fully-qualified request URL.
	 * @param string[] $headers   Headers as "Name: value" strings (Stripe SDK format).
	 * @param array    $params    Request parameters.
	 * @param bool     $has_file  Whether the request contains a file upload.
	 * @param string   $api_mode  API mode ('v1' or 'v2').
	 *
	 * @return array{0: string, 1: int, 2: array<string, string>} Tuple of
	 *                                                             [body, status_code, headers].
	 * @throws \Stripe\Exception\ApiConnectionException On transport failure or unsupported file upload.
	 */
	public function request( $method, $abs_url, $headers, $params, $has_file, $api_mode = 'v1' ) {
		$method = strtoupper( $method );

		if ( $has_file ) {
			// Multipart file uploads are handled directly via cURL in
			// class-wc-stripe-agentic-commerce-files-api-delivery.php and are
			// never routed through the SDK client, so this path should not be
			// reached in normal operation.
			throw new \Stripe\Exception\ApiConnectionException(
				'Multipart file uploads are not supported through the WordPress HTTP client adapter. Use the dedicated file upload implementation instead.'
			);
		}

		$args = [
			'method'  => $method,
			'headers' => $this->parse_headers( $headers ),
			'timeout' => 70,
		];

		if ( 'GET' === $method ) {
			if ( ! empty( $params ) ) {
				$abs_url = add_query_arg( $params, $abs_url );
			}
		} else {
			$args['body'] = $params;
		}

		$response = wp_safe_remote_request( $abs_url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Stripe\Exception\ApiConnectionException(
				'Error connecting to Stripe API: ' . $response->get_error_message()
			);
		}

		$rbody    = wp_remote_retrieve_body( $response );
		$rcode    = (int) wp_remote_retrieve_response_code( $response );
		$rheaders = [];

		foreach ( wp_remote_retrieve_headers( $response ) as $key => $value ) {
			$rheaders[ $key ] = $value;
		}

		return [ $rbody, $rcode, $rheaders ];
	}

	/**
	 * Convert Stripe SDK-style headers ("Name: value" strings) into the
	 * associative array format expected by the WordPress HTTP API.
	 *
	 * @param string[] $headers
	 * @return array<string, string>
	 */
	private function parse_headers( array $headers ): array {
		$parsed = [];
		foreach ( $headers as $header ) {
			$pos = strpos( $header, ': ' );
			if ( false !== $pos ) {
				$parsed[ substr( $header, 0, $pos ) ] = substr( $header, $pos + 2 );
			}
		}
		return $parsed;
	}
}
