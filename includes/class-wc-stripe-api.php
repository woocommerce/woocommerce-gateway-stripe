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
	 * Option key for cases where Stripe rate limits our live API calls.
	 *
	 * @var string
	 */
	public const LIVE_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY = 'wc_stripe_live_api_rate_limit';

	/**
	 * Option key for cases where Stripe rate limits our test API calls.
	 *
	 * @var string
	 */
	public const TEST_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY = 'wc_stripe_test_api_rate_limit';

	/**
	 * Duration we will use to disable Stripe API calls if we have been rate limited.
	 *
	 * @var int
	 */
	public const STRIPE_API_RATE_LIMIT_DURATION = 30;

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

		$headers = apply_filters(
			'woocommerce_stripe_request_headers',
			[
				'Authorization'  => 'Basic ' . base64_encode( self::get_secret_key() . ':' ),
				'Stripe-Version' => self::STRIPE_API_VERSION,
			]
		);

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
		WC_Stripe_Logger::log( "{$api} request: " . print_r( $request, true ) );

		$headers = self::get_headers();

		$idempotency_key = apply_filters( 'wc_stripe_idempotency_key', self::get_idempotency_key( $api, $method, $request ), $request );
		if ( $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$response = wp_safe_remote_post(
			self::ENDPOINT . $api,
			[
				'method'  => $method,
				'headers' => $headers,
				'body'    => apply_filters( 'woocommerce_stripe_request_body', $request, $api ),
				'timeout' => 70,
			]
		);

		$response_headers = wp_remote_retrieve_headers( $response );
		// Log the stripe version in the response headers, if present.
		if ( isset( $response_headers['stripe-version'] ) ) {
			WC_Stripe_Logger::log( "{$api} response with stripe-version: " . $response_headers['stripe-version'] );
		}

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_Stripe_Logger::log(
				'Error Response: ' . print_r( $response, true ) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					[
						'api'             => $api,
						'request'         => $request,
						'idempotency_key' => $idempotency_key,
					],
					true
				)
			);

			throw new WC_Stripe_Exception( print_r( $response, true ), __( 'There was a problem connecting to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ) );
		}

		if ( $with_headers ) {
			return [
				'headers' => $response_headers,
				'body'    => json_decode( $response['body'] ),
			];
		}

		return json_decode( $response['body'] );
	}

	/**
	 * Retrieve API endpoint.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $api
	 */
	public static function retrieve( $api ) {
		if ( self::is_stripe_api_rate_limited() ) {
			return null;
		}

		WC_Stripe_Logger::log( "{$api}" );

		$response = wp_safe_remote_get(
			self::ENDPOINT . $api,
			[
				'method'  => 'GET',
				'headers' => self::get_headers(),
				'timeout' => 70,
			]
		);

		$is_error_response = is_wp_error( $response ) || empty( $response['body'] );
		// Check for 4xx and 5xx HTTP responses.
		if ( ! $is_error_response && is_array( $response ) && isset( $response['response'] ) && is_array( $response['response'] ) && is_int( $response['response']['code'] ?? null ) ) {
			$is_error_response = $response['response']['code'] >= 400;
		}

		if ( $is_error_response ) {
			self::check_stripe_api_error_response( $response );
			WC_Stripe_Logger::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'stripe_error', __( 'There was a problem connecting to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ) );
		}

		return json_decode( $response['body'] );
	}

	/**
	 * Checks if the Stripe API for the current mode (i.e. test or live) is rate limited.
	 *
	 * @return bool True if the Stripe API is rate limited, false otherwise.
	 */
	public static function is_stripe_api_rate_limited() {
		$rate_limit_option_key = WC_Stripe_Mode::is_test() ? self::TEST_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY : self::LIVE_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY;

		$rate_limit_expiration = get_option( $rate_limit_option_key );
		if ( ! $rate_limit_expiration ) {
			return false;
		}

		$now = time();
		if ( $now > $rate_limit_expiration ) {
			delete_option( $rate_limit_option_key );
			return false;
		}

		return true;
	}

	/**
	 * Helper function to check error responses from Stripe and ensure we prevent unnecessary API calls,
	 * primarily in cases where we have been rate limited or we don't have valid keys..
	 *
	 * @param array|WP_Error $response The response from the Stripe API.
	 * @return void
	 */
	protected static function check_stripe_api_error_response( $response ) {
		// If we don't have an array for $response, return early, as we won't have an HTTP status code.
		if ( ! is_array( $response ) ) {
			return;
		}

		// We specifically want to check $response['response']['code']. If it's not present, return early.
		if ( ! isset( $response['response'] ) || ! is_array( $response['response'] ) || ! isset( $response['response']['code'] ) ) {
			return;
		}

		$status_code = $response['response']['code'];

		if ( 429 === $status_code ) {
			// Stripe has rate limited us, so disable API calls for a period of time.
			$is_test_mode = WC_Stripe_Mode::is_test();

			$timestamp = time();
			$rate_limit_option_key = $is_test_mode ? self::TEST_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY : self::LIVE_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY;
			update_option( $rate_limit_option_key, $timestamp + self::STRIPE_API_RATE_LIMIT_DURATION );

			$mode = $is_test_mode ? 'test' : 'LIVE';
			$message = "Stripe {$mode} mode API has been rate limited, disabling API calls for " . self::STRIPE_API_RATE_LIMIT_DURATION . ' seconds.';

			error_log( 'woocommerce-gateway-stripe: WARNING: ' . $message );
			WC_Stripe_Logger::error( $message );

			// Store history of rate limits so we can see how often they're occurring.
			$history_option_key = $rate_limit_option_key . '_history';
			$history = get_option( $history_option_key, [] );
			if ( ! is_array( $history ) ) {
				$history = [];
			}
			// Keep a maximum of 20 rate limit history entries.
			$history = array_slice( $history, -19 );
			$history[] = [
				'timestamp' => $timestamp,
				'datetime'  => gmdate( 'Y-m-d H:i:s', $timestamp ) . ' UTC',
				'duration'  => self::STRIPE_API_RATE_LIMIT_DURATION,
			];
			// Note that we set autoload to false - we don't want this option to be autoloaded by default.
			update_option( $history_option_key, $history, false );
		}
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
			WC_Stripe_Logger::log(
				'Level3 data sum incorrect: ' . PHP_EOL
				. print_r( $result->error->message, true ) . PHP_EOL
				. print_r( 'Order line items: ', true ) . PHP_EOL
				. print_r( $order->get_items(), true ) . PHP_EOL
				. print_r( 'Order shipping amount: ', true ) . PHP_EOL
				. print_r( $order->get_shipping_total(), true ) . PHP_EOL
				. print_r( 'Order currency: ', true ) . PHP_EOL
				. print_r( $order->get_currency(), true )
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

		// Return true for the delete user request from the admin dashboard when the site is a production site
		// and return false when the site is a staging/local/development site.
		// This is to avoid detaching the payment method from the live production site.
		// Requests coming from the customer account page i.e delete payment method, are not affected by this and returns true.
		if ( is_admin() ) {
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
