<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_API class.
 *
 * Communicates with Stripe API.
 */
class WC_Stripe_API {

	/**
	 * Stripe API Endpoint
	 */
	const ENDPOINT           = 'https://api.stripe.com/v1/';
	const STRIPE_API_VERSION = '2024-06-20';

	/**
	 * The invalid API key error count cache key.
	 *
	 * @var string
	 */
	public const INVALID_API_KEY_ERROR_COUNT_CACHE_KEY = 'invalid_api_key_error_count';

	/**
	 * The invalid API key error count cache timeout.
	 * This is the delay in seconds enforced for Stripe API calls after the consecutive error count threshold is reached.
	 *
	 * @var int
	 */
	protected const INVALID_API_KEY_ERROR_COUNT_CACHE_TIMEOUT = 2 * HOUR_IN_SECONDS;

	/**
	 * The invalid API key error count threshold.
	 *
	 * @var int
	 */
	protected const INVALID_API_KEY_ERROR_COUNT_THRESHOLD = 5;

	/**
	 * Secret API Key.
	 *
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Instance of WC_Stripe_API.
	 *
	 * @var WC_Stripe_API
	 */
	private static $instance;

	/**
	 * Get instance of WC_Stripe_API.
	 *
	 * @return WC_Stripe_API
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set instance of WC_Stripe_API.
	 *
	 * @param WC_Stripe_API $instance
	 */
	public static function set_instance( $instance ) {
		self::$instance = $instance;
	}

	/**
	 * Set secret API Key.
	 *
	 * @param string $key
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Get secret key.
	 *
	 * @return string
	 */
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			self::set_secret_key_for_mode();
		}
		return self::$secret_key;
	}

	/**
	 * Set secret key based on mode.
	 *
	 * @param string|null $mode Optional. The mode to set the secret key for. 'live' or 'test'. Default will set the secret for the currently active mode.
	 */
	public static function set_secret_key_for_mode( $mode = null ) {
		$options         = WC_Stripe_Helper::get_stripe_settings();
		$secret_key      = $options['secret_key'] ?? '';
		$test_secret_key = $options['test_secret_key'] ?? '';

		if ( ! in_array( $mode, [ 'test', 'live' ], true ) ) {
			$mode = WC_Stripe_Mode::is_test() ? 'test' : 'live';
		}

		self::set_secret_key( 'test' === $mode ? $test_secret_key : $secret_key );
	}

	/**
	 * Generates the user agent we use to pass to API request so
	 * Stripe can identify our application.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_user_agent() {
		$app_info = [
			'name'       => 'WooCommerce Stripe Gateway',
			'version'    => WC_STRIPE_VERSION,
			'url'        => 'https://woocommerce.com/products/stripe/',
			'partner_id' => 'pp_partner_EYuSt9peR0WTMg',
		];

		return [
			'lang'         => 'php',
			'lang_version' => phpversion(),
			'publisher'    => 'woocommerce',
			'uname'        => function_exists( 'php_uname' ) ? php_uname() : PHP_OS,
			'application'  => $app_info,
		];
	}

	/**
	 * Generates the headers to pass to API request.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_headers() {
		$user_agent = self::get_user_agent();
		$app_info   = $user_agent['application'];

		$headers = [
			'Authorization' => 'Basic ' . base64_encode( self::get_secret_key() . ':' ),
			'Stripe-Version' => self::STRIPE_API_VERSION,
		];

		$headers = apply_filters_deprecated(
			'woocommerce_stripe_request_headers',
			[ $headers ],
			'9.7.0',
			'wc_stripe_request_headers',
			'The woocommerce_stripe_request_headers filter is deprecated since WooCommerce Stripe Gateway 9.7.0, and will be removed in a future version. Use wc_stripe_request_headers instead.'
		);

		/**
		 * Filters the request headers sent to the Stripe API.
		 *
		 * @since 9.7.0
		 *
		 * @param array $headers The default headers we send to the Stripe API.
		 * @param array $user_agent The user agent.
		 */
		$headers = apply_filters( 'wc_stripe_request_headers', $headers );

		// These headers should not be overridden for this gateway.
		$headers['User-Agent']                 = $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')';
		$headers['X-Stripe-Client-User-Agent'] = wp_json_encode( $user_agent );

		return $headers;
	}

	/**
	 * Generates the idempotency key for the request.
	 *
	 * @param string $api The API endpoint.
	 * @param string $method The HTTP method.
	 * @param array  $request The request parameters.
	 * @return string|null The idempotency key.
	 */
	public static function get_idempotency_key( $api, $method, $request ) {
		if ( 'charges' === $api && 'POST' === $method ) {
			$customer = ! empty( $request['customer'] ) ? $request['customer'] : '';
			$source   = ! empty( $request['source'] ) ? $request['source'] : $customer;
			return $request['metadata']['order_id'] . '-' . $source;
		} elseif ( 'payment_intents' === $api && 'POST' === $method ) {
			// https://docs.stripe.com/api/idempotent_requests suggests using
			// v4 uuids for idempotency keys.
			return wp_generate_uuid4();
		}

		return null;
	}

	/**
	 * Send the request to Stripe's API
	 *
	 * @since 3.1.0
	 * @version 4.0.6
	 * @param array  $request
	 * @param string $api
	 * @param string $method
	 * @param bool   $with_headers To get the response with headers.
	 * @return stdClass|array
	 * @throws WC_Stripe_Exception
	 */
	public static function request( $request, $api = 'charges', $method = 'POST', $with_headers = false ) {
		$headers = self::get_headers();

		$idempotency_key = apply_filters( 'wc_stripe_idempotency_key', self::get_idempotency_key( $api, $method, $request ), $request );
		if ( $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$request = apply_filters_deprecated(
			'woocommerce_stripe_request_body',
			[ $request, $api ],
			'9.7.0',
			'wc_stripe_request_body',
			'The woocommerce_stripe_request_body filter is deprecated since WooCommerce Stripe Gateway 9.7.0, and will be removed in a future version. Use wc_stripe_request_body instead.'
		);

		/**
		 * Filters the request body sent to the Stripe API.
		 *
		 * @since 9.7.0
		 *
		 * @param array $request The default request body we will send to the Stripe API.
		 * @param string $api The Stripe API endpoint.
		 */
		$request = apply_filters( 'wc_stripe_request_body', $request, $api );

		// Log the request after the filters have been applied.
		WC_Stripe_Logger::debug( "Stripe API request: {$method} {$api}", [ 'request' => $request ] );

		$response = wp_safe_remote_post(
			self::ENDPOINT . $api,
			[
				'method'  => $method,
				'headers' => $headers,
				'body'    => $request,
				'timeout' => 70,
			]
		);

		$response_headers = wp_remote_retrieve_headers( $response );

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			// Stripe redacts API keys in the response.
			WC_Stripe_Logger::error(
				"Stripe API error: {$method} {$api}",
				[
					'request'         => $request,
					'idempotency_key' => $idempotency_key,
					'response'        => $response,
				]
			);

			throw new WC_Stripe_Exception( print_r( $response, true ), __( 'There was a problem connecting to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ) );
		}

		$response_body = json_decode( $response['body'] );

		WC_Stripe_Logger::debug( "Stripe API response: {$method} {$api}", [ 'response' => $response_body ] );

		if ( $with_headers ) {
			return [
				'headers' => $response_headers,
				'body'    => $response_body,
			];
		}

		return $response_body;
	}

	/**
	 * Retrieve API endpoint.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $api
	 */
	public static function retrieve( $api ) {
		// If keep count of consecutive 401 errors, and it exceeds INVALID_API_KEY_ERROR_COUNT_THRESHOLD,
		// we return null until the cache expires (INVALID_API_KEY_ERROR_COUNT_CACHE_TIMEOUT) or the keys are updated.
		$invalid_api_key_error_count = WC_Stripe_Database_Cache::get( self::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		if ( ! empty( $invalid_api_key_error_count ) && self::INVALID_API_KEY_ERROR_COUNT_THRESHOLD <= $invalid_api_key_error_count ) {
			// We skip logging the error here because when there is no Account cache,
			// the instantiation of the UPE gateway triggers a call to this method for
			// every available payment method. This would result in excessive log entries
			// which is not useful.
			// We only log the error when the count exceeds the threshold for the first time.

			// The UI expects a null response (and not an error) in case of invalid API keys.
			return null;
		}

		WC_Stripe_Logger::debug( "Stripe API request: GET {$api}" );

		$response = wp_safe_remote_get(
			self::ENDPOINT . $api,
			[
				'method'  => 'GET',
				'headers' => self::get_headers(),
				'timeout' => 70,
			]
		);

		// If we get a 401 error, we know the secret key is not valid.
		if ( is_array( $response ) && isset( $response['response'] ) && is_array( $response['response'] ) && isset( $response['response']['code'] ) && 401 === $response['response']['code'] ) {
			// Stripe redacts API keys in the response.
			WC_Stripe_Logger::error(
				"Stripe API error: GET {$api} returned a 401",
				[
					'response' => json_decode( $response['body'] ),
				]
			);

			++$invalid_api_key_error_count;
			WC_Stripe_Database_Cache::set( self::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY, $invalid_api_key_error_count, self::INVALID_API_KEY_ERROR_COUNT_CACHE_TIMEOUT );

			if ( $invalid_api_key_error_count >= self::INVALID_API_KEY_ERROR_COUNT_THRESHOLD ) {
				WC_Stripe_Logger::error(
					'Invalid API keys request rate limit exceeded',
					[
						'count'      => $invalid_api_key_error_count,
						'next_retry' => date_i18n( 'Y-m-d H:i:sP', time() + self::INVALID_API_KEY_ERROR_COUNT_CACHE_TIMEOUT ),
					]
				);

				// We need to invalidate the Account Data cache here, so that the UI shows the "Connect to Stripe" button.
				WC_Stripe_Database_Cache::delete( WC_Stripe_Account::ACCOUNT_CACHE_KEY );
			}

			return null; // The UI expects this empty response in case of invalid API keys.

		}

		// We got a valid, non-401 response, so clear the invalid API key count if it is present.
		if ( null !== $invalid_api_key_error_count ) {
			WC_Stripe_Database_Cache::delete( self::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		}

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_Stripe_Logger::error(
				"Stripe API error: GET {$api}",
				[
					'response' => $response,
				]
			);
			return new WP_Error( 'stripe_error', __( 'There was a problem connecting to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ) );
		}

		$response_body = json_decode( $response['body'] );

		WC_Stripe_Logger::debug( "Stripe API response: GET {$api}", [ 'response' => $response_body ] );

		return $response_body;
	}

	/**
	 * Send the request to Stripe's API with level 3 data generated
	 * from the order. If the request fails due to an error related
	 * to level3 data, make the request again without it to allow
	 * the payment to go through.
	 *
	 * @since 4.3.2
	 * @version 5.1.0
	 *
	 * @param array    $request     Array with request parameters.
	 * @param string   $api         The API path for the request.
	 * @param array    $level3_data The level 3 data for this request.
	 * @param WC_Order $order       The order associated with the payment.
	 *
	 * @return stdClass|array The response
	 */
	public static function request_with_level3_data( $request, $api, $level3_data, $order ) {
		// 1. Do not add level3 data if the array is empty.
		// 2. Do not add level3 data if there's a transient indicating that level3 was
		// not accepted by Stripe in the past for this account.
		// 3. Do not try to add level3 data if merchant is not based in the US.
		// https://docs.stripe.com/level3#level-iii-usage-requirements
		// (Needs to be authenticated with a level3 gated account to see above docs).
		if (
			empty( $level3_data ) ||
			get_transient( 'wc_stripe_level3_not_allowed' ) ||
			'US' !== WC()->countries->get_base_country()
		) {
			return self::request(
				$request,
				$api
			);
		}

		// Add level 3 data to the request.
		$request['level3'] = $level3_data;

		$result = self::request(
			$request,
			$api
		);

		// Check for amount_too_small error - if found, return immediately without retrying
		if (
			isset( $result->error ) &&
			isset( $result->error->code ) &&
			'amount_too_small' === $result->error->code
		) {
			return $result;
		}

		$is_level3_param_not_allowed = (
			isset( $result->error )
			&& isset( $result->error->code )
			&& 'parameter_unknown' === $result->error->code
			&& isset( $result->error->param )
			&& 'level3' === $result->error->param
		);

		$is_level_3data_incorrect = (
			isset( $result->error )
			&& isset( $result->error->type )
			&& 'invalid_request_error' === $result->error->type
		);

		if ( $is_level3_param_not_allowed ) {
			// Set a transient so that future requests do not add level 3 data.
			// Transient is set to expire in 3 months, can be manually removed if needed.
			set_transient( 'wc_stripe_level3_not_allowed', true, 3 * MONTH_IN_SECONDS );
		} elseif ( $is_level_3data_incorrect ) {
			// Log the issue so we could debug it.
			WC_Stripe_Logger::error(
				'Level3 data sum incorrect',
				[
					'error'                 => $result->error,
					'order_line_items'      => $order->get_items(),
					'order_shipping_amount' => $order->get_shipping_total(),
					'order_currency'        => $order->get_currency(),
				]
			);
		}

		// Make the request again without level 3 data.
		if ( $is_level3_param_not_allowed || $is_level_3data_incorrect ) {
			unset( $request['level3'] );
			return self::request(
				$request,
				$api
			);
		}

		return $result;
	}

	/**
	 * Returns a payment method object from Stripe given an ID. Accepts both 'src_xxx' and 'pm_xxx'
	 * style IDs for backwards compatibility.
	 *
	 * @param string $payment_method_id The ID of the payment method to retrieve.
	 *
	 * @return stdClass  The payment method object.
	 */
	public static function get_payment_method( string $payment_method_id ) {
		// Sources have a separate API.
		if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
			return self::retrieve( 'sources/' . $payment_method_id );
		}

		// If it's not a source it's a PaymentMethod.
		return self::retrieve( 'payment_methods/' . $payment_method_id );
	}

	/**
	 * Update payment method data.
	 *
	 * @param string $payment_method_id   Payment method ID.
	 * @param array  $payment_method_data Payment method updated data.
	 *
	 * @return array Payment method details.
	 *
	 * @throws WC_Stripe_Exception If payment method update fails.
	 */
	public static function update_payment_method( $payment_method_id, $payment_method_data = [] ) {
		return self::request(
			$payment_method_data,
			'payment_methods/' . $payment_method_id
		);
	}

	/**
	 * Attaches a payment method to the given customer.
	 *
	 * @param string $customer_id        The ID of the customer the payment method should be attached to.
	 * @param string $payment_method_id  The payment method that should be attached to the customer.
	 *
	 * @return stdClass|array  The response from the API request.
	 * @throws WC_Stripe_Exception
	 */
	public static function attach_payment_method_to_customer( string $customer_id, string $payment_method_id ) {
		// Sources and Payment Methods need different API calls.
		if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
			return self::request(
				[ 'source' => $payment_method_id ],
				'customers/' . $customer_id . '/sources'
			);
		}

		return self::request(
			[ 'customer' => $customer_id ],
			'payment_methods/' . $payment_method_id . '/attach'
		);
	}

	/**
	 * Detaches a payment method from the given customer.
	 *
	 * @param string $customer_id        The ID of the customer that contains the payment method that should be detached.
	 * @param string $payment_method_id  The ID of the payment method that should be detached.
	 *
	 * @return  stdClass|array  The response from the API request
	 * @throws WC_Stripe_Exception
	 */
	public static function detach_payment_method_from_customer( string $customer_id, string $payment_method_id ) {
		if ( ! self::should_detach_payment_method_from_customer() ) {
			return [];
		}

		$payment_method_id = sanitize_text_field( $payment_method_id );

		// Sources and Payment Methods need different API calls.
		if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
			return self::request(
				[],
				'customers/' . $customer_id . '/sources/' . $payment_method_id,
				'DELETE'
			);
		}

		return self::request(
			[],
			'payment_methods/' . $payment_method_id . '/detach'
		);
	}

	/**
	 * Checks if a payment method should be detached from a customer.
	 *
	 * If the site is a staging/local/development site in live mode, we should not detach the payment method
	 * from the customer to avoid detaching it from the production site.
	 *
	 * @return bool True if the payment should be detached, false otherwise.
	 */
	public static function should_detach_payment_method_from_customer() {
		// If we are in test mode, we can always detach the payment method.
		if ( WC_Stripe_Mode::is_test() ) {
			return true;
		}

		// Return true for the delete user request from the admin dashboard or WP-CLI when the site is a production site
		// and return false when the site is a staging/local/development site.
		// This is to avoid detaching the payment method from the live production site.
		// Requests coming from the customer account page i.e delete payment method, are not affected by this and returns true.
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			if ( 'production' === wp_get_environment_type() ) {
				return true;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the payment method configuration.
	 *
	 * @return array The response from the API request.
	 */
	public function get_payment_method_configurations() {
		return self::retrieve( 'payment_method_configurations' );
	}

	/**
	 * Update the payment method configuration.
	 *
	 * @param array $payment_method_configurations The payment method configurations to update.
	 */
	public function update_payment_method_configurations( $id, $payment_method_configurations ) {
		$response = self::request(
			$payment_method_configurations,
			'payment_method_configurations/' . $id
		);
		return $response;
	}
}
