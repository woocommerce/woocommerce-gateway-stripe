<?php

/**
 * These tests make assertions against class WC_Stripe_Logger.
 *
 * Class WC_Stripe_Logger_Test.
 */
class WC_Stripe_Logger_Test extends WP_UnitTestCase {
	/**
	 * Test for `can_log`.
	 *
	 * @return void
	 */
	public function test_can_log() {
		$this->assertFalse( WC_Stripe_Logger::can_log() );

		$stripe_settings            = WC_Stripe::get_instance()->get_settings();
		$stripe_settings['logging'] = 'yes';
		WC_Stripe::get_instance()->update_settings( $stripe_settings );

		$this->assertTrue( WC_Stripe_Logger::can_log() );
	}

	/**
	 * Tests {@see WC_Stripe_Logger::can_log()} calls the 'wc_stripe_logger_can_log' filter
	 * with the correct arguments.
	 *
	 * @dataProvider provide_log_level_inputs
	 * @param string|null $log_level_input The log level to test.
	 * @return void
	 */
	public function test_can_log_calls_filter_correctly( ?string $log_level_input = null ): void {
		$stripe_settings = WC_Stripe::get_instance()->get_settings();
		unset( $stripe_settings['logging'] );
		WC_Stripe::get_instance()->update_settings( $stripe_settings );

		$captured_can_log          = null;
		$captured_log_level        = null;
		$captured_calling_class    = null;
		$captured_calling_function = null;

		$filter = function ( $can_log, $log_level, $calling_class, $calling_function ) use ( &$captured_can_log, &$captured_calling_class, &$captured_calling_function, &$captured_log_level ) {
			$captured_can_log          = $can_log;
			$captured_calling_class    = $calling_class;
			$captured_calling_function = $calling_function;
			$captured_log_level        = $log_level;

			return $can_log;
		};
		add_filter( 'wc_stripe_logger_can_log', $filter, 10, 4 );

		$result = WC_Stripe_Logger::can_log( $log_level_input );

		remove_filter( 'wc_stripe_logger_can_log', $filter, 10 );

		$this->assertFalse( $result );
		$this->assertFalse( $captured_can_log );
		$this->assertEquals( $log_level_input, $captured_log_level );
		$this->assertEquals( self::class, $captured_calling_class );
		$this->assertEquals( __FUNCTION__, $captured_calling_function );
	}

	/**
	 * Provides the log level inputs for the {@see test_can_log_calls_filter_correctly()} test.
	 *
	 * @return array The log level inputs.
	 */
	public function provide_log_level_inputs(): array {
		return [
			'null'    => [ null ],
			'warning' => [ 'warning' ],
			'notice'  => [ 'notice' ],
			'info'    => [ 'info' ],
			'debug'   => [ 'debug' ],
		];
	}

	/**
	 * Tests {@see WC_Stripe_Logger::can_log()} correctly normalizes the value returned
	 * from the 'wc_stripe_logger_can_log' filter.
	 *
	 * @dataProvider provide_log_level_and_filter_return_scenarios
	 * @param string|null $log_level_input     The log level to test.
	 * @param mixed       $filter_return_value The value to return from the 'wc_stripe_logger_can_log' filter.
	 * @param bool        $expected_result     The expected result of the can_log() call.
	 * @return void
	 */
	public function test_can_log_normalizes_filter_return_value( ?string $log_level_input, $filter_return_value, bool $expected_result ): void {
		$stripe_settings = WC_Stripe::get_instance()->get_settings();
		unset( $stripe_settings['logging'] );
		WC_Stripe::get_instance()->update_settings( $stripe_settings );

		$filter = function ( $can_log ) use ( $filter_return_value ) {
			return $filter_return_value;
		};
		add_filter( 'wc_stripe_logger_can_log', $filter, 10, 1 );

		$result = WC_Stripe_Logger::can_log( $log_level_input );

		remove_filter( 'wc_stripe_logger_can_log', $filter, 10 );

		$this->assertEquals( $expected_result, $result );
	}

	/**
	 * Provides the log level and filter values for the {@see test_can_log_normalizes_filter_return_value()} test.
	 *
	 * @return array The log level inputs and filter return values.
	 */
	public function provide_log_level_and_filter_return_scenarios(): array {
		return [
			'null log level, null filter'          => [ null, null, false ],
			'null log level, 0 filter'             => [ null, 0, false ],
			'null log level, [ true ] filter'      => [ null, [ true ], false ],
			'warning log level, false filter'      => [ 'warning', false, false ],
			'warning log level, 1 filter'          => [ 'warning', 1, false ],
			'notice log level, true filter'        => [ 'notice', true, true ],
			"notice log level, '0' filter"         => [ 'notice', '0', false ],
			"info log level, 'false' filter"       => [ 'info', 'false', false ],
			"info log level, 'true' filter"        => [ 'info', 'true', false ],
			'debug log level, empty string filter' => [ 'debug', '', false ],
			"debug log level, '1' filter"          => [ 'debug', '1', false ],
			'debug log level, array filter'        => [ 'debug', [], false ],
		];
	}

	/**
	 * Tests that the logger methods which call {@see WC_Stripe_Logger::can_log()} trigger
	 * the wc_stripe_logger_can_log filter as expected.
	 *
	 * @dataProvider provide_logger_methods_that_call_can_log
	 * @param string $logger_method The logger method to test.
	 * @return void
	 */
	public function test_logger_methods_call_filter_correctly( string $logger_method ): void {
		$stripe_settings = WC_Stripe::get_instance()->get_settings();
		unset( $stripe_settings['logging'] );
		WC_Stripe::get_instance()->update_settings( $stripe_settings );

		$captured_can_log          = null;
		$captured_log_level        = null;
		$captured_calling_class    = null;
		$captured_calling_function = null;

		$filter = function ( $can_log, $log_level, $calling_class, $calling_function ) use ( &$captured_can_log, &$captured_calling_class, &$captured_calling_function, &$captured_log_level ) {
			$captured_can_log          = $can_log;
			$captured_calling_class    = $calling_class;
			$captured_calling_function = $calling_function;
			$captured_log_level        = $log_level;

			return true;
		};
		add_filter( 'wc_stripe_logger_can_log', $filter, 10, 4 );

		$mock_message = 'test message: ' . $logger_method;

		$mock_logger = $this->getMockBuilder( WC_Logger::class )->getMock();
		$mock_logger->expects( $this->once() )
			->method( $logger_method )
			->with( $mock_message, $this->isType( 'array' ) );

		WC_Stripe_Logger::$logger = $mock_logger;

		call_user_func( [ WC_Stripe_Logger::class, $logger_method ], $mock_message );

		remove_filter( 'wc_stripe_logger_can_log', $filter, 10 );
		WC_Stripe_Logger::$logger = null;

		$this->assertFalse( $captured_can_log );
		$this->assertEquals( $logger_method, $captured_log_level );
		$this->assertEquals( self::class, $captured_calling_class );
		$this->assertEquals( __FUNCTION__, $captured_calling_function );
	}

	/**
	 * Provides the logger methods that call {@see WC_Stripe_Logger::can_log()}.
	 * Data provider for {@see test_logger_methods_call_filter_correctly()}.
	 *
	 * @return array The logger methods.
	 */
	public function provide_logger_methods_that_call_can_log(): array {
		return [
			'warning' => [ 'warning' ],
			'notice'  => [ 'notice' ],
			'info'    => [ 'info' ],
			'debug'   => [ 'debug' ],
		];
	}
}
