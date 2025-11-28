<?php

namespace WooCommerce\Stripe\Tests;

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

		parent::tearDown();
	}

	/**
	 * Provide test cases for {@see test_handle_prefetch_action()}.
	 *
	 * @return array
	 */
	public function provide_handle_prefetch_action_test_cases(): array {
		return [
			'pmc_key_exists_and_should_prefetch' => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, true ],
			'invalid_key_should_not_prefetch'    => [ 'invalid_test_key', false ],
			'account_key_should_prefetch'        => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, true ],
		];
	}

	/**
	 * Test {@see \WC_Stripe_Database_Cache_Prefetch::handle_prefetch_action()}.
	 *
	 * @param string $key           The key to prefetch.
	 * @param bool $should_prefetch Whether we expect the key to be prefetched.
	 *
	 * @dataProvider provide_handle_prefetch_action_test_cases
	 */
	public function test_handle_prefetch_action( string $key, bool $should_prefetch ): void {
		$mock_instance = $this->getMockBuilder( 'WC_Stripe_Database_Cache_Prefetch' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'prefetch_cache_key' ] )
			->getMock();

		$expected_prefetch_count = $should_prefetch ? $this->once() : $this->never();

		$mock_instance->expects( $expected_prefetch_count )
			->method( 'prefetch_cache_key' )
			->with( $key )
			->willReturn( true );

		$mock_instance->handle_prefetch_action( $key );
	}

	/**
	 * Provide test cases for {@see test_maybe_queue_prefetch()}.
	 *
	 * @return array
	 */
	public function provide_maybe_queue_prefetch_test_cases(): array {
		return [
			'invalid_key_should_not_prefetch'                                          => [ 'invalid_test_key', 5, false ],
			'pmc_key_expires_in_60_seconds_should_not_prefetch'                        => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 60, false ],
			'pmc_key_expires_in_5_seconds_should_prefetch'                             => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true ],
			'pmc_key_expires_in_5_seconds_with_option_set_2s_should_not_prefetch'      => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, 2 ],
			'pmc_key_expires_in_5_seconds_with_option_set_-2s_should_not_prefetch'     => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, false, -2 ],
			'pmc_key_expires_in_5_seconds_with_option_set_-11s_should_prefetch'        => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true, -11 ],
			'pmc_key_expires_in_5_seconds_with_invalid_option_should_prefetch'         => [ \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 5, true, 'invalid' ],
			'account_key_expires_in_60_seconds_should_not_prefetch'                    => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 60, false ],
			'account_key_expires_in_5_seconds_should_prefetch'                         => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, true ],
			'account_key_expires_in_5_seconds_with_option_set_2s_should_not_prefetch'  => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, 2 ],
			'account_key_expires_in_5_seconds_with_option_set_-2s_should_not_prefetch' => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, false, -2 ],
			'account_key_expires_in_5_seconds_with_option_set_-11s_should_prefetch'    => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, true, -11 ],
			'account_key_expires_in_5_seconds_with_invalid_option_should_prefetch'     => [ \WC_Stripe_Account::ACCOUNT_CACHE_KEY, 5, true, 'invalid' ],
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
	public function test_maybe_queue_prefetch( string $key, int $expiry_time_adjustment, bool $should_enqueue_action, $option_adjusted_time = null ): void {
		$instance = \WC_Stripe_Database_Cache_Prefetch::get_instance();

		$mock_class = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'test_stub_callback' ] )
			->getMock();

		$mock_class->expects( $should_enqueue_action ? $this->once() : $this->never() )
			->method( 'test_stub_callback' )
			->with( null, \WC_Stripe_Database_Cache_Prefetch::ASYNC_PREFETCH_ACTION, [ $key ], 'woocommerce-gateway-stripe' )
			->willReturn( 1 );

		add_filter( 'pre_as_enqueue_async_action', [ $mock_class, 'test_stub_callback' ], 10, 4 );

		$test_args = [ $key, $expiry_time_adjustment, $should_enqueue_action, $option_adjusted_time ];

		$option_name          = 'wcstripe_prefetch_' . $key;
		$initial_option_value = null;

		$start_time  = time();
		$expiry_time = $start_time + $expiry_time_adjustment;

		if ( null == $option_adjusted_time ) {
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
}
