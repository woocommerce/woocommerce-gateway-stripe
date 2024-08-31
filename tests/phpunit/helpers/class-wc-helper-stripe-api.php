<?php
/**
 * Stripe API helpers.
 *
 * @package WooCommerce\Tests
 */

/**
 * Class WC_Helper_Stripe_Api.
 *
 * This helper class should ONLY be used for unit tests!.
 * This helper class is used to mock static functions of WC_Stripe_API
 */
class WC_Helper_Stripe_Api {

	/**
	 * Retrieve response.
	 *
	 * Use this to mock what should be returned from WC_Stripe_API::retrieve()
	 *
	 * @var array
	 */
	public static $retrieve_response = [
		'id'    => '1234',
		'email' => 'test@example.com',
	];

	/**
	 * Request response.
	 *
	 * Use this to mock what should be returned from WC_Stripe_API::request()
	 *
	 * @var array
	 */
	public static $request_response = [];

	/**
	 * The expected request calls params.
	 *
	 * Use this to check if the expected params are passed to WC_Stripe_API::request()
	 *
	 * @var array
	 */
	public static $expected_request_call_params = null;

	/**
	 * Reset the helper.
	 */
	public static function reset() {
		self::$retrieve_response = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		self::$request_response = [];
		self::$expected_request_call_params = null;
	}

	/**
	 * Retrieve data. This is the equivalent mock for WC_Stripe_API::retrieve
	 *
	 * @param string data type
	 *
	 * @return array retrieved data mock
	 */
	public static function retrieve( $key = 'account' ) {
		return self::$retrieve_response;
	}

	/**
	 * Request data. This is the equivalent mock for WC_Stripe_API::request()
	 *
	 * @param array $request     Request data.
	 * @param string $api        API endpoint.
	 * @param string $method     Request method.
	 * @param bool $with_headers Include headers in the response.
	 *
	 * @return array $response
	 */
	public static function request( $request, $api = 'charges', $method = 'POST', $with_headers = false ) {
		// If the expected request calls params are set, check if the params match the expected params.
		if ( ! is_null( self::$expected_request_call_params ) ) {
			$passed_params   = [ $request, $api, $method, $with_headers ];
			$expected_params = array_shift( self::$expected_request_call_params );

			// Fill in missing expected params with default values.
			$expected_params = [
				$expected_params[0],
				$expected_params[1] ?? 'charges',
				$expected_params[2] ?? 'POST',
				$expected_params[3] ?? false,
			];

			if ( $expected_params !== $passed_params ) {
				throw new Exception( 'Expected request params do not match the actual request params.' );
			}
		}

		// The returned value should be mocked in the test.
		return self::$request_response;
	}
}
