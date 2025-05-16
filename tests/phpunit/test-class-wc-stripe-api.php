<?php
/**
 * Class WC_Stripe_API
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_API
 */

/**
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
	}

	/**
	 * Tear down environment after tests.
	 */
	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();
		WC_Stripe_API::set_secret_key( null );

		WC_Stripe_Logger::$logger = null;

		if ( false !== has_filter( 'pre_http_request', [ $this, 'mock_429_response' ] ) ) {
			remove_filter( 'pre_http_request', [ $this, 'mock_429_response' ] );
		}

		if ( false !== has_filter( 'pre_http_request', [ $this, 'throw_exception_on_http_request' ] ) ) {
			remove_filter( 'pre_http_request', [ $this, 'throw_exception_on_http_request' ] );
		}

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
	 * Provide test modes for test cases that test both test and live modes.
	 *
	 * @return array
	 */
	public function provide_test_modes() {
		return [
			'live mode' => [ false ],
			'test mode' => [ true ],
		];
	}

	/**
	 * Test WC_Stripe_API::retrieve() returns early when rate limit is active.
	 *
	 * @dataProvider provide_test_modes
	 * @param bool $is_test_mode Whether the test mode is true or false.
	 */
	public function test_retrieve_returns_early_when_rate_limit_is_active( $is_test_mode ) {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		$settings['logging'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// Add this filter after we update the settings, as that code can trigger HTTP requests.
		add_filter( 'pre_http_request', [ $this, 'throw_exception_on_http_request' ] );

		$mock_logger = $this->createStub( WC_Logger_Interface::class );

		$mock_logger->expects( $this->never() )
			->method( 'debug' );

		$mock_logger->expects( $this->never() )
			->method( 'error' );

		$now = time();
		$rate_limit_option_key = $is_test_mode ? WC_Stripe_API::TEST_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY : WC_Stripe_API::LIVE_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY;
		update_option( $rate_limit_option_key, $now + 20 );

		WC_Stripe_Logger::$logger = $mock_logger;

		$result = WC_Stripe_API::retrieve( 'account' );

		WC_Stripe_Logger::$logger = null;

		$this->assertNull( $result );

		remove_filter( 'pre_http_request', [ $this, 'throw_exception_on_http_request' ] );
	}

	/**
	 * Test WC_Stripe_API::is_stripe_api_rate_limited() returns false when no rate limit is active.
	 *
	 * @dataProvider provide_test_modes
	 * @param bool $is_test_mode Whether the test mode is true or false.
	 */
	public function test_rate_limit_check_returns_false_when_no_rate_limit_is_active( $is_test_mode ) {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$this->assertFalse( WC_Stripe_API::is_stripe_api_rate_limited() );
	}

	/**
	 * Test WC_Stripe_API::is_stripe_api_rate_limited() returns false and deletes the option after the rate limit expires.
	 *
	 * @dataProvider provide_test_modes
	 * @param bool $is_test_mode Whether the test mode is true or false.
	 */
	public function test_rate_limit_check_returns_false_and_deletes_option_after_rate_limit_expires( $is_test_mode ) {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$rate_limit_option_key = $is_test_mode ? WC_Stripe_API::TEST_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY : WC_Stripe_API::LIVE_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY;
		update_option( $rate_limit_option_key, time() - 20 );

		$this->assertFalse( WC_Stripe_API::is_stripe_api_rate_limited() );

		$this->assertNull( get_option( $rate_limit_option_key, null ) );
	}

	/**
	 * Test WC_Stripe_API::retrieve() correctly triggers rate limiting when
	 * we receive a 429 response from the Stripe API.
	 *
	 * @dataProvider provide_test_modes
	 * @param bool $is_test_mode Whether the test mode is true or false.
	 */
	public function test_check_stripe_api_429_response_triggers_rate_limit( $is_test_mode ) {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		$settings['logging'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$rate_limit_option_key = $is_test_mode ? WC_Stripe_API::TEST_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY : WC_Stripe_API::LIVE_MODE_STRIPE_API_RATE_LIMIT_OPTION_KEY;
		$history_option_key = $rate_limit_option_key . '_history';
		update_option( $history_option_key, [], false );

		$mock_logger = $this->createStub( WC_Logger_Interface::class );

		$mock_logger->expects( $this->exactly( 2 ) )
			->method( 'debug' )
			->withConsecutive(
				[ $this->get_expected_log_message( 'account' ) ],
				[ $this->get_expected_log_prefix( 'Error Response: ' ) ],
			);

		$message_mode = $is_test_mode ? 'test' : 'LIVE';
		$mock_logger->expects( $this->once() )
			->method( 'error' )
			->with(
				"Stripe $message_mode mode API has been rate limited, disabling API calls for " . WC_Stripe_API::STRIPE_API_RATE_LIMIT_DURATION . ' seconds.'
			);

		// Mock 429 responses from the Stripe API.
		add_filter( 'pre_http_request', [ $this, 'mock_429_response' ] );

		WC_Stripe_Logger::$logger = $mock_logger;

		$request_start_time = time();
		$result = WC_Stripe_API::retrieve( 'account' );
		$request_end_time = time();

		// Unset the mock logger.
		WC_Stripe_Logger::$logger = null;

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'stripe_error', $result->get_error_code() );
		$this->assertEquals( 'There was a problem connecting to the Stripe API endpoint.', $result->get_error_message() );

		$rate_limit_option = get_option( $rate_limit_option_key );
		$this->assertIsInt( $rate_limit_option );

		$runtime_delta = max( $request_end_time - $request_start_time, 1 );
		$this->assertEqualsWithDelta( $request_end_time + WC_Stripe_API::STRIPE_API_RATE_LIMIT_DURATION, $rate_limit_option, $runtime_delta );

		$history = get_option( $history_option_key, null );
		$this->assertIsArray( $history );
		$this->assertCount( 1, $history );

		$history_entry = $history[0];
		$this->assertIsArray( $history_entry );
		$this->assertArrayHasKey( 'timestamp', $history_entry );
		$this->assertArrayHasKey( 'datetime', $history_entry );
		$this->assertArrayHasKey( 'duration', $history_entry );

		$expected_timestamp = $rate_limit_option - WC_Stripe_API::STRIPE_API_RATE_LIMIT_DURATION;
		$this->assertEquals( $expected_timestamp, $history_entry['timestamp'] );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', $expected_timestamp ) . ' UTC', $history_entry['datetime'] );
		$this->assertEquals( WC_Stripe_API::STRIPE_API_RATE_LIMIT_DURATION, $history_entry['duration'] );

		remove_filter( 'pre_http_request', [ $this, 'mock_429_response' ] );
	}

	/**
	 * Helper method to get the expected log message.
	 *
	 * @param string $message The message we expect to see in the log.
	 * @return string The expected log message.
	 */
	protected function get_expected_log_message( $message ) {
		$expected_log_message = "\n" . '====Stripe Version: ' . WC_STRIPE_VERSION . '====' . "\n";
		$expected_log_message .= '====Stripe Plugin API Version: ' . WC_Stripe_API::STRIPE_API_VERSION . '====' . "\n";
		$expected_log_message .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

		return $expected_log_message;
	}

	/**
	 * Helper method to get the expected log message prefix.
	 *
	 * @param string $message The message prefix we expect to see in the log.
	 * @return string The expected log message prefix.
	 */
	protected function get_expected_log_prefix( $message ) {
		$expected_log_prefix = "\n" . '====Stripe Version: ' . WC_STRIPE_VERSION . '====' . "\n";
		$expected_log_prefix .= '====Stripe Plugin API Version: ' . WC_Stripe_API::STRIPE_API_VERSION . '====' . "\n";
		$expected_log_prefix .= '====Start Log====' . "\n" . $message;

		return $this->stringStartsWith( $expected_log_prefix );
	}

	/**
	 * Helper method to mock an HTTP 429 response from the Stripe API.
	 */
	public function mock_429_response( $preempt ) {
		return [
			'response' => [
				'code' => 429,
				'message' => 'Too many requests',
			],
			'body'     => '',
		];
	}

	/**
	 * Helper method to throw an when triggered.
	 */
	public function throw_exception_on_http_request( $preempt ) {
		throw new Exception( 'HTTP request should not be triggered' );
	}
}
