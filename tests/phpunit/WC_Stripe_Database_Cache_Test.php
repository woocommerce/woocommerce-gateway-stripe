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
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$key_prefix = 'wcstripe_cache_' . ( WC_Stripe_Mode::is_test() ? 'test_' : 'live_' );
		// Update the in-memory-cache to simulate expiration.
		$reflection = new ReflectionClass( 'WC_Stripe_Database_Cache' );
		$property = $reflection->getProperty( 'in_memory_cache' );
		$property->setAccessible( true );
		$in_memory_cache = $property->getValue();

		$cached_keys = array_keys( $in_memory_cache );
		foreach ( $cached_keys as $key ) {
			// The key is prefixed with "wcstripe_cache_[mode]_", so we need to remove it to get the original key.
			// This change ensures that we're properly cleaning up the cache by using the correct key format that
			// the WC_Stripe_Database_Cache::delete() method expects.
			$key_without_prefix = str_replace( $key_prefix, '', $key );
			WC_Stripe_Database_Cache::delete( $key_without_prefix );
		}

		parent::tearDown();
	}
}
