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
	 * Action Scheduler hook for the daily removal of abandoned lock rows.
	 *
	 * @var string
	 */
	public const CLEANUP_ACTION = 'wc_stripe_option_lock_cleanup';

	/**
	 * Option names, or name prefixes, of every lock that uses this class.
	 *
	 * A lock row stays behind when its request dies before `release()` (a fatal error, a timeout, a
	 * killed process). Locks named per cart, session, or user are never acquired again under the
	 * same name, so nothing else would ever remove those rows. Add the prefix of any new lock here.
	 *
	 * @var string[]
	 */
	private const CLEANUP_PREFIXES = [
		'wc_stripe_agentic_sync_lock',
		'wc_stripe_checkout_session_lock_',
		'wc_stripe_user_customer_lock_',
	];

	/**
	 * Age in seconds after which a lock row counts as abandoned.
	 *
	 * Far above every lock TTL (minutes), so the cleanup can never remove a lock that is still in use.
	 *
	 * @var int
	 */
	private const CLEANUP_STALE_AFTER = DAY_IN_SECONDS;

	/**
	 * Maximum rows removed per cleanup run; a full batch queues another run.
	 *
	 * @var int
	 */
	private const CLEANUP_BATCH_SIZE = 500;

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
	 * Schedules the daily cleanup of abandoned lock rows, if it is not scheduled yet.
	 *
	 * @return void
	 */
	public static function maybe_schedule_daily_cleanup(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::CLEANUP_ACTION, null ) ) {
			return;
		}

		if ( 0 === as_schedule_recurring_action( strtotime( 'tomorrow 01:30' ), DAY_IN_SECONDS, self::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' ) ) {
			WC_Stripe_Logger::error( 'Failed to schedule the daily option lock cleanup.' );
		}
	}

	/**
	 * Unschedules the daily cleanup of abandoned lock rows.
	 *
	 * @return void
	 */
	public static function unschedule_daily_cleanup(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' );
	}

	/**
	 * Removes expired lock rows. Runs from the {@see self::CLEANUP_ACTION} action.
	 *
	 * @return void
	 */
	public static function cleanup_stale_locks(): void {
		$deleted = self::delete_stale_locks( self::CLEANUP_BATCH_SIZE );

		if ( $deleted > 0 ) {
			WC_Stripe_Logger::info( "Removed {$deleted} expired option lock rows." );
		}

		// A full batch may have left more rows behind; continue soon instead of waiting a day.
		if ( $deleted >= self::CLEANUP_BATCH_SIZE && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' );
		}
	}

	/**
	 * Deletes lock rows whose timestamp is older than {@see self::CLEANUP_STALE_AFTER}.
	 *
	 * Each row is deleted only if it still holds the value that was read, so a lock acquired again
	 * between the read and the delete is never removed.
	 *
	 * @param int $limit The maximum number of rows to delete.
	 * @return int The number of rows deleted.
	 */
	public static function delete_stale_locks( int $limit ): int {
		global $wpdb;

		$cutoff  = time() - self::CLEANUP_STALE_AFTER;
		$deleted = 0;

		foreach ( self::CLEANUP_PREFIXES as $prefix ) {
			if ( $deleted >= $limit ) {
				break;
			}

			// The value is `<timestamp>:<token>`, or a bare timestamp from an older deploy.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST( SUBSTRING_INDEX( option_value, ':', 1 ) AS UNSIGNED ) < %d LIMIT %d",
					$wpdb->esc_like( $prefix ) . '%',
					$cutoff,
					$limit - $deleted
				)
			);

			foreach ( $rows as $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$removed = $wpdb->delete(
					$wpdb->options,
					[
						'option_name'  => $row->option_name,
						'option_value' => $row->option_value,
					]
				);
				if ( $removed ) {
					self::forget_cached_option( $row->option_name );
					++$deleted;
				}
			}
		}

		return $deleted;
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
