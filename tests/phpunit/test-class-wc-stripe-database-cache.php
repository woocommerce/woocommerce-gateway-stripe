<?php

/**
 * Tests for the WC_Stripe_Database_Cache class.
 *
 * @package WooCommerce_Stripe/Tests
 */

/**
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
		$in_memory_cache[ $key ]['updated'] -= HOUR_IN_SECONDS + 1; // Set update time to 1h 1s ago.
		$property->setValue( null, $in_memory_cache );

		// Should be expired.
		$this->assertNull( WC_Stripe_Database_Cache::get( $key ) );

		// Remove the in-memory-cache objects.
		$property->setValue( null, [] );

		// Update the database option to simulate expiration.
		$cache_contents = get_option( $key );
		$cache_contents['updated'] -= HOUR_IN_SECONDS + 1; // Set update time to 1h 1s ago.
		update_option( $key, $cache_contents );

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
		$key  = 'memory_key';
		$data = 'memory_data';

		// Set data.
		WC_Stripe_Database_Cache::set( $key, $data );

		// Get data once.
		$result1 = WC_Stripe_Database_Cache::get( $key );

		// Modify the option directly to check the second read in the same process uses the in-memory cache.
		update_option( $key, null );

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
		add_filter( 'wcstripe_database_cache_is_expired', '__return_true' );

		// Should be expired.
		$this->assertNull( WC_Stripe_Database_Cache::get( $key ) );

		// Remove the filter.
		remove_filter( 'wcstripe_database_cache_is_expired', '__return_true' );
	}

	/**
	 * Test cache with non-existent key.
	 */
	public function test_non_existent_key() {
		$this->assertNull( WC_Stripe_Database_Cache::get( 'non_existent_key' ) );
	}

	/**
	 * Tests the get_cached_keys method.
	 *
	 * @return void
	 */
	public function test_get_cached_keys() {
		// Initially there should be no cached keys
		$this->assertEmpty( WC_Stripe_Database_Cache::get_cached_keys() );

		// Add some test data to the cache
		WC_Stripe_Database_Cache::set( 'test_key_1', 'test_value_1' );
		WC_Stripe_Database_Cache::set( 'test_key_2', 'test_value_2' );

		// Get the cached keys
		$cached_keys = WC_Stripe_Database_Cache::get_cached_keys();

		// Verify we have the expected keys
		$this->assertCount( 2, $cached_keys );
		$this->assertContains( 'test_key_1', $cached_keys );
		$this->assertContains( 'test_key_2', $cached_keys );

		// Delete one key and verify it's removed from cached keys
		WC_Stripe_Database_Cache::delete( 'test_key_1' );
		$cached_keys = WC_Stripe_Database_Cache::get_cached_keys();
		$this->assertCount( 1, $cached_keys );
		$this->assertNotContains( 'test_key_1', $cached_keys );
		$this->assertContains( 'test_key_2', $cached_keys );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$cached_keys = WC_Stripe_Database_Cache::get_cached_keys();
		foreach ( $cached_keys as $key ) {
			WC_Stripe_Database_Cache::delete( $key );
		}

		parent::tearDown();
	}
}
