<?php

/**
 * Tests for the WC_Stripe_Database_Cache_Prefetch class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Database_Cache
 *
 * WC_Stripe_Database_Cache_Prefetch_Test Class.
 */
class WC_Stripe_Database_Cache_Prefetch_Test extends \WP_UnitTestCase {

	/**
	 * Ensure we clean up the pending prefetch data after each test.
	 */
	public function tearDown(): void {
		\WC_Stripe_Database_Cache_Prefetch::get_instance()->reset_pending_prefetches();
		delete_option( 'wcstripe_prefetch_' . \WC_Stripe_Account::ACCOUNT_CACHE_KEY );
		\WC_Stripe_Database_Cache::delete_with_mode( \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'test' );
		\WC_Stripe_Database_Cache::delete_with_mode( \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'live' );

		parent::tearDown();
	}

	/**
	 * Provide active and requested modes for cache-read prefetch tests.
	 *
	 * @return array
	 */
	public function provide_cache_read_prefetch_mode_test_cases(): array {
		return [
			'active test cache'     => [ 'yes', 'test', true ],
			'non-active live cache' => [ 'yes', 'live', false ],
			'active live cache'     => [ 'no', 'live', true ],
			'non-active test cache' => [ 'no', 'test', false ],
		];
	}

	/**
	 * Explicit reads may only queue prefetches for the active mode.
	 *
	 * @param string $testmode      The active test mode setting.
	 * @param string $requested_mode The cache mode to read.
	 * @param bool   $should_enqueue Whether a prefetch should be queued.
	 *
	 * @dataProvider provide_cache_read_prefetch_mode_test_cases
	 */
	public function test_cache_read_only_prefetches_active_mode( string $testmode, string $requested_mode, bool $should_enqueue ): void {
		$stripe_settings             = \WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = $testmode;
		\WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$key  = \WC_Stripe_Account::ACCOUNT_CACHE_KEY;
		$data = [ 'id' => "acct_$requested_mode" ];
		\WC_Stripe_Database_Cache::set_with_mode( $key, $data, 5, $requested_mode );
		delete_option( 'wcstripe_prefetch_' . $key );

		$mock_class = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'test_stub_callback' ] )
			->getMock();
		$mock_class->expects( $should_enqueue ? $this->once() : $this->never() )
			->method( 'test_stub_callback' )
			->with( null, \WC_Stripe_Database_Cache_Prefetch::ASYNC_PREFETCH_ACTION, [ $key ], 'woocommerce-gateway-stripe' )
			->willReturn( 1 );

		$prefetch_window = static function ( $window, $cache_key ) use ( $key ) {
			return $key === $cache_key ? 20 : $window;
		};
		add_filter( 'pre_as_enqueue_async_action', [ $mock_class, 'test_stub_callback' ], 10, 4 );
		add_filter( 'wc_stripe_database_cache_prefetch_window', $prefetch_window, 10, 2 );

		$result = \WC_Stripe_Database_Cache::get_with_mode( $key, $requested_mode );

		remove_filter( 'pre_as_enqueue_async_action', [ $mock_class, 'test_stub_callback' ], 10 );
		remove_filter( 'wc_stripe_database_cache_prefetch_window', $prefetch_window, 10 );
		$this->assertSame( $data, $result );
	}

	/**
	 * Provide test cases for {@see test_handle_prefetch_action()}.
	 *
	 * @return array
	 */
	public function provide_handle_prefetch_action_test_cases(): array {
		return [
			'pmc_key_exists_and_should_prefetch'                          => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, null, true ],
			'pmc_key_exists_and_should_prefetch_with_20_filter'           => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 20, true ],
			'pmc_key_exists_and_should_not_prefetch_with_0_filter'        => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 0, false ],
			'pmc_key_exists_and_should_not_prefetch_with_negative_filter' => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, -3, false ],
			'pmc_key_exists_and_should_prefetch_with_invalid_filter'      => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 'invalid', true ],
			'invalid_key_should_not_prefetch'                             => [ 'invalid_test_key', null, false ],
			'invalid_key_should_not_prefetch_with_filter'                 => [ 'invalid_test_key', 10, false ],
			'invalid_key_should_not_prefetch_with_0_filter'               => [ 'invalid_test_key', 0, false ],
			'invalid_key_should_not_prefetch_with_negative_filter'        => [ 'invalid_test_key', -3, false ],
			'invalid_key_should_not_prefetch_with_invalid_filter'         => [ 'invalid_test_key', 'invalid', false ],
		];
	}

	/**
	 * Test {@see \WC_Stripe_Database_Cache_Prefetch::handle_prefetch_action()}.
	 *
	 * @param string $key                         The key to prefetch.
	 * @param mixed $prefetch_window_filter_value The value to filter the prefetch window with. Null is no filter value set; other values will be returned as-is.
	 * @param bool $should_prefetch               Whether we expect the key to be prefetched.
	 *
	 * @dataProvider provide_handle_prefetch_action_test_cases
	 */
	public function test_handle_prefetch_action( string $key, $prefetch_window_filter_value, bool $should_prefetch ): void {
		$mock_instance = $this->getMockBuilder( 'WC_Stripe_Database_Cache_Prefetch' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'prefetch_cache_key' ] )
			->getMock();

		$filter_callback = null;
		if ( null !== $prefetch_window_filter_value ) {
			$filter_callback = function ( $prefetch_window, $cache_key ) use ( $key, $prefetch_window_filter_value ) {
				if ( $cache_key === $key ) {
					return $prefetch_window_filter_value;
				}
				return $prefetch_window;
			};
			add_filter( 'wc_stripe_database_cache_prefetch_window', $filter_callback, 10, 2 );
		}

		$expected_prefetch_count = $should_prefetch ? $this->once() : $this->never();

		$mock_instance->expects( $expected_prefetch_count )
			->method( 'prefetch_cache_key' )
			->with( $key )
			->willReturn( true );

		$mock_instance->handle_prefetch_action( $key );

		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_database_cache_prefetch_window', $filter_callback, 10 );
		}
	}

	/**
	 * Provide test cases for {@see test_maybe_queue_prefetch()}.
	 *
	 * @return array
	 */
	public function provide_maybe_queue_prefetch_test_cases(): array {
		return [
			'invalid_key_should_not_prefetch'                                                => [ 'invalid_test_key', 5, false ],
			'pmc_key_expires_in_60_seconds_should_not_prefetch'                              => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 60, false ],
			'pmc_key_expires_in_5_seconds_should_prefetch'                                   => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true ],
			'pmc_key_expires_in_5_seconds_with_option_set_2s_should_not_prefetch'            => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, 2 ],
			'pmc_key_expires_in_5_seconds_with_option_set_-2s_should_not_prefetch'           => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, -2 ],
			'pmc_key_expires_in_5_seconds_with_option_set_-11s_should_prefetch'              => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true, -11 ],
			'pmc_key_expires_in_5_seconds_with_invalid_option_should_prefetch'               => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true, 'invalid' ],
			'pmc_key_expires_in_5_seconds_with_20_filter_should_prefetch'                    => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true, null, 20 ],
			'pmc_key_expires_in_5_seconds_with_20_filter_option_-11_should_not_prefetch'     => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, -11, 20 ],
			'pmc_key_expires_in_5_seconds_with_0_filter_should_not_prefetch'                 => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, null, 0 ],
			'pmc_key_expires_in_5_seconds_with_negative_filter_should_not_prefetch'          => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, null, -3 ],
			'pmc_key_expires_in_5_seconds_with_invalid_filter_should_prefetch'               => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true, null, 'invalid' ],
			'account_key_expires_in_60_seconds_should_not_prefetch'                          => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 60, false ],
			'account_key_expires_in_5_seconds_should_not_prefetch'                           => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false ],
			'account_key_expires_in_5_seconds_with_20_filter_should_prefetch'                => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, true, null, 20 ],
			'account_key_expires_in_5_seconds_with_20_filter_option_-11_should_not_prefetch' => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, -11, 20 ],
			'account_key_expires_in_5_seconds_with_0_filter_should_not_prefetch'             => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, null, 0 ],
			'account_key_expires_in_5_seconds_with_negative_filter_should_not_prefetch'      => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, null, -3 ],
			'account_key_expires_in_5_seconds_with_invalid_filter_should_not_prefetch'       => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, null, 'invalid' ],
			'account_key_expires_in_5_seconds_with_option_set_2s_should_not_prefetch'        => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, 2 ],
			'account_key_expires_in_5_seconds_with_option_set_-2s_should_not_prefetch'       => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, -2 ],
			'account_key_expires_in_5_seconds_with_option_set_-11s_should_not_prefetch'      => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, -11 ],
		];
	}

	/**
	 * Test {@see \WC_Stripe_Database_Cache_Prefetch::maybe_queue_prefetch()}.
	 *
	 * @param string $key                 The key to prefetch.
	 * @param int $expiry_time_adjustment The adjustment in seconds to the expiry time of the cache entry. Computed relative to the current time when the test is run.
	 * @param bool $should_enqueue_action Whether we expect the action to be enqueued.
	 * @param mixed $option_adjusted_time The time to set the option to. Null is no value set; an integer will be treated as an adjustment from the runtime timestamp; any other value will be written to the option.
	 *
	 * @dataProvider provide_maybe_queue_prefetch_test_cases
	 */
	public function test_maybe_queue_prefetch( string $key, int $expiry_time_adjustment, bool $should_enqueue_action, $option_adjusted_time = null, $prefetch_window_filter_value = null ): void {
		$instance = \WC_Stripe_Database_Cache_Prefetch::get_instance();

		$mock_class = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'test_stub_callback' ] )
			->getMock();

		$mock_class->expects( $should_enqueue_action ? $this->once() : $this->never() )
			->method( 'test_stub_callback' )
			->with( null, \WC_Stripe_Database_Cache_Prefetch::ASYNC_PREFETCH_ACTION, [ $key ], 'woocommerce-gateway-stripe' )
			->willReturn( 1 );

		add_filter( 'pre_as_enqueue_async_action', [ $mock_class, 'test_stub_callback' ], 10, 4 );

		$filter_callback = null;
		if ( null !== $prefetch_window_filter_value ) {
			$filter_callback = function ( $prefetch_window, $cache_key ) use ( $key, $prefetch_window_filter_value ) {
				if ( $cache_key === $key ) {
					return $prefetch_window_filter_value;
				}
				return $prefetch_window;
			};
			add_filter( 'wc_stripe_database_cache_prefetch_window', $filter_callback, 10, 2 );
		}

		$option_name          = 'wcstripe_prefetch_' . $key;
		$initial_option_value = null;

		$start_time  = time();
		$expiry_time = $start_time + $expiry_time_adjustment;

		if ( null === $option_adjusted_time ) {
			delete_option( $option_name );
		} elseif ( is_int( $option_adjusted_time ) ) {
			$initial_option_value = $start_time + $option_adjusted_time;
		} else {
			$initial_option_value = $option_adjusted_time;
		}

		if ( null !== $initial_option_value ) {
			update_option( $option_name, $initial_option_value );
		}

		$instance->maybe_queue_prefetch( $key, $expiry_time );
		$end_time = time();

		remove_filter( 'pre_as_enqueue_async_action', [ $mock_class, 'test_stub_callback' ], 10 );
		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_database_cache_prefetch_window', $filter_callback, 10 );
		}

		$option_value = get_option( $option_name, false );

		delete_option( $option_name );

		if ( $should_enqueue_action ) {
			$this->assertIsInt( $option_value );
			$this->assertGreaterThanOrEqual( $start_time, $option_value );
			$this->assertLessThanOrEqual( $end_time, $option_value );
		} elseif ( null === $initial_option_value ) {
			$this->assertFalse( $option_value );
		} else {
			$this->assertEquals( $initial_option_value, $option_value );
		}
	}

	/**
	 * Provide test cases for {@see test_should_prefetch_cache_key()}.
	 *
	 * @return array
	 */
	public function provide_test_should_prefetch_cache_key_test_cases(): array {
		return [
			'invalid_key_should_not_prefetch'                      => [ 'invalid_test_key', false, null ],
			'pmc_key_should_prefetch_with_no_filter'               => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, true, null ],
			'pmc_key_should_not_prefetch_with_0_filter'            => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, false, 0 ],
			'pmc_key_should_prefetch_with_20_filter'               => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, true, 20 ],
			'pmc_key_should_not_prefetch_with_negative_filter'     => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, false, -5 ],
			'pmc_key_should_prefetch_with_invalid_filter'          => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, true, 'invalid' ],
			'account_key_should_not_prefetch_with_no_filter'       => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, false, null ],
			'account_key_should_not_prefetch_with_0_filter'        => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, false, 0 ],
			'account_key_should_prefetch_with_20_filter'           => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, true, 20 ],
			'account_key_should_not_prefetch_with_negative_filter' => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, false, -5 ],
			'account_key_should_not_prefetch_with_invalid_filter'  => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, false, 'invalid' ],
		];
	}

	/**
	 * Test {@see \WC_Stripe_Database_Cache_Prefetch::should_prefetch_cache_key()}.
	 *
	 * @param string $cache_key          The key to prefetch.
	 * @param bool $expected_result      The expected result.
	 * @param mixed $filter_return_value The value to return from the prefetch window filter. Null is no filter value returned; other values will be returned as-is.
	 *
	 * @dataProvider provide_test_should_prefetch_cache_key_test_cases
	 */
	public function test_should_prefetch_cache_key( string $cache_key, bool $expected_result, $filter_return_value = null ): void {
		$instance = \WC_Stripe_Database_Cache_Prefetch::get_instance();

		$filter_callback = null;
		if ( null !== $filter_return_value ) {
			$filter_callback = function ( $prefetch_window, $key ) use ( $cache_key, $filter_return_value ) {
				if ( $cache_key === $key ) {
					return $filter_return_value;
				}
				return $prefetch_window;
			};
			add_filter( 'wc_stripe_database_cache_prefetch_window', $filter_callback, 10, 2 );
		}

		$result = $instance->should_prefetch_cache_key( $cache_key );
		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_database_cache_prefetch_window', $filter_callback, 10 );
		}

		$this->assertEquals( $expected_result, $result );
	}
}
