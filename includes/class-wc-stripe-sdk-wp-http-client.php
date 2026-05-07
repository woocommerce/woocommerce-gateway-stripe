<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe PHP SDK HTTP transport that routes through WordPress' HTTP API.
 *
 * The default `\Stripe\HttpClient\CurlClient` uses curl directly. That bypasses
 * `wp_safe_remote_*`, the `pre_http_request` filter the WP test framework relies
 * on, and any third-party rewrites added to the WP HTTP layer. This adapter
 * implements `\Stripe\HttpClient\ClientInterface` on top of `wp_safe_remote_request`
 * so SDK calls behave like every other HTTP call the plugin makes.
 *
 * Registered globally via `\Stripe\ApiRequestor::setHttpClient()` from
 * {@see WC_Stripe_Client::get()}.
 *
 * @since 10.7.0
 */
class WC_Stripe_SDK_WP_Http_Client implements \Stripe\HttpClient\ClientInterface {

	/**
	 * Default request timeout (seconds). Matches `WC_Stripe_Client::request()`.
	 */
	private const REQUEST_TIMEOUT = 70;

	/**
	 * Performs an HTTP request on behalf of the Stripe SDK.
	 *
	 * @param 'delete'|'get'|'post' $method  HTTP method (lowercase, per SDK contract).
	 * @param string                $absUrl  Fully qualified request URL.
	 * @param array<int,string>     $headers Headers as full "Header: value" strings.
	 * @param array<string,mixed>   $params  Request parameters.
	 * @param bool                  $hasFile Whether `$params` references a file upload.
	 * @param 'v1'|'v2'             $apiMode Stripe API mode — affects POST body encoding.
	 * @param null|int              $maxNetworkRetries Honored by the SDK at a higher layer; we don't retry here (matches `WC_Stripe_Client::request()`).
	 *
	 * @return array{0:string,1:int,2:array<string,string>} `[ body, status, headers ]`.
	 *
	 * @throws \Stripe\Exception\ApiConnectionException When the request never reaches Stripe.
	 * @throws \Stripe\Exception\UnexpectedValueException When asked to upload a file (not yet supported by this adapter).
	 */
	// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Argument names must match \Stripe\HttpClient\ClientInterface::request().
	public function request( $method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null ) {
		if ( $hasFile ) {
			throw new \Stripe\Exception\UnexpectedValueException( 'WC_Stripe_SDK_WP_Http_Client does not support file uploads.' );
		}

		$method = strtolower( $method );

		[ $url, $body, $extra_headers ] = $this->build_url_and_body( $method, $absUrl, $params, $apiMode );

		$assoc_headers = array_merge( $this->normalize_headers( $headers ), $extra_headers );

		$assoc_headers = apply_filters_deprecated(
			'woocommerce_stripe_request_headers',
			[ $assoc_headers ],
			'9.7.0',
			'wc_stripe_request_headers',
			'The woocommerce_stripe_request_headers filter is deprecated since WooCommerce Stripe Gateway 9.7.0, and will be removed in a future version. Use wc_stripe_request_headers instead.'
		);

		/** This filter is documented in includes/class-wc-stripe-api.php */
		$assoc_headers = apply_filters( 'wc_stripe_request_headers', $assoc_headers );

		$args = [
			'method'    => strtoupper( $method ),
			'headers'   => $assoc_headers,
			'timeout'   => self::REQUEST_TIMEOUT,
			'sslverify' => true,
		];

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Stripe\Exception\ApiConnectionException(
				sprintf( 'Stripe request failed via WordPress HTTP: %s', $response->get_error_message() )
			);
		}

		$status   = $this->extract_status_code( $response );
		$body_str = (string) wp_remote_retrieve_body( $response );

		return [ $body_str, $status, $this->collapse_headers( $response ) ];
	}

	/**
	 * Pulls the HTTP status code from the response array.
	 *
	 * `wp_remote_retrieve_response_code()` only handles the canonical shape
	 * (`'response' => ['code' => 200, 'message' => 'OK']`). Some `pre_http_request`
	 * mocks in this codebase return `'response' => 200` directly, which the
	 * canonical helper turns into `0`. Tolerate both so SDK calls behave the
	 * same as `WC_Stripe_API::request()` did under those mocks.
	 *
	 * @param array|\WP_Error $response
	 */
	private function extract_status_code( $response ): int {
		if ( ! is_array( $response ) || ! isset( $response['response'] ) ) {
			return 0;
		}
		$code = $response['response'];
		if ( is_array( $code ) ) {
			$code = $code['code'] ?? 0;
		}
		return (int) $code;
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName

	/**
	 * Mirrors `\Stripe\HttpClient\CurlClient::constructUrlAndBody()` for the
	 * combinations we care about: form-encoded POST/DELETE bodies for v1,
	 * JSON for v2, and query-string params for GET.
	 *
	 * @param array<string,mixed> $params
	 * @return array{0:string,1:null|string|array<string,mixed>,2:array<string,string>}
	 */
	private function build_url_and_body( string $method, string $abs_url, array $params, string $api_mode ): array {
		$params = \Stripe\Util\Util::objectsToIds( $params );

		if ( 'post' === $method ) {
			if ( 'v2' === $api_mode ) {
				$body = empty( $params ) ? null : (string) wp_json_encode( $params );
				return [ $abs_url, $body, [ 'Content-Type' => 'application/json' ] ];
			}

			return [ $abs_url, $params, [] ];
		}

		// GET / DELETE — params go on the query string.
		if ( ! empty( $params ) ) {
			$encoded = \Stripe\Util\Util::encodeParameters( $params, $api_mode );
			$abs_url = $abs_url . ( false === strpos( $abs_url, '?' ) ? '?' : '&' ) . $encoded;
		}

		return [ $abs_url, null, [] ];
	}

	/**
	 * Converts the SDK's `[ "Header: value", … ]` array into the assoc form
	 * `wp_safe_remote_request()` expects.
	 *
	 * @param array<int,string> $headers
	 * @return array<string,string>
	 */
	private function normalize_headers( array $headers ): array {
		$normalized = [];
		foreach ( $headers as $line ) {
			$colon = strpos( $line, ':' );
			if ( false === $colon ) {
				continue;
			}
			$name  = trim( substr( $line, 0, $colon ) );
			$value = trim( substr( $line, $colon + 1 ) );
			if ( '' !== $name ) {
				$normalized[ $name ] = $value;
			}
		}
		return $normalized;
	}

	/**
	 * Collapses WP's response headers into the assoc form the SDK expects.
	 *
	 * `wp_remote_retrieve_headers()` returns a `Requests_Utility_CaseInsensitiveDictionary`
	 * (or `WpOrg\Requests\Utility\CaseInsensitiveDictionary`); both expose
	 * `getAll()`.
	 *
	 * @param array|\WP_Error $response Whatever `wp_safe_remote_request()` returned.
	 * @return array<string,string>
	 */
	private function collapse_headers( $response ): array {
		$headers = wp_remote_retrieve_headers( $response );
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		if ( ! is_array( $headers ) ) {
			return [];
		}

		$out = [];
		foreach ( $headers as $name => $value ) {
			$out[ (string) $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		return $out;
	}
}
