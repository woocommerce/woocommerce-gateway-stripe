<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Short-lived mutual exclusion backed by a row in the options table.
 *
 * The lock row bypasses the options API: `add_option()` upserts behind a cached existence
 * check, so two concurrent callers can both succeed, and it offers no conditional update or
 * delete. Each holder stores `<timestamp>:<token>`. A lock abandoned for longer than its TTL
 * is reclaimed with a compare-and-swap on the exact stale value, so of two requests that read
 * the same expired lock only one wins, and a holder can never remove a lock it does not own.
 *
 * Callers pass the full option name; every consumer already had a stable name before this
 * class existed, and keeping those names lets a lock held by an older deploy stay respected
 * during an upgrade (a bare timestamp value parses as a lock with no token).
 */
final class WC_Stripe_Option_Lock {

	/**
	 * Attempts to acquire the lock.
	 *
	 * @param string $name The lock option name.
	 * @param int    $ttl  Seconds after which a lock still held is treated as abandoned and may be reclaimed.
	 * @return string|null The owner value to release the lock with, or null when another request holds it.
	 */
	public static function acquire( string $name, int $ttl ): ?string {
		global $wpdb;

		$owner = time() . ':' . wp_generate_uuid4();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
				$name,
				$owner
			)
		);
		if ( 1 === $inserted ) {
			self::forget_cached_option( $name );
			return $owner;
		}

		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( ! is_string( $current ) ) {
			return null;
		}

		$locked_at = (int) strtok( $current, ':' );
		if ( $locked_at > 0 && ( time() - $locked_at ) < $ttl ) {
			return null;
		}

		$reclaimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$owner,
				$name,
				$current
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( 1 !== $reclaimed ) {
			return null;
		}

		self::forget_cached_option( $name );
		return $owner;
	}

	/**
	 * Releases the lock, but only while the caller still owns it.
	 *
	 * @param string $name  The lock option name.
	 * @param string $owner The owner value returned by `acquire()`.
	 */
	public static function release( string $name, string $owner ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->options,
			[
				'option_name'  => $name,
				'option_value' => $owner,
			]
		);
		self::forget_cached_option( $name );
	}

	/**
	 * Removes the lock whoever holds it.
	 *
	 * Only for cleanup once the guarded resource is gone and no holder can act on it any more;
	 * on a live resource this hands the lock to the next caller while the holder is still working.
	 *
	 * @param string $name The lock option name.
	 */
	public static function force_release( string $name ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->options, [ 'option_name' => $name ] );
		self::forget_cached_option( $name );
	}

	/**
	 * Drops the options-API cache entries for the lock after a direct row write.
	 *
	 * The writes above bypass `add_option()`/`delete_option()`, so a caller that read the lock
	 * through `get_option()` earlier would keep seeing the cached value, or the cached miss in
	 * `notoptions`, under a persistent object cache.
	 *
	 * @param string $name The lock option name.
	 */
	private static function forget_cached_option( string $name ): void {
		wp_cache_delete( $name, 'options' );

		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ $name ] ) ) {
			unset( $notoptions[ $name ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
