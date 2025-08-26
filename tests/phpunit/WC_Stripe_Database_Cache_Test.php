<?php

namespace WooCommerce\Stripe\Tests;

use ReflectionClass;
use WC_Stripe_Database_Cache;
use WC_Stripe_Mode;
use WP_UnitTestCase;

/**
 * Tests for the WC_Stripe_Database_Cache class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Database_Cache
 *
 * WC_Stripe_Database_Cache_Test Class.
 */
class WC_Stripe_Database_Cache_Test extends WP_UnitTestCase {

	/**
	 * Test setting and getting a value from cache.
	 */
	public function test_set_and_get_cache() {
		$key  = 'test_key';
		$data = 'test_data';

		WC_Stripe_Database_Cache::set( $key, $data );
		$result = WC_Stripe_Database_Cache::get( $key );

		$this->assertEquals( $data, $result );
	}

	/**
	 * Test cache expiration.
	 */
	public function test_cache_expiration() {
		$key_prefix = 'wcstripe_cache_' . ( WC_Stripe_Mode::is_test() ? 'test_' : 'live_' );
		$key  = 'expiring_key';
		$data = 'expiring_data';

		// Set cache with 1 hour TTL.
		WC_Stripe_Database_Cache::set( $key, $data, HOUR_IN_SECONDS );

		// Should be available immediately.
		$this->assertEquals( $data, WC_Stripe_Database_Cache::get( $key ) );

		// Update the in-memory-cache to simulate expiration.
		$reflection = new ReflectionClass( 'WC_Stripe_Database_Cache' );
		$property = $reflection->getProperty( 'in_memory_cache' );
		$property->setAccessible( true );
		$in_memory_cache = $property->getValue();

		$in_memory_cache[ $key_prefix . $key ]['updated'] -= HOUR_IN_SECONDS + 1; // Set update time to 1h 1s ago.
		$property->setValue( null, $in_memory_cache );

		// Should be expired.
		$this->assertNull( WC_Stripe_Database_Cache::get( $key ) );

		// Remove the in-memory-cache objects.
		$property->setValue( null, [] );

		// Update the database option to simulate expiration.
		$cache_contents = get_option( $key_prefix . $key );
		$cache_contents['updated'] -= HOUR_IN_SECONDS + 1; // Set update time to 1h 1s ago.
		update_option( $key_prefix . $key, $cache_contents );

		// Should be expired.
		$this->assertNull( WC_Stripe_Database_Cache::get( $key ) );
	}

	/**
	 * Test deleting from cache.
	 */
	public function test_delete_cache() {
		$key  = 'delete_key';
		$data = 'delete_data';

		// Set and verify data is in cache.
		WC_Stripe_Database_Cache::set( $key, $data );
		$this->assertEquals( $data, WC_Stripe_Database_Cache::get( $key ) );

		// Delete and verify data is removed.
		WC_Stripe_Database_Cache::delete( $key );
		$this->assertNull( WC_Stripe_Database_Cache::get( $key ) );
	}

	/**
	 * Test in-memory cache.
	 */
	public function test_in_memory_cache() {
		$key_prefix = 'wcstripe_cache_' . ( WC_Stripe_Mode::is_test() ? 'test_' : 'live_' );
		$key  = 'memory_key';
		$data = 'memory_data';

		// Set data.
		WC_Stripe_Database_Cache::set( $key, $data );

		// Get data once.
		$result1 = WC_Stripe_Database_Cache::get( $key );

		// Modify the option directly to check the second read in the same process uses the in-memory cache.
		update_option( $key_prefix . $key, null );

		// Get data twice - second call should use in-memory cache.
		$result2 = WC_Stripe_Database_Cache::get( $key );

		$this->assertEquals( $data, $result1 );
		$this->assertEquals( $data, $result2 );
	}

	/**
	 * Data provider for {@see test_cache_with_data_type()}.
	 *
	 * @return array Array of test cases.
	 */
	public function provide_data_type_test_cases() {
		return [
			'string'        => [ 'string', 'test string' ],
			'integer'       => [ 'integer', 123 ],
			'array'         => [ 'array', [ 'key' => 'value' ] ],
			'object'        => [ 'object', (object) [ 'property' => 'value' ] ],
			'boolean_true'  => [ 'boolean', true ],
			'boolean_false' => [ 'boolean', false ],
			'null'          => [ 'null', null ],
		];
	}

	/**
	 * Test cache with different data types.
	 *
	 * @dataProvider provide_data_type_test_cases
	 */
	public function test_cache_with_different_data_types( $key_suffix, $data ) {
		$key    = "test_{$key_suffix}";
		WC_Stripe_Database_Cache::set( $key, $data );
		$result = WC_Stripe_Database_Cache::get( $key );
		$this->assertEquals( $data, $result, 'Failed to cache and fetch data type' );
	}

	/**
	 * Test cache with custom TTL.
	 */
	public function test_custom_ttl() {
		$key  = 'custom_ttl_key';
		$data = 'custom_ttl_data';
		$ttl  = HOUR_IN_SECONDS * 5; // 5 hours.

		WC_Stripe_Database_Cache::set( $key, $data, $ttl );

		// Should be available immediately.
		$this->assertEquals( $data, WC_Stripe_Database_Cache::get( $key ) );

		// Force expiration using the filter.
		add_filter( 'wc_stripe_database_cache_is_expired', '__return_true' );

		// Should be expired.
		$this->assertNull( WC_Stripe_Database_Cache::get( $key ) );

		// Remove the filter.
		remove_filter( 'wc_stripe_database_cache_is_expired', '__return_true' );
	}

	/**
	 * Test cache with non-existent key.
	 */
	public function test_non_existent_key() {
		$this->assertNull( WC_Stripe_Database_Cache::get( 'non_existent_key' ) );
	}

	/**
	 * Data provider for {@see test_delete_stale_entries()}.
	 *
	 * @return array Array of test cases.
	 */
	public function provide_delete_stale_entries_test_cases() {
		return [
			'only_valid_test_and_live_entries' => [
				'expected_processed' => 4,
				'expected_deleted_keys' => [],
				'expected_more_entries' => false,
				'expected_last_key' => 'wcstripe_cache_test_test_key_2',
				'valid_entries' => [
					'wcstripe_cache_test_test_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_live_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_test_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_live_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [],
				'max_rows' => 500,
				'last_key' => null,
			],
			'partially_valid_test_and_live_entries' => [
				'expected_processed' => 8,
				'expected_deleted_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'expected_more_entries' => false,
				'expected_last_key' => 'wcstripe_cache_test_valid_key_2',
				'valid_entries' => [
					'wcstripe_cache_test_valid_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_valid_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_valid_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_valid_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'max_rows' => 500,
				'last_key' => null,
			],
			'only_stale_entries' => [
				'expected_processed' => 4,
				'expected_deleted_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'expected_more_entries' => false,
				'expected_last_key' => 'wcstripe_cache_test_stale_key_2',
				'valid_entries' => [],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'max_rows' => 500,
				'last_key' => null,
			],
			'partially_valid_and_stale_entries_with_initial_key' => [
				'expected_processed' => 5,
				'expected_deleted' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
				],
				'expected_more_entries' => false,
				'expected_last_key' => 'wcstripe_cache_test_valid_key_2',
				'valid_entries' => [
					'wcstripe_cache_test_valid_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_valid_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_valid_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_valid_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'max_rows' => 500,
				'last_key' => 'wcstripe_cache_live_valid_key_1',
			],
			'partially_valid_and_stale_entries_with_small_max_rows' => [
				'expected_processed' => 4,
				'expected_deleted_keys' => [
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_live_stale_key_2',
				],
				'expected_more_entries' => true,
				'expected_last_key' => 'wcstripe_cache_live_valid_key_2',
				'valid_entries' => [
					'wcstripe_cache_test_valid_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_valid_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_valid_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_valid_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'max_rows' => 4,
				'last_key' => null,
			],
		];
	}

	/**
	 * Generate a valid cache entry.
	 *
	 * @param array $data The data to store in the cache.
	 * @param int   $ttl  The TTL for the cache entry.
	 * @return array The cache entry.
	 */
	protected function generate_valid_cache_entry( array $data, int $ttl = 300 ): array {
		return [
			'updated' => time(),
			'ttl'     => $ttl,
			'data'    => $data,
		];
	}

	/**
	 * Tests {@see WC_Stripe_Database_Cache::delete_stale_entries()}.
	 *
	 * @dataProvider provide_delete_stale_entries_test_cases
	 * @param int         $expected_processed    The expected number of entries processed.
	 * @param string[]    $expected_deleted_keys The expected keys of entries that should be deleted.
	 * @param bool        $expected_more_entries The expected value of the more_entries key.
	 * @param string|null $expected_last_key     The expected last key value.
	 * @param array       $valid_entries         The valid entries to set, specified using complete option name and value data, including ttl and updated keys.
	 * @param string[]    $stale_entry_keys      The stale entry keys to generate. Keys should be complete option values.
	 * @param int         $max_rows              The maximum number of rows to process.
	 * @param string|null $last_key              The last key value to start with.
	 */
	public function test_delete_stale_entries( int $expected_processed, array $expected_deleted_keys, bool $expected_more_entries, ?string $expected_last_key = null, array $valid_entries = [], array $stale_entry_keys = [], int $max_rows = 500, ?string $last_key = null ): void {
		foreach ( $valid_entries as $valid_entry_key => $valid_entry_value ) {
			update_option( $valid_entry_key, $valid_entry_value );
		}

		$this->generate_stale_cache_entries( $stale_entry_keys );

		$result = WC_Stripe_Database_Cache::delete_stale_entries( $max_rows, $last_key );

		$this->assertEquals( $expected_processed, $result['processed'] );
		$this->assertEquals( count( $expected_deleted_keys ), $result['deleted'] );
		$this->assertEquals( $expected_last_key, $result['last_key'] );
		$this->assertEquals( $expected_more_entries, $result['more_entries'] );

		foreach ( $valid_entries as $valid_entry_key => $valid_entry_value ) {
			$this->assertEquals( $valid_entry_value, get_option( $valid_entry_key ) );
		}

		foreach ( $stale_entry_keys as $stale_entry_key ) {
			if ( in_array( $stale_entry_key, $expected_deleted_keys, true ) ) {
				$this->assertFalse( get_option( $stale_entry_key ) );
			} else {
				$this->assertNotFalse( get_option( $stale_entry_key ) );
			}
		}
	}

	/**
	 * Data provider for {@see test_delete_all_stale_entries()}.
	 *
	 * @return array Array of test cases.
	 */
	public function provide_delete_all_stale_entries_test_cases() {
		return [
			'only_valid_test_and_live_entries_inline' => [
				'expected_processed' => 4,
				'expected_deleted_keys' => [],
				'expected_error' => null,
				'approach' => WC_Stripe_Database_Cache::CLEANUP_APPROACH_INLINE,
				'max_rows' => 500,
				'valid_entries' => [
					'wcstripe_cache_test_test_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_live_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_test_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_live_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [],
			],
			'invalid_approach_returns_error' => [
				'expected_processed' => 0,
				'expected_deleted_keys' => [],
				'expected_error' => new \WP_Error( 'invalid_approach', 'Invalid approach' ),
				'approach' => 'invalid_approach',
				'max_rows' => 500,
			],
			'valid_and_stale_test_and_live_entries_inline' => [
				'expected_processed' => 8,
				'expected_deleted_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'expected_error' => null,
				'approach' => WC_Stripe_Database_Cache::CLEANUP_APPROACH_INLINE,
				'max_rows' => 500,
				'valid_entries' => [
					'wcstripe_cache_test_test_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_live_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_test_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_live_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
			],
			'valid_and_stale_test_and_live_entries_inline_with_max_rows' => [
				'expected_processed' => 8,
				'expected_deleted_keys' => [
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_live_stale_key_2',
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
				],
				'expected_error' => null,
				'approach' => WC_Stripe_Database_Cache::CLEANUP_APPROACH_INLINE,
				'max_rows' => 4,
				'valid_entries' => [
					'wcstripe_cache_test_test_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_live_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_test_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_live_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
			],
			'valid_and_stale_test_and_live_entries_async' => [
				'expected_processed' => 0,
				'expected_deleted_keys' => [],
				'expected_error' => null,
				'approach' => WC_Stripe_Database_Cache::CLEANUP_APPROACH_ASYNC,
				'max_rows' => 500,
				'valid_entries' => [
					'wcstripe_cache_test_test_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_live_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_test_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_live_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
			],
			'valid_and_stale_test_and_live_entries_async_with_queue_error' => [
				'expected_processed' => 0,
				'expected_deleted_keys' => [],
				'expected_error' => new \WP_Error( 'failed_to_enqueue_async_action', 'Failed to enqueue async action' ),
				'approach' => WC_Stripe_Database_Cache::CLEANUP_APPROACH_ASYNC,
				'max_rows' => 500,
				'valid_entries' => [
					'wcstripe_cache_test_test_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test123' ] ),
					'wcstripe_cache_live_live_key_1' => $this->generate_valid_cache_entry( [ 'test' => 'test321' ] ),
					'wcstripe_cache_test_test_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test456' ] ),
					'wcstripe_cache_live_live_key_2' => $this->generate_valid_cache_entry( [ 'test' => 'test654' ] ),
				],
				'stale_entry_keys' => [
					'wcstripe_cache_test_stale_key_1',
					'wcstripe_cache_live_stale_key_1',
					'wcstripe_cache_test_stale_key_2',
					'wcstripe_cache_live_stale_key_2',
				],
				'as_queue_error' => true,
			],
		];
	}

	/**
	 * Tests {@see WC_Stripe_Database_Cache::delete_all_stale_entries()}.
	 *
	 * @dataProvider provide_delete_all_stale_entries_test_cases
	 */
	public function test_delete_all_stale_entries( int $expected_processed, array $expected_deleted_keys, ?\WP_Error $expected_error = null, string $approach = '', int $max_rows = 500, array $valid_entries = [], array $stale_entry_keys = [], bool $as_queue_error = false ) {
		foreach ( $valid_entries as $valid_entry_key => $valid_entry_value ) {
			update_option( $valid_entry_key, $valid_entry_value );
		}

		$this->generate_stale_cache_entries( $stale_entry_keys );

		if ( WC_Stripe_Database_Cache::CLEANUP_APPROACH_ASYNC === $approach ) {
			$action_scheduler_filter = function ( $mock_result, $hook, $args, $group ) use ( $max_rows, $as_queue_error ) {
				if ( WC_Stripe_Database_Cache::ASYNC_CLEANUP_ACTION === $hook && 'woocommerce-gateway-stripe' === $group && is_array( $args ) && 1 === count( $args ) && $max_rows === $args[0] ) {
					if ( $as_queue_error ) {
						return 0;
					}
					return 1;
				}
				return $mock_result;
			};
			add_filter( 'pre_as_enqueue_async_action', $action_scheduler_filter, 10, 4 );
		}

		$result = WC_Stripe_Database_Cache::delete_all_stale_entries( $approach, $max_rows );

		$this->assertEquals( $expected_processed, $result['processed'] );
		$this->assertEquals( count( $expected_deleted_keys ), $result['deleted'] );

		if ( null === $expected_error ) {
			$this->assertNull( $result['error'] );
		} else {
			$this->assertEquals( $expected_error->get_error_code(), $result['error']->get_error_code() );
			$this->assertEquals( $expected_error->get_error_message(), $result['error']->get_error_message() );
		}

		foreach ( $valid_entries as $valid_entry_key => $valid_entry_value ) {
			$this->assertEquals( $valid_entry_value, get_option( $valid_entry_key ) );
		}

		foreach ( $stale_entry_keys as $stale_entry_key ) {
			if ( in_array( $stale_entry_key, $expected_deleted_keys, true ) ) {
				$this->assertFalse( get_option( $stale_entry_key ) );
			} else {
				$this->assertNotFalse( get_option( $stale_entry_key ) );
			}
		}
	}

	/**
	 * Helper function to generate stale cache entries.
	 *
	 * @param string[] $stale_entry_keys The keys of the stale entries to generate.
	 * @return void
	 */
	protected function generate_stale_cache_entries( array $stale_entry_keys ): void {
		$time          = time();
		$stale_counter = 0;
		foreach ( $stale_entry_keys as $stale_entry_key ) {
			$stale_counter++;
			$stale_data = [
				'updated' => $time - 1000,
				'ttl'     => 300,
				'data'    => [ 'test' => 'test' . $stale_counter ],
			];
			update_option( $stale_entry_key, $stale_data );
		}
	}

	/**
	 * Remove all cache entries before running the tests.
	 */
	public static function setUpBeforeClass(): void {
		self::remove_all_cache_entries();

		parent::setUpBeforeClass();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		self::remove_all_cache_entries();

		parent::tearDown();
	}

	protected static function remove_all_cache_entries(): void {
		$reflection = new ReflectionClass( 'WC_Stripe_Database_Cache' );
		$property   = $reflection->getProperty( 'in_memory_cache' );
		$property->setAccessible( true );
		$in_memory_cache = $property->getValue();

		$delete_function = $reflection->getMethod( 'delete_from_cache' );
		$delete_function->setAccessible( true );

		$cached_keys = array_keys( $in_memory_cache );
		foreach ( $cached_keys as $key ) {
			$delete_function->invoke( null, $key );
		}
	}
}
