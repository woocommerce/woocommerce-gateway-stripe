<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for the official Stripe PHP SDK client.
 *
 * Returns a `\Stripe\StripeClient` configured with the active secret key, the
 * pinned API version, and the partner / app info Stripe uses to identify this
 * plugin in dashboards and traffic analytics.
 *
 * @since 10.7.0
 */
class WC_Stripe_Client {

	/**
	 * The Stripe partner ID assigned to the WooCommerce Stripe Gateway plugin.
	 */
	const PARTNER_ID = 'pp_partner_EYuSt9peR0WTMg';

	/**
	 * Cached SDK client.
	 *
	 * @var \Stripe\StripeClient|null
	 */
	private static $client = null;

	/**
	 * The secret key the cached client was built with. Lets us rebuild the
	 * client when the key changes (mode toggle, OAuth reconnect, tests).
	 *
	 * @var string
	 */
	private static $cached_secret_key = '';

	/**
	 * Returns a configured `\Stripe\StripeClient`.
	 *
	 * Lazily constructs the client and caches it per-secret-key.
	 */
	public static function get(): \Stripe\StripeClient {
		$secret_key = WC_Stripe_API::get_secret_key();

		// The SDK rejects an empty `api_key` at construction time, before the
		// HTTP call ever happens. `WC_Stripe_API::request()` historically let
		// unkeyed requests go out and 401 — which is what the invalid-key
		// tracking in `WC_Stripe_Client::retrieve()` is designed to handle.
		// Fall back to a placeholder so the SDK lets the request through and
		// the WP HTTP layer (and any mocks) take over.
		if ( '' === (string) $secret_key ) {
			$secret_key = 'sk_test_placeholder';
		}

		if ( null === self::$client || self::$cached_secret_key !== $secret_key ) {
			\Stripe\Stripe::setAppInfo(
				'WooCommerce Stripe Gateway',
				WC_STRIPE_VERSION,
				'https://woocommerce.com/products/stripe/',
				self::PARTNER_ID
			);

			// Route SDK traffic through `wp_safe_remote_request` so existing
			// `pre_http_request` mocks, third-party WP HTTP filters, and the
			// `wp_safe_remote_*` allowlist all continue to apply.
			\Stripe\ApiRequestor::setHttpClient( new WC_Stripe_SDK_WP_Http_Client() );

			self::$client = new \Stripe\StripeClient(
				[
					'api_key'        => $secret_key,
					'stripe_version' => WC_Stripe_API::STRIPE_API_VERSION,
				]
			);

			self::$cached_secret_key = $secret_key;
		}

		return self::$client;
	}

	/**
	 * Resets the cached client. Mainly intended for tests and for callers that
	 * mutate the secret key out-of-band.
	 */
	public static function reset(): void {
		self::$client            = null;
		self::$cached_secret_key = '';
	}

	/**
	 * Drop-in replacement for `WC_Stripe_API::request()` that routes through
	 * the SDK while preserving the cross-cutting behavior the old helper
	 * carried (`wc_stripe_request_headers` / `wc_stripe_request_body` /
	 * `wc_stripe_idempotency_key` filters, idempotency keys for charges and
	 * payment intents, request/response logging).
	 *
	 * @param array  $request      The request body / query parameters.
	 * @param string $api          The Stripe API path (e.g. `customers`, `payment_intents/pi_123`).
	 * @param string $method       HTTP method.
	 * @param bool   $with_headers When true, returns `[ 'headers' => ..., 'body' => ... ]`.
	 *
	 * @return \Stripe\StripeObject|stdClass|array
	 *
	 * @throws WC_Stripe_Exception When the SDK cannot reach the API at all.
	 */
	public static function request( $request, $api = 'charges', $method = 'POST', $with_headers = false ) {
		$request = self::filter_request_body( (array) $request, $api );

		$idempotency_key = apply_filters(
			'wc_stripe_idempotency_key',
			WC_Stripe_API::get_idempotency_key( $api, strtoupper( $method ), $request ),
			$request
		);

		$masked_secret_key = WC_Stripe_API::get_masked_secret_key();

		WC_Stripe_Logger::debug(
			"Stripe API request: {$method} {$api}",
			[
				'stripe_api_key' => $masked_secret_key,
				'request'        => $request,
			]
		);

		[ $path, $query_params ] = self::split_path_and_query( $api );
		$lower_method            = self::normalize_http_method( $method );
		$params                  = 'get' === $lower_method ? array_merge( $request, $query_params ) : $request;
		$opts                    = [];
		if ( $idempotency_key ) {
			$opts['idempotency_key'] = $idempotency_key;
		}
		$response_headers  = [];
		$stripe_request_id = null;

		try {
			$obj           = self::get()->request( $lower_method, '/v1/' . $path, $params, $opts );
			$last_response = $obj->getLastResponse();
			if ( $last_response ) {
				$response_headers  = $last_response->headers ?? [];
				$stripe_request_id = $response_headers['Request-Id'] ?? null;
			}
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			$obj               = self::convert_exception_to_response( $e );
			$stripe_request_id = $e->getRequestId();
		} catch ( \Throwable $e ) {
			WC_Stripe_Logger::error(
				"Stripe API request failed before reaching the API: {$method} {$api}",
				[
					'stripe_api_key'  => $masked_secret_key,
					'request'         => $request,
					'idempotency_key' => $idempotency_key,
					'exception'       => $e->getMessage(),
				]
			);
			throw new WC_Stripe_Exception(
				$e->getMessage(),
				__( 'There was a problem sending a request to the Stripe API endpoint.', 'woocommerce-gateway-stripe' )
			);
		}

		WC_Stripe_Logger::debug(
			"Stripe API response: {$method} {$api}",
			[
				'stripe_api_key'    => $masked_secret_key,
				'stripe_request_id' => $stripe_request_id,
				'response'          => $obj,
			]
		);

		if ( $with_headers ) {
			return [
				'headers' => $response_headers,
				'body'    => $obj,
			];
		}

		return $obj;
	}

	/**
	 * Drop-in replacement for `WC_Stripe_API::retrieve()`.
	 *
	 * Performs a GET and preserves the consecutive-401 invalid-API-key tracking
	 * the old helper carried (after the threshold, returns `null` until the
	 * cache expires or the keys change).
	 *
	 * @param string $api The Stripe API path (may include a query string).
	 *
	 * @return \Stripe\StripeObject|stdClass|null
	 */
	public static function retrieve( $api ) {
		$invalid_api_key_error_count = WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		if ( ! empty( $invalid_api_key_error_count ) && $invalid_api_key_error_count >= 5 ) {
			return null;
		}

		[ $path, $query_params ] = self::split_path_and_query( $api );
		$masked_secret_key       = WC_Stripe_API::get_masked_secret_key();

		WC_Stripe_Logger::debug(
			"Stripe API request: GET {$api}",
			[ 'stripe_api_key' => $masked_secret_key ]
		);

		try {
			$obj = self::get()->request( 'get', '/v1/' . $path, $query_params, [] );
		} catch ( \Stripe\Exception\AuthenticationException $e ) {
			WC_Stripe_Logger::error(
				"Stripe API error: GET {$api} returned a 401",
				[
					'stripe_api_key'    => $masked_secret_key,
					'stripe_request_id' => $e->getRequestId(),
				]
			);

			$invalid_api_key_error_count = is_int( $invalid_api_key_error_count ) ? $invalid_api_key_error_count + 1 : 1;
			WC_Stripe_Database_Cache::set(
				WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY,
				$invalid_api_key_error_count,
				2 * HOUR_IN_SECONDS
			);

			if ( $invalid_api_key_error_count >= 5 ) {
				WC_Stripe_Database_Cache::delete( WC_Stripe_Account::ACCOUNT_CACHE_KEY );
			}

			return null;
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			$obj = self::convert_exception_to_response( $e );
		}

		// Successful or non-401 error response — clear the invalid-key counter.
		if ( ! empty( $invalid_api_key_error_count ) ) {
			WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		}

		WC_Stripe_Logger::debug(
			"Stripe API response: GET {$api}",
			[
				'stripe_api_key' => $masked_secret_key,
				'response'       => $obj,
			]
		);

		return $obj;
	}

	/**
	 * Drop-in replacement for `WC_Stripe_API::request_with_level3_data()`.
	 *
	 * @param array    $request     The request body.
	 * @param string   $api         The Stripe API path.
	 * @param array    $level3_data Level3 data to attach.
	 * @param WC_Order $order       The order; used for logging context only.
	 *
	 * @return \Stripe\StripeObject|stdClass|array
	 */
	public static function request_with_level3_data( $request, $api, $level3_data, $order ) {
		if (
			empty( $level3_data ) ||
			get_transient( 'wc_stripe_level3_not_allowed' ) ||
			'US' !== WC()->countries->get_base_country()
		) {
			return self::request( $request, $api );
		}

		$request['level3'] = $level3_data;
		$result            = self::request( $request, $api );

		if ( isset( $result->error->code ) && 'amount_too_small' === $result->error->code ) {
			return $result;
		}

		$is_level3_param_not_allowed = (
			isset( $result->error->code, $result->error->param )
			&& 'parameter_unknown' === $result->error->code
			&& 'level3' === $result->error->param
		);

		$is_level3_data_incorrect = (
			isset( $result->error->type )
			&& 'invalid_request_error' === $result->error->type
		);

		if ( $is_level3_param_not_allowed ) {
			set_transient( 'wc_stripe_level3_not_allowed', true, 3 * MONTH_IN_SECONDS );
		} elseif ( $is_level3_data_incorrect ) {
			WC_Stripe_Logger::error(
				'Level3 data sum incorrect',
				[
					'error'                 => $result->error,
					'order_line_items'      => $order ? $order->get_items() : [],
					'order_shipping_amount' => $order ? $order->get_shipping_total() : 0,
					'order_currency'        => $order ? $order->get_currency() : '',
				]
			);
		}

		if ( $is_level3_param_not_allowed || $is_level3_data_incorrect ) {
			unset( $request['level3'] );
			return self::request( $request, $api );
		}

		return $result;
	}

	/**
	 * Runs the request body through the same filters `WC_Stripe_API::request()` applies.
	 */
	private static function filter_request_body( array $request, string $api ): array {
		$request = apply_filters_deprecated(
			'woocommerce_stripe_request_body',
			[ $request, $api ],
			'9.7.0',
			'wc_stripe_request_body',
			'The woocommerce_stripe_request_body filter is deprecated since WooCommerce Stripe Gateway 9.7.0, and will be removed in a future version. Use wc_stripe_request_body instead.'
		);

		/** This filter is documented in includes/class-wc-stripe-api.php */
		return apply_filters( 'wc_stripe_request_body', $request, $api );
	}

	/**
	 * Splits a path that may include a query string ("foo?expand[]=bar") into
	 * a clean path and a query parameter array.
	 *
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	private static function split_path_and_query( string $api ): array {
		$query_position = strpos( $api, '?' );
		if ( false === $query_position ) {
			return [ $api, [] ];
		}

		$path       = (string) substr( $api, 0, $query_position );
		$query_part = (string) substr( $api, $query_position + 1 );
		parse_str( $query_part, $parsed );

		$query_params = [];
		foreach ( (array) $parsed as $key => $value ) {
			$query_params[ (string) $key ] = $value;
		}

		return [ $path, $query_params ];
	}

	/**
	 * Returns one of the SDK's accepted HTTP method literals.
	 *
	 * @return 'delete'|'get'|'post'
	 */
	private static function normalize_http_method( string $method ): string {
		$lower = strtolower( $method );
		if ( in_array( $lower, [ 'delete', 'get', 'post' ], true ) ) {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			/** @var 'delete'|'get'|'post' $lower */
			return $lower;
		}
		return 'post';
	}

	/**
	 * Converts a Stripe SDK exception into a `stdClass` with the same `->error`
	 * shape `WC_Stripe_API::request()` used to return, so existing callsites
	 * that check `$response->error` keep working without try/catch. Nested
	 * objects (`error.payment_intent.charges.data[0]`, …) are decoded as
	 * `stdClass` to match the `json_decode($body)` shape the legacy helper
	 * produced.
	 */
	private static function convert_exception_to_response( \Stripe\Exception\ApiErrorException $e ): \stdClass {
		$json_body = $e->getJsonBody();

		// Re-decode without `assoc` so nested structures are stdClass — matches
		// what `json_decode( $response['body'] )` returned in the legacy path.
		if ( is_array( $json_body ) ) {
			$decoded = json_decode( (string) wp_json_encode( $json_body ) );
			if ( is_object( $decoded ) && isset( $decoded->error ) ) {
				return $decoded;
			}
		}

		$response        = new \stdClass();
		$response->error = (object) [
			'message' => $e->getMessage(),
			'code'    => $e->getStripeCode(),
			'type'    => '',
		];

		return $response;
	}
}
