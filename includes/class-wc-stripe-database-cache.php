<?php

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Class WC_Stripe_Database_Cache
 */

/**
 * A class for caching data as an option in the database.
 *
 * Based on the WooCommerce Payments Database_Cache class implementation.
 *
 * @see https://github.com/Automattic/woocommerce-payments/blob/4b084af108cac9c6bd2467e52e5cdc3bc974a951/includes/class-database-cache.php
 */
class WC_Stripe_Database_Cache {

	/**
	 * In-memory cache for the duration of a single request.
	 *
	 * This is used to avoid multiple database reads for the same data and as a backstop in case the database write fails.
	 *
	 * @var array
	 */
	private static $in_memory_cache = [];

	/**
	 * The prefix used for every cache key.
	 *
	 * @var string
	 */
	const CACHE_KEY_PREFIX = 'wcstripe_cache_';

	/**
	 * Class constructor.
	 */
	private function __construct() {
	}

	/**
	 * Stores a value in the cache.
	 *
	 * The key is automatically prefixed with "wcstripe_cache_[mode]_".
	 *
	 * @param string $key  The key to store the value under.
	 * @param mixed  $data The value to store.
	 * @param int    $ttl  The TTL of the cache. Dafault 1 hour.
	 *
	 * @return void
	 */
	public static function set( $key, $data, $ttl = HOUR_IN_SECONDS ) {
		$prefixed_key = self::add_key_prefix( $key );
		self::write_to_cache( $prefixed_key, $data, $ttl );
	}

	/**
	 * Gets a value from the cache.
	 *
	 * The key is automatically prefixed with "wcstripe_cache_[mode]_".
	 *
	 * @param string $key The key to look for.
	 *
	 * @return mixed|null The cache contents. NULL if the cache value is expired or missing.
	 */
	public static function get( $key ) {
		$prefixed_key = self::add_key_prefix( $key );
		$cache_contents = self::get_from_cache( $prefixed_key );
		if ( is_array( $cache_contents ) && array_key_exists( 'data', $cache_contents ) ) {
			if ( self::is_expired( $prefixed_key, $cache_contents ) ) {
				return null;
			}

			return $cache_contents['data'];
		}

		return null;
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * The key is automatically prefixed with "wcstripe_cache_[mode]_".
	 *
	 * @param string $key The key to delete.
	 *
	 * @return void
	 */
	public static function delete( $key ) {
		$prefixed_key = self::add_key_prefix( $key );
		// Remove from the in-memory cache.
		unset( self::$in_memory_cache[ $prefixed_key ] );

		// Remove from the DB cache.
		if ( delete_option( $prefixed_key ) ) {
			// Clear the WP object cache to ensure the new data is fetched by other processes.
			wp_cache_delete( $prefixed_key, 'options' );
		}
	}

	/**
	 * Wraps the data in the cache metadata and stores it.
	 *
	 * @param string  $prefixed_key The key to store the data under (with prefix).
	 * @param mixed   $data         The data to store.
	 * @param int     $ttl          The TTL of the cache.
	 *
	 * @return void
	 */
	private static function write_to_cache( $prefixed_key, $data, $ttl ) {
		// Add the data and expiry time to the array we're caching.
		$cache_contents = [
			'data'    => $data,
			'ttl'     => $ttl,
			'updated' => time(),
		];

		// Write the in-memory cache.
		self::$in_memory_cache[ $prefixed_key ] = $cache_contents;

		// Create or update the DB option cache.
		// Note: Since we are adding the current time to the option value, WP will ALWAYS write the option because
		// the cache contents value is different from the current one, even if the data is the same.
		// A `false` result ONLY means that the DB write failed.
		// Yes, there is the possibility that we attempt to write the same data multiple times within the SAME second,
		// and we will mistakenly think that the DB write failed. We are OK with this false positive,
		// since the actual data is the same.
		//
		// Note 2: Autoloading too many options can lead to performance problems, and we are implementing this as a
		// general cache for the plugin, so we set the autoload to false.
		$result = update_option( $prefixed_key, $cache_contents, false );
		if ( false !== $result ) {
			// If the DB cache write succeeded, clear the WP object cache to ensure the new data is fetched by other processes.
			wp_cache_delete( $prefixed_key, 'options' );
		}
	}

	/**
	 * Get the cache contents for a certain key.
	 *
	 * @param string $prefixed_key The cache key (with prefix).
	 *
	 * @return array|false The cache contents (array with `data`, `ttl`, and `updated` entries).
	 *                     False if there is no cached data.
	 */
	private static function get_from_cache( $prefixed_key ) {
		// Check the in-memory cache first.
		if ( isset( self::$in_memory_cache[ $prefixed_key ] ) ) {
			return self::$in_memory_cache[ $prefixed_key ];
		}

		// Read from the DB cache.
		$data = get_option( $prefixed_key );

		// Store the data in the in-memory cache, including the case when there is no data cached (`false`).
		self::$in_memory_cache[ $prefixed_key ] = $data;

		return $data;
	}

	/**
	 * Checks if the cache value is expired.
	 *
	 * @param string $prefixed_key   The cache key (with prefix).
	 * @param array  $cache_contents The cache contents.
	 *
	 * @return boolean True if the contents are expired. False otherwise.
	 */
	private static function is_expired( $prefixed_key, $cache_contents ) {
		if ( ! is_array( $cache_contents ) || ! isset( $cache_contents['updated'] ) || ! isset( $cache_contents['ttl'] ) ) {
			// Treat bad/invalid cache contents as expired
			return true;
		}

		// Double-check that we have integers for `updated` and `ttl`.
		if ( ! is_int( $cache_contents['updated'] ) || ! is_int( $cache_contents['ttl'] ) ) {
			return true;
		}

		$expires = $cache_contents['updated'] + $cache_contents['ttl'];
		$now     = time();

		/**
		 * Filters the result of the database cache entry expiration check.
		 *
		 * @since 9.7.0
		 *
		 * @param bool   $is_expired Whether the cache is expired.
		 * @param string $prefixed_key The cache key (with prefix).
		 * @param array  $cache_contents The cache contents.
		 *
		 * @return bool Whether the cache is expired.
		 */
		return apply_filters( 'wc_stripe_database_cache_is_expired', $expires < $now, $prefixed_key, $cache_contents );
	}

	/**
	 * Adds the CACHE_KEY_PREFIX + plugin mode prefix to the key.
	 * Ex: "wcstripe_cache_[mode]_[key].
	 *
	 * @param string $key The key to add the prefix to.
	 *
	 * @return string The key with the prefix.
	 */
	private static function add_key_prefix( $key ) {
		$mode = WC_Stripe_Mode::is_test() ? 'test_' : 'live_';
		return self::CACHE_KEY_PREFIX . $mode . $key;
	}
}
