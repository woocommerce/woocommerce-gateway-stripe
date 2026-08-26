<?php

/**
 * Class WC_Stripe_API
 *
 * @package WooCommerce/Stripe/WC_Stripe_API
 *
 * Class WC_Stripe_API tests.
 */
class WC_Stripe_API_Test extends WP_UnitTestCase {

	/**
	 * Secret key for test mode.
	 */
	const TEST_SECRET_KEY = 'sk_test_key_123';

	/**
	 * Secret key for live mode.
	 */
	const LIVE_SECRET_KEY = 'sk_live_key_123';

	/**
	 * Setup environment for tests.
	 */
	public function set_up() {
		parent::set_up();

		$stripe_settings                    = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']         = 'yes';
		$stripe_settings['testmode']        = 'yes';
		$stripe_settings['secret_key']      = self::LIVE_SECRET_KEY;
		$stripe_settings['test_secret_key'] = self::TEST_SECRET_KEY;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Reset the invalid API keys count cache.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Tear down environment after tests.
	 */
	public function tear_down() {
		// Reset the invalid API keys count cache.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		// Clear any outage state recorded during the test run.
		delete_transient( WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY );

		// Clear any level3 rejection state recorded during the test run.
		delete_transient( 'wc_stripe_level3_not_allowed' );

		WC_Stripe_Helper::delete_main_stripe_settings();
		WC_Stripe_API::set_secret_key( null );

		parent::tear_down();
	}

	/**
	 * Test get_secret_key and set_secret_key.
	 */
	public function test_set_secret_key() {
		$secret_key = 'sk_test_key';
		WC_Stripe_API::set_secret_key( $secret_key );

		$this->assertEquals( $secret_key, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::set_secret_key_for_mode() with no parameter.
	 */
	public function test_set_secret_key_for_mode_no_parameter() {
		// Base test - current mode is test.
		WC_Stripe_API::set_secret_key_for_mode();

		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Enable live mode.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Stripe_API::set_secret_key_for_mode();

		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::set_secret_key_for_mode() with mode parameters.
	 */
	public function test_set_secret_key_for_mode_with_parameter() {
		WC_Stripe_API::set_secret_key_for_mode( 'test' );
		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		WC_Stripe_API::set_secret_key_for_mode( 'live' );
		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Invalid parameters will set the secret key to the current mode.
		WC_Stripe_API::set_secret_key_for_mode( 'invalid' );
		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Set the mode to live and test the invalid parameter.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Stripe_API::set_secret_key_for_mode( 'invalid' );
		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::retrieve() when API keys are valid.
	 */
	public function test_retrieve_makes_api_call_when_api_keys_are_valid() {
		// Mock a successful API response
		add_filter( 'pre_http_request', [ $this, 'mock_successful_response' ] );

		// Call the retrieve method
		$result = WC_Stripe_API::retrieve( 'test_endpoint' );

		// Verify the result matches our mock response
		$this->assertEquals( 'success', $result );

		// Clean up
		remove_filter( 'pre_http_request', [ $this, 'mock_successful_response' ] );
	}

	/**
	 * Test WC_Stripe_API::retrieve() returns null without API call after raeching the max threshold.
	 */
	public function test_retrieve_returns_null_without_api_call_after_threshold() {
		$call_count = 0;

		// Mock HTTP to always return 401 and increment the counter.
		add_filter(
			'pre_http_request',
			function () use ( &$call_count ) {
				$call_count++;
				return $this->mock_unauthorized_response();
			}
		);

		$stripe_api_class = new ReflectionClass( WC_Stripe_API::class );
		$threshold        = $stripe_api_class->getConstant( 'INVALID_API_KEY_ERROR_COUNT_THRESHOLD' );

		// Call retrieve up to the threshold, each should make an HTTP call.
		for ( $i = 0; $i < $threshold; $i++ ) {
			WC_Stripe_API::retrieve( 'test_endpoint' );
		}
		$this->assertEquals( $threshold, $call_count, 'Should have made HTTP calls up to the threshold.' );

		// Now, the next call should NOT make an HTTP call, but return null immediately.
		$result = WC_Stripe_API::retrieve( 'test_endpoint' );
		$this->assertNull( $result, 'Expected null after reaching invalid API key threshold.' );
		$this->assertEquals( $threshold, $call_count, 'Should not make another HTTP call after threshold is reached.' );

		remove_all_filters( 'pre_http_request' );
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Test WC_Stripe_API::retrieve() resets the invalid API key count on successful response.
	 */
	public function test_retrieve_resets_invalid_api_key_count_on_successful_response() {
		// 1. Mock a 401 response for the first call.
		add_filter( 'pre_http_request', [ $this, 'mock_unauthorized_response' ] );

		// First call: should set the cache count to 1.
		WC_Stripe_API::retrieve( 'test_endpoint' );
		$count = WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		$this->assertEquals( 1, $count, 'Cache count should be 1 after first 401.' );

		remove_all_filters( 'pre_http_request' );

		// 2. Mock a 200 response for the second call.
		add_filter( 'pre_http_request', [ $this, 'mock_successful_response' ] );

		// Second call: should delete the cache.
		WC_Stripe_API::retrieve( 'test_endpoint' );
		$count = WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		$this->assertNull( $count, 'Cache should be deleted after a successful response.' );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Helper method to mock a successful API response.
	 */
	public function mock_successful_response() {
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => json_encode( 'success' ),
		];
	}

	/**
	 * Helper method to mock an unauthorized API response.
	 */
	public function mock_unauthorized_response() {
		return [
			'response' => [
				'code'    => 401,
				'message' => 'Unauthorized',
			],
			'body'     => json_encode( [ 'error' => 'invalid_api_key' ] ),
		];
	}

	/**
	 * Regression test for https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5337
	 * We must not use wp_safe_remote_post(), as the calls can fail when the host's DNS resolution is flaky.
	 */
	public function test_request_does_not_use_safe_remote_http() {
		$captured_args = null;

		$capture_filter = function ( $return_value, $parsed_args ) use ( &$captured_args ) {
			$captured_args = $parsed_args;
			return $this->mock_successful_response();
		};
		add_filter( 'pre_http_request', $capture_filter, 10, 2 );

		try {
			WC_Stripe_API::request( [], 'test_endpoint', 'POST' );
		} finally {
			remove_filter( 'pre_http_request', $capture_filter, 10 );
		}

		$this->assertIsArray( $captured_args );
		$this->assertNotTrue(
			$captured_args['reject_unsafe_urls'] ?? false,
			'Stripe API POST requests must not set reject_unsafe_urls.'
		);
	}

	/**
	 * Regression test for https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5337
	 * We must not use wp_safe_remote_get() as the calls can fail when the host's DNS resolution is flaky.
	 */
	public function test_retrieve_does_not_use_safe_remote_http() {
		$captured_args = null;

		$capture_filter = function ( $return_value, $parsed_args ) use ( &$captured_args ) {
			$captured_args = $parsed_args;
			return $this->mock_successful_response();
		};
		add_filter( 'pre_http_request', $capture_filter, 10, 2 );

		try {
			WC_Stripe_API::retrieve( 'test_endpoint' );
		} finally {
			remove_filter( 'pre_http_request', $capture_filter, 10 );
		}

		$this->assertIsArray( $captured_args );
		$this->assertNotTrue(
			$captured_args['reject_unsafe_urls'] ?? false,
			'Stripe API GET requests must not set reject_unsafe_urls.'
		);
	}

	/**
	 * Captures the outbound HTTP request (URL + args) made during $callback, without hitting Stripe.
	 *
	 * @param callable $callback Invokes the WC_Stripe_API method under test.
	 * @return array{url:?string,args:?array} The captured request.
	 */
	private function capture_stripe_request( callable $callback ): array {
		$captured = [
			'url'  => null,
			'args' => null,
		];

		$capture_filter = function ( $return_value, $parsed_args, $url ) use ( &$captured ) {
			$captured['url']  = $url;
			$captured['args'] = $parsed_args;
			return $this->mock_successful_response();
		};
		add_filter( 'pre_http_request', $capture_filter, 10, 3 );

		try {
			$callback();
		} finally {
			remove_filter( 'pre_http_request', $capture_filter, 10 );
		}

		return $captured;
	}

	/**
	 * Prefix-compatible values whose structural delimiters must be percent-encoded when concatenated
	 * into a Stripe API path, alongside valid IDs that must pass through unchanged (encoding no-op).
	 *
	 * All values take the default (payment_methods) branch, so a single provider drives every helper.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function provide_path_segment_encoding_ids(): array {
		return [
			'valid pm (encoding is a no-op)'          => [ 'pm_1MqLiJLkdIwHu7ixUEgbFdYF', 'pm_1MqLiJLkdIwHu7ixUEgbFdYF' ],
			'valid legacy card (encoding is a no-op)' => [ 'card_1AbCdEfGhIjKlMnO', 'card_1AbCdEfGhIjKlMnO' ],
			'path traversal to another API'           => [ 'pm_1AbC/../setup_intents', 'pm_1AbC%2F..%2Fsetup_intents' ],
			'extra path segment'                      => [ 'pm_1AbC/extra', 'pm_1AbC%2Fextra' ],
			'query delimiter'                         => [ 'pm_1AbC?foo=bar', 'pm_1AbC%3Ffoo%3Dbar' ],
			'ampersand and equals'                    => [ 'pm_1AbC&usage=on_session=1', 'pm_1AbC%26usage%3Don_session%3D1' ],
			'fragment'                                => [ 'pm_1AbC#/attach', 'pm_1AbC%23%2Fattach' ],
			'brackets'                                => [ 'pm_1AbC[0]', 'pm_1AbC%5B0%5D' ],
			'space (rawurlencode not urlencode)'      => [ 'pm_a b', 'pm_a%20b' ],
			'prefixless retargeting payload'          => [ '../setup_intents?payment_method_types[]=card&usage=on_session#', '..%2Fsetup_intents%3Fpayment_method_types%5B%5D%3Dcard%26usage%3Don_session%23' ],
		];
	}

	/**
	 * A payment method ID is concatenated into the Stripe path, so it must be encoded as a single
	 * segment: structural delimiters percent-encoded, valid IDs left byte-for-byte identical.
	 *
	 * @dataProvider provide_path_segment_encoding_ids
	 */
	public function test_get_payment_method_encodes_id_as_single_path_segment( string $id, string $encoded_id ) {
		$captured = $this->capture_stripe_request(
			function () use ( $id ) {
				WC_Stripe_API::get_payment_method( $id );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'payment_methods/' . $encoded_id, $captured['url'] );
	}

	/**
	 * get_payment_method routes src_ IDs to the Sources API, which must also encode them as one segment.
	 */
	public function test_get_payment_method_encodes_source_id_as_single_path_segment() {
		$captured = $this->capture_stripe_request(
			function () {
				WC_Stripe_API::get_payment_method( 'src_1AbC/../charges' );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'sources/src_1AbC%2F..%2Fcharges', $captured['url'] );
	}

	/**
	 * A pre-encoded value is treated as literal path data, not decoded: an embedded %2F becomes %252F,
	 * so it can never be re-interpreted as a path separator downstream.
	 */
	public function test_get_payment_method_does_not_decode_pre_encoded_input() {
		$captured = $this->capture_stripe_request(
			function () {
				WC_Stripe_API::get_payment_method( 'pm_a%2Fb' );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'payment_methods/pm_a%252Fb', $captured['url'] );
	}

	/**
	 * The update path — `payment_methods/{id}`.
	 *
	 * @dataProvider provide_path_segment_encoding_ids
	 */
	public function test_update_payment_method_encodes_id_as_single_path_segment( string $id, string $encoded_id ) {
		$captured = $this->capture_stripe_request(
			function () use ( $id ) {
				WC_Stripe_API::update_payment_method( $id );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'payment_methods/' . $encoded_id, $captured['url'] );
	}

	/**
	 * The default (PaymentMethod) attach path — `payment_methods/{id}/attach`. The src_ branch, which
	 * sends the value in the request body instead, is covered by the test below.
	 *
	 * @dataProvider provide_path_segment_encoding_ids
	 */
	public function test_attach_payment_method_to_customer_encodes_id_as_single_path_segment( string $id, string $encoded_id ) {
		$captured = $this->capture_stripe_request(
			function () use ( $id ) {
				WC_Stripe_API::attach_payment_method_to_customer( 'cus_123', $id );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'payment_methods/' . $encoded_id . '/attach', $captured['url'] );
	}

	/**
	 * For src_ IDs, attach sends the value in the request body (source=), not the path — so there is
	 * nothing to path-encode. Assert the ID never lands in the URL and is preserved intact in the body.
	 */
	public function test_attach_payment_method_to_customer_keeps_source_id_out_of_the_path() {
		$captured = $this->capture_stripe_request(
			function () {
				WC_Stripe_API::attach_payment_method_to_customer( 'cus_123', 'src_1AbC' );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'customers/cus_123/sources', $captured['url'] );

		$body        = $captured['args']['body'];
		$body_string = is_array( $body ) ? http_build_query( $body ) : (string) $body;
		$this->assertStringContainsString( 'src_1AbC', $body_string );
	}

	/**
	 * The default (PaymentMethod) detach path — `payment_methods/{id}/detach`. The src_ branch, which
	 * uses the Sources API instead, is covered by the test below.
	 *
	 * @dataProvider provide_path_segment_encoding_ids
	 */
	public function test_detach_payment_method_from_customer_encodes_id_as_single_path_segment( string $id, string $encoded_id ) {
		$captured = $this->capture_stripe_request(
			function () use ( $id ) {
				WC_Stripe_API::detach_payment_method_from_customer( 'cus_123', $id );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'payment_methods/' . $encoded_id . '/detach', $captured['url'] );
	}

	/**
	 * detach_payment_method_from_customer routes src_ IDs to the Sources API (DELETE) and encodes them.
	 */
	public function test_detach_payment_method_from_customer_encodes_source_id_as_single_path_segment() {
		$captured = $this->capture_stripe_request(
			function () {
				WC_Stripe_API::detach_payment_method_from_customer( 'cus_123', 'src_1AbC/../charges' );
			}
		);

		$this->assertSame( WC_Stripe_API::ENDPOINT . 'customers/cus_123/sources/src_1AbC%2F..%2Fcharges', $captured['url'] );
		$this->assertSame( 'DELETE', $captured['args']['method'] );
	}

	/**
	 * Test WC_Stripe_API::log_error_response() as called from WC_Stripe_API::request() and WC_Stripe_API::retrieve().
	 *
	 * @param array|WP_Error $response     The mock response.
	 * @param string         $api          The API endpoint.
	 * @param string         $method       The HTTP method used for the request.
	 * @param array|null     $request_data The mock request data. Only used for POST requests.
	 * @dataProvider provide_test_log_error_response_tests
	 */
	public function test_log_error_response( $response, string $api, string $method, ?array $request_data = null ) {
		$expected_url = WC_Stripe_API::ENDPOINT . $api;

		$pre_http_filter = function ( $return_value, $parsed_args, $url ) use ( $response, $method, $expected_url ) {
			if ( $url !== $expected_url ) {
				return $return_value;
			}
			if ( ( $parsed_args['method'] ?? null ) !== $method ) {
				return $return_value;
			}

			return $response;
		};

		$mock_logger               = $this->createMock( \WC_Logger::class );
		\WC_Stripe_Logger::$logger = $mock_logger;

		$expected_data_keys = [
			'stripe_request_id',
			'response',
		];

		if ( 'POST' === $method ) {
			$expected_data_keys[] = 'idempotency_key';
			$expected_data_keys[] = 'request';
		}

		if (
			is_wp_error( $response ) &&
			'http_request_failed' === $response->get_error_code() &&
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			__( 'A valid URL was not provided.' ) === $response->get_error_message()
		) {
			$expected_data_keys[] = 'resolved_ip_address';
			$expected_data_keys[] = 'validation_details';
		}

		$expected_data_keys_callback = function ( $context ) use ( $expected_data_keys ) {
			$this->assertLessThanOrEqual( count( $context ), count( $expected_data_keys ) );
			foreach ( $expected_data_keys as $key ) {
				$this->assertArrayHasKey( $key, $context );
			}
			return true;
		};

		$mock_logger->expects( $this->once() )
			->method( 'error' )
			->with(
				$this->stringStartsWith( "Stripe API error: $method $api" ),
				$this->callback( $expected_data_keys_callback )
			);

		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		if ( 'GET' === $method ) {
			$result = WC_Stripe_API::retrieve( $api );
		} else {
			$caught_exception = null;
			try {
				$result = WC_Stripe_API::request( $request_data, $api, $method, false );
			} catch ( \WC_Stripe_Exception $stripe_exception ) {
				$caught_exception = $stripe_exception;
			}
		}

		// Clean up before we perform any assertions.
		remove_filter( 'pre_http_request', $pre_http_filter );
		\WC_Stripe_Logger::$logger = null;

		$is_outage = WC_Stripe_API_Outage_Status::is_outage_response( $response );

		if ( 'GET' === $method ) {
			$this->assertInstanceof( \WP_Error::class, $result );
			if ( $is_outage ) {
				$this->assertEquals( 'stripe_api_outage', $result->get_error_code() );
				$this->assertEquals( __( 'The Stripe API is temporarily unavailable. Please try again in a few minutes.', 'woocommerce-gateway-stripe' ), $result->get_error_message() );
			} else {
				$this->assertEquals( 'stripe_error', $result->get_error_code() );
				$this->assertEquals( __( 'There was a problem retrieving data from the Stripe API endpoint.', 'woocommerce-gateway-stripe' ), $result->get_error_message() );
			}
		} else {
			$this->assertInstanceof( \WC_Stripe_Exception::class, $caught_exception );
			$this->assertEquals( print_r( $response, true ), $caught_exception->getMessage() );
			if ( $is_outage ) {
				$this->assertEquals( __( 'The Stripe API is temporarily unavailable. Please try again in a few minutes.', 'woocommerce-gateway-stripe' ), $caught_exception->getLocalizedMessage() );
			} else {
				$this->assertEquals( __( 'There was a problem sending a request to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ), $caught_exception->getLocalizedMessage() );
			}
		}
	}

	/**
	 * Data provider for {@see test_log_error_response()}.
	 *
	 * @return array
	 */
	public function provide_test_log_error_response_tests(): array {
		return [
			'generic error for GET account'                             => [
				'response' => new \WP_Error( 'mock_error', 'Mock Error' ),
				'api'      => 'account',
				'method'   => 'GET',
			],
			'generic error for POST account'                            => [
				'response'     => new \WP_Error( 'mock_error', 'Mock Error' ),
				'api'          => 'account',
				'method'       => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
			'general http_request_failed error for GET account'         => [
				'response' => new \WP_Error( 'http_request_failed', 'Mock Error' ),
				'api'      => 'account',
				'method'   => 'GET',
			],
			'general http_request_failed error for POST account'        => [
				'response'     => new \WP_Error( 'http_request_failed', 'Mock Error' ),
				'api'          => 'account',
				'method'       => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
			'URL validation http_request_failed error for GET account'  => [
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'response' => new \WP_Error( 'http_request_failed', __( 'A valid URL was not provided.' ) ),
				'api'      => 'account',
				'method'   => 'GET',
			],
			'URL validation http_request_failed error for POST account' => [
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'response'     => new \WP_Error( 'http_request_failed', __( 'A valid URL was not provided.' ) ),
				'api'          => 'account',
				'method'       => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
			'empty response body for GET account'                       => [
				'response' => [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '',
				],
				'api'      => 'account',
				'method'   => 'GET',
			],
			'empty response body for POST account'                      => [
				'response'     => [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '',
				],
				'api'          => 'account',
				'method'       => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
		];
	}

	public function provide_test_should_detach_payment_method_from_customer(): array {
		return [
			'test mode from non-admin context should detach'                                  => [
				'expected_return'        => true,
				'is_test_mode'           => true,
				'is_admin_request'       => false,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from non-admin context should detach'                                  => [
				'expected_return'        => true,
				'is_test_mode'           => false,
				'is_admin_request'       => false,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'test mode from admin context should detach'                                      => [
				'expected_return'        => true,
				'is_test_mode'           => true,
				'is_admin_request'       => true,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'test mode from wp cron context should detach'                                    => [
				'expected_return'        => true,
				'is_test_mode'           => true,
				'is_admin_request'       => false,
				'is_cron_request'        => true,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from admin context with no subscription staging site should detach'    => [
				'expected_return'        => true,
				'is_test_mode'           => false,
				'is_admin_request'       => true,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from wp cron context with no subscription staging site should detach'  => [
				'expected_return'        => true,
				'is_test_mode'           => false,
				'is_admin_request'       => false,
				'is_cron_request'        => true,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from admin context with subscription staging site should not detach'   => [
				'expected_return'        => false,
				'is_test_mode'           => false,
				'is_admin_request'       => true,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => true,
			],
			'live mode from wp cron context with subscription staging site should not detach' => [
				'expected_return'        => false,
				'is_test_mode'           => false,
				'is_admin_request'       => false,
				'is_cron_request'        => true,
				'is_wc_sub_staging_site' => true,
			],
			// Ideally, we would test multiple environment types, but wp_get_environment_type() uses a
			// static variable that can't be modified between tests.
		];
	}

	/**
	 * @dataProvider provide_test_should_detach_payment_method_from_customer
	 */
	public function test_should_detach_payment_method_from_customer( bool $expected_return, bool $is_test_mode, bool $is_admin_request, bool $is_cron_request, bool $is_wc_sub_staging_site = false ) {
		$initial_test_mode = \WC_Stripe_Mode::is_test();

		$stripe_settings             = \WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		\WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$initial_current_screen = null;
		$reset_current_screen   = false;

		if ( $is_admin_request ) {
			$initial_current_screen = $GLOBALS['current_screen'] ?? null;
			$reset_current_screen   = true;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = \WP_Screen::get( 'post.php' );
		}

		$cron_filter_return = $is_cron_request ? '__return_true' : '__return_false';
		add_filter( 'wp_doing_cron', $cron_filter_return, 10, 1 );

		require_once __DIR__ . '/helpers/class-wcs-staging.php';
		\WCS_Staging::set_is_duplicate_site( $is_wc_sub_staging_site );

		$result = \WC_Stripe_API::should_detach_payment_method_from_customer();

		// Reset the environment before running any assertions.
		if ( $reset_current_screen ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = $initial_current_screen;
		}

		if ( $initial_test_mode !== $is_test_mode ) {
			$stripe_settings             = \WC_Stripe_Helper::get_stripe_settings();
			$stripe_settings['testmode'] = $initial_test_mode ? 'yes' : 'no';
			\WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}

		remove_filter( 'wp_doing_cron', $cron_filter_return, 10 );

		\WCS_Staging::set_is_duplicate_site( false );

		$this->assertEquals( $expected_return, $result );
	}

	/**
	 * Filter helper that returns a fixed wp_remote response.
	 *
	 * @param array|WP_Error $response The response to return.
	 * @return Closure
	 */
	private function return_response( $response ) {
		return function () use ( $response ) {
			return $response;
		};
	}

	/**
	 * A 5xx response from Stripe records an outage and surfaces a
	 * stripe_api_outage WP_Error from retrieve().
	 */
	public function test_retrieve_records_outage_on_5xx() {
		$response = [
			'response' => [
				'code'    => 503,
				'message' => 'Service Unavailable',
			],
			'headers'  => [],
			'body'     => '{"error":"service unavailable"}',
		];

		$filter = $this->return_response( $response );
		add_filter( 'pre_http_request', $filter );

		$result = WC_Stripe_API::retrieve( 'account' );

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'stripe_api_outage', $result->get_error_code() );
		$this->assertTrue( WC_Stripe_API_Outage_Status::is_in_outage() );
	}

	/**
	 * A network failure (WP_Error from wp_remote_*) records an outage.
	 */
	public function test_retrieve_records_outage_on_network_error() {
		$filter = $this->return_response( new \WP_Error( 'http_request_failed', 'Connection refused' ) );
		add_filter( 'pre_http_request', $filter );

		$result = WC_Stripe_API::retrieve( 'account' );

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'stripe_api_outage', $result->get_error_code() );
		$this->assertTrue( WC_Stripe_API_Outage_Status::is_in_outage() );
	}

	/**
	 * A successful response clears any prior outage flag.
	 */
	public function test_retrieve_clears_outage_on_success() {
		WC_Stripe_API_Outage_Status::record_outage();
		$this->assertTrue( WC_Stripe_API_Outage_Status::is_in_outage() );

		$filter = $this->return_response(
			[
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [],
				'body'     => '{"id":"acct_123"}',
			]
		);
		add_filter( 'pre_http_request', $filter );

		WC_Stripe_API::retrieve( 'account' );

		remove_filter( 'pre_http_request', $filter );

		$this->assertFalse( WC_Stripe_API_Outage_Status::is_in_outage() );
	}

	/**
	 * A 4xx response is NOT an outage — Stripe is alive, just rejecting us.
	 * The transient should be cleared.
	 */
	public function test_retrieve_4xx_is_not_outage() {
		WC_Stripe_API_Outage_Status::record_outage();

		$filter = $this->return_response(
			[
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
				'headers'  => [],
				'body'     => '{"error":{"message":"bad request"}}',
			]
		);
		add_filter( 'pre_http_request', $filter );

		WC_Stripe_API::retrieve( 'account' );

		remove_filter( 'pre_http_request', $filter );

		$this->assertFalse( WC_Stripe_API_Outage_Status::is_in_outage() );
	}

	/**
	 * request() throws a WC_Stripe_Exception with the outage-specific
	 * localized message when Stripe responds with 5xx.
	 */
	public function test_request_throws_outage_exception_on_5xx() {
		$response = [
			'response' => [
				'code'    => 502,
				'message' => 'Bad Gateway',
			],
			'headers'  => [],
			'body'     => '{"error":"bad gateway"}',
		];

		$filter = $this->return_response( $response );
		add_filter( 'pre_http_request', $filter );

		$caught = null;
		try {
			WC_Stripe_API::request(
				[
					'amount'   => 100,
					'metadata' => [ 'order_id' => 1 ],
				],
				'charges',
				'POST',
				false
			);
		} catch ( \WC_Stripe_Exception $e ) {
			$caught = $e;
		}

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WC_Stripe_Exception::class, $caught );
		$this->assertEquals(
			__( 'The Stripe API is temporarily unavailable. Please try again in a few minutes.', 'woocommerce-gateway-stripe' ),
			$caught->getLocalizedMessage()
		);
		$this->assertTrue( WC_Stripe_API_Outage_Status::is_in_outage() );
	}

	/**
	 * Level3 gating must prefer the request's own payment_method_types over the order meta,
	 * which stays as the fallback for requests with no payment method info (captures).
	 *
	 * @param string[]|null $request_types   The payment_method_types the request carries, or null for none.
	 * @param string        $order_meta_type The Stripe UPE payment type stored on the order, or '' for none.
	 * @param bool          $expect_level3   Whether the outgoing request should carry level3 data.
	 * @dataProvider provide_test_request_with_level3_data_gating
	 */
	public function test_request_with_level3_data_gates_on_request_types_then_order_meta( $request_types, $order_meta_type, $expect_level3 ) {
		// The level3 gate only applies to US-based stores.
		update_option( 'woocommerce_default_country', 'US:CA' );
		delete_transient( 'wc_stripe_level3_not_allowed' );

		$order = WC_Helper_Order::create_order();
		if ( '' !== $order_meta_type ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $order_meta_type );
			$order->save_meta_data();
		}

		$request = [ 'amount' => 100 ];
		if ( null !== $request_types ) {
			$request['payment_method_types'] = $request_types;
		}

		$bodies_seen = [];
		$mock        = function ( $preempt, $parsed_args ) use ( &$bodies_seen ) {
			$bodies_seen[] = $parsed_args['body'];

			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => '{"id":"pi_mock"}',
			];
		};
		add_filter( 'pre_http_request', $mock, 10, 2 );

		WC_Stripe_API::request_with_level3_data( $request, 'payment_intents', [ 'merchant_reference' => (string) $order->get_id() ], $order );

		remove_filter( 'pre_http_request', $mock, 10 );

		$this->assertCount( 1, $bodies_seen );
		if ( $expect_level3 ) {
			$this->assertArrayHasKey( 'level3', $bodies_seen[0] );
		} else {
			$this->assertArrayNotHasKey( 'level3', $bodies_seen[0] );
		}
	}

	/**
	 * Provider for {@see test_request_with_level3_data_gates_on_request_types_then_order_meta()}.
	 *
	 * @return array
	 */
	public function provide_test_request_with_level3_data_gating() {
		return [
			'card request'                               => [ [ WC_Stripe_Payment_Methods::CARD ], '', true ],
			'card request wins over non-card meta'       => [ [ WC_Stripe_Payment_Methods::CARD ], WC_Stripe_Payment_Methods::AMAZON_PAY, true ],
			'non-card request wins over card meta'       => [ [ WC_Stripe_Payment_Methods::AMAZON_PAY ], WC_Stripe_Payment_Methods::CARD, false ],
			// The case the meta-only gate could not catch: a flow that never wrote the meta.
			'non-card request without meta'              => [ [ WC_Stripe_Payment_Methods::AMAZON_PAY ], '', false ],
			'card with link keeps level3'                => [ [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ], '', true ],
			// No type selected at checkout: the intent lists every enabled method. Card being a
			// candidate is enough — Stripe only rejects level3 when no type on the intent supports it.
			'mixed types including card keep level3'     => [ [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], '', true ],
			'no request types falls back to card meta'   => [ null, WC_Stripe_Payment_Methods::CARD, true ],
			'no request types respects non-card meta'    => [ null, WC_Stripe_Payment_Methods::AMAZON_PAY, false ],
			'no request types, no meta defaults to card' => [ null, '', true ],
			// Filters run on the request before the gate, so entries are untrusted: non-string
			// garbage must not count as declared types and the meta decides instead.
			'malformed types fall back to card meta'     => [ [ null, [ 'nested' ] ], WC_Stripe_Payment_Methods::CARD, true ],
			'malformed types fall back to non-card meta' => [ [ null, [ 'nested' ] ], WC_Stripe_Payment_Methods::AMAZON_PAY, false ],
		];
	}

	/**
	 * A level3 rejection is only cached account-wide (3-month transient) when the payment is
	 * known to support level3 — for an unknown type the rejection is ambiguous. The payment
	 * must go through either way, via the retry without level3.
	 *
	 * @param string[]|null $request_types    The payment_method_types the request carries, or null for none.
	 * @param string        $order_meta_type  The Stripe UPE payment type stored on the order, or '' for none.
	 * @param bool          $expect_transient Whether the rejection should set the account-wide transient.
	 * @dataProvider provide_test_request_with_level3_data_rejection_transient
	 */
	public function test_request_with_level3_data_only_caches_rejections_for_known_level3_payments( $request_types, $order_meta_type, $expect_transient ) {
		// The level3 gate only applies to US-based stores.
		update_option( 'woocommerce_default_country', 'US:CA' );
		delete_transient( 'wc_stripe_level3_not_allowed' );

		$order = WC_Helper_Order::create_order();
		if ( '' !== $order_meta_type ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $order_meta_type );
			$order->save_meta_data();
		}

		$request = [ 'amount' => 100 ];
		if ( null !== $request_types ) {
			$request['payment_method_types'] = $request_types;
		}

		$bodies_seen = [];
		$mock        = function ( $preempt, $parsed_args ) use ( &$bodies_seen ) {
			$bodies_seen[] = $parsed_args['body'];

			$body = isset( $parsed_args['body']['level3'] )
				? '{"error":{"code":"parameter_unknown","param":"level3","type":"invalid_request_error","message":"Received unknown parameter: level3"}}'
				: '{"id":"pi_mock"}';

			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => $body,
			];
		};
		add_filter( 'pre_http_request', $mock, 10, 2 );

		$result = WC_Stripe_API::request_with_level3_data( $request, 'payment_intents', [ 'merchant_reference' => (string) $order->get_id() ], $order );

		remove_filter( 'pre_http_request', $mock, 10 );

		// The rejection must always trigger a retry without level3 so the payment goes through.
		$this->assertCount( 2, $bodies_seen );
		$this->assertArrayHasKey( 'level3', $bodies_seen[0] );
		$this->assertArrayNotHasKey( 'level3', $bodies_seen[1] );
		$this->assertSame( 'pi_mock', $result->id );

		$this->assertSame( $expect_transient, (bool) get_transient( 'wc_stripe_level3_not_allowed' ) );
	}

	/**
	 * Provider for {@see test_request_with_level3_data_only_caches_rejections_for_known_level3_payments()}.
	 *
	 * Only combinations that attach level3 in the first place can reach the rejection.
	 *
	 * @return array
	 */
	public function provide_test_request_with_level3_data_rejection_transient() {
		return [
			'card request caches the rejection'         => [ [ WC_Stripe_Payment_Methods::CARD ], '', true ],
			'mixed types with card cache the rejection' => [ [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], '', true ],
			'card meta caches the rejection'            => [ null, WC_Stripe_Payment_Methods::CARD, true ],
			// Unknown type: level3 is sent via the legacy card default, but the rejection is
			// ambiguous and must not disable level3 account-wide.
			'unknown type does not cache the rejection' => [ null, '', false ],
		];
	}
}
