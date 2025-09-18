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
	 * The action used for the asynchronous cache cleanup code.
	 *
	 * @var string
	 */
	public const ASYNC_CLEANUP_ACTION = 'wc_stripe_database_cache_cleanup_async';

	/**
	 * The prefix used for every cache key.
	 *
	 * @var string
	 */
	public const CACHE_KEY_PREFIX = 'wcstripe_cache_';

	/**
	 * Cleanup approach that runs in the current process.
	 *
	 * @var string
	 */
	public const CLEANUP_APPROACH_INLINE = 'inline';

	/**
	 * Cleanup approach that runs asynchronously via Action Scheduler.
	 *
	 * @var string
	 */
	public const CLEANUP_APPROACH_ASYNC = 'async';

	/**
	 * Permitted/accepted approaches.
	 *
	 * @var string[]
	 */
	protected const CLEANUP_APPROACHES = [
		self::CLEANUP_APPROACH_INLINE,
		self::CLEANUP_APPROACH_ASYNC,
	];

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

			self::maybe_trigger_prefetch( $key, $cache_contents );

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

		self::delete_from_cache( $prefixed_key );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $prefixed_key The key to delete.
	 *
	 * @return void
	 */
	private static function delete_from_cache( string $prefixed_key ): void {
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
		if ( ! is_array( $cache_contents ) ) {
			// Treat bad/invalid cache contents as expired
			return true;
		}

		$expires = self::get_expiry_time( $cache_contents );
		if ( null === $expires ) {
			return true;
		}

		$now = time();

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
	 * Get the expiry time for a cache entry. Includes validation for time-related fields in the array.
	 *
	 * @param array $cache_contents The cache contents.
	 *
	 * @return int|null The expiry time as a timestamp. Null if the expiry time can't be determined.
	 */
	private static function get_expiry_time( array $cache_contents ): ?int {
		// If we don't have updated and ttl keys, expiry time is unknown.
		if ( ! isset( $cache_contents['updated'], $cache_contents['ttl'] ) ) {
			return null;
		}

		// If we don't have integers for updated and ttl, expiry time is unknown.
		if ( ! is_int( $cache_contents['updated'] ) || ! is_int( $cache_contents['ttl'] ) ) {
			return null;
		}

		return $cache_contents['updated'] + $cache_contents['ttl'];
	}

	/**
	 * Maybe trigger a cache prefetch.
	 *
	 * @param string $key            The unprefixed cache key.
	 * @param array  $cache_contents The cache contents.
	 *
	 * @return void
	 */
	private static function maybe_trigger_prefetch( string $key, array $cache_contents ): void {
		$prefetch = WC_Stripe_Database_Cache_Prefetch::get_instance();
		if ( ! $prefetch->should_prefetch_cache_key( $key ) ) {
			return;
		}

		$expires = self::get_expiry_time( $cache_contents );
		if ( null === $expires ) {
			return;
		}

		$prefetch->maybe_queue_prefetch( $key, $expires );
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

	/**
	 * Deletes stale entries from the cache.
	 *
	 * @param int         $max_rows The maximum number of entries to check. -1 will check all rows. 0 will do nothing. Default is 500.
	 * @param string|null $last_key The last key processed. If provided, the query will start from the next key. Allows for pagination.
	 * @return array {
	 *     @type bool        $more_entries True if more entries may exist. False if all rows have been processed.
	 *     @type string|null $last_key     The last key processed.
	 *     @type int         $processed    The number of entries processed.
	 *     @type int         $deleted      The number of entries deleted.
	 * }
	 */
	public static function delete_stale_entries( int $max_rows = 500, ?string $last_key = null ): array {
		global $wpdb;

		$result = [
			'more_entries' => false,
			'last_key'     => null,
			'processed'    => 0,
			'deleted'      => 0,
		];

		if ( 0 === $max_rows ) {
			return $result;
		}

		// We call prepare() below after building the components.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$raw_query  = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s";
		$query_args = [ self::CACHE_KEY_PREFIX . '%' ];

		if ( null !== $last_key ) {
			$raw_query .= ' AND option_name > %s';
			$query_args[] = $last_key;
		}

		$raw_query .= ' ORDER BY option_name ASC';

		if ( $max_rows > 0 ) {
			$raw_query .= ' LIMIT %d';
			$query_args[] = $max_rows;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cached_rows = $wpdb->get_results( $wpdb->prepare( $raw_query, ...$query_args ) );

		foreach ( $cached_rows as $cached_row ) {
			$result['last_key'] = $cached_row->option_name;
			$result['processed']++;

			// We fetched the raw contents, so check if we need to unserialize the data.
			$cache_contents = maybe_unserialize( $cached_row->option_value );

			if ( self::is_expired( $cached_row->option_name, $cache_contents ) ) {
				self::delete_from_cache( $cached_row->option_name );
				$result['deleted']++;
			}
		}

		if ( $max_rows > 0 && count( $cached_rows ) === $max_rows ) {
			$result['more_entries'] = true;
		}

		return $result;
	}

	/**
	 * Deletes all stale entries from the cache.
	 *
	 * @param string $approach The approach to use to delete the entries. {@see CLEANUP_APPROACH_INLINE} will delete the entries in the
	 *                         current process, and {@see CLEANUP_APPROACH_ASYNC} will enqueue an async job to delete the entries.
	 * @param int    $max_rows The maximum number of entries to check. -1 will check all rows. 0 will do nothing. Default is 500.
	 *
	 * @return array {
	 *     @type int           $processed The number of entries processed.
	 *     @type int           $deleted   The number of entries deleted.
	 *     @type WP_Error|null $error     Null if all is OK; WP_Error if there is an error.
	 * }
	 */
	public static function delete_all_stale_entries( string $approach, int $max_rows = 500 ): array {
		$result = [
			'processed' => 0,
			'deleted'   => 0,
			'error'     => null,
		];

		if ( ! in_array( $approach, self::CLEANUP_APPROACHES, true ) ) {
			$result['error'] = new WP_Error( 'invalid_approach', 'Invalid approach' );
			return $result;
		}

		if ( self::CLEANUP_APPROACH_INLINE === $approach ) {
			$has_more_entries = false;
			$last_key         = null;
			do {
				$delete_result = self::delete_stale_entries( $max_rows, $last_key );

				$last_key         = $delete_result['last_key'];
				$has_more_entries = $delete_result['more_entries'];

				$result['processed'] += $delete_result['processed'];
				$result['deleted']   += $delete_result['deleted'];
			} while ( $has_more_entries && null !== $last_key );
		} elseif ( self::CLEANUP_APPROACH_ASYNC === $approach ) {
			if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
				$result['error'] = new WP_Error( 'action_scheduler_not_initialized', 'Action Scheduler is not initialized' );
				return $result;
			}

			$enqueue_result = as_enqueue_async_action( self::ASYNC_CLEANUP_ACTION, [ $max_rows ], 'woocommerce-gateway-stripe' );

			if ( 0 === $enqueue_result ) {
				$result['error'] = new WP_Error( 'failed_to_enqueue_async_action', 'Failed to enqueue async action' );
			}
		}

		return $result;
	}

	/**
	 * Schedule a daily async cleanup of the Stripe database cache.
	 *
	 * @return void
	 */
	public static function maybe_schedule_daily_async_cleanup(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			WC_Stripe_Logger::debug( 'Unable to schedule daily asynchronous cache cleanup: Action Scheduler is not initialized' );
			return;
		}

		if ( as_has_scheduled_action( self::ASYNC_CLEANUP_ACTION, null ) ) {
			WC_Stripe_Logger::debug( 'Daily asynchronous cache cleanup already scheduled' );
			return;
		}

		$one_am_tomorrow = strtotime( 'tomorrow 01:00' );
		$schedule_id = as_schedule_recurring_action( $one_am_tomorrow, DAY_IN_SECONDS, self::ASYNC_CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' );

		if ( 0 === $schedule_id ) {
			WC_Stripe_Logger::error( 'Failed to schedule daily asynchronous cache cleanup' );
		} else {
			WC_Stripe_Logger::info( 'Scheduled daily asynchronous cache cleanup', [ 'schedule_id' => $schedule_id ] );
		}
	}

	/**
	 * Unschedule the daily async cleanup of the Stripe database cache.
	 *
	 * @return void
	 */
	public static function unschedule_daily_async_cleanup(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			WC_Stripe_Logger::debug( 'Unable to unschedule daily asynchronous cache cleanup: Action Scheduler is not initialized' );
			return;
		}

		as_unschedule_all_actions( self::ASYNC_CLEANUP_ACTION, null, 'woocommerce-gateway-stripe' );

		WC_Stripe_Logger::info( 'Unscheduled daily asynchronous cache cleanup' );
	}

	/**
	 * Deletes all stale entries from the cache asynchronously using Action Scheduler and the `wc_stripe_database_cache_cleanup_async` action.
	 *
	 * @param int   $max_rows The maximum number of entries to check. -1 will check all rows. 0 will do nothing. Default is 500.
	 * @param array $job_data Internal job data. Must not be provided when calling the function/action.
	 *
	 * @return void
	 */
	public static function delete_all_stale_entries_async( int $max_rows = 500, array $job_data = [] ): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			WC_Stripe_Logger::error( 'Unable to run cache cleanup asynchronously: Action Scheduler is not initialized' );
			return;
		}

		if ( ! isset( $job_data['run_id'] ) || ! is_int( $job_data['run_id'] ) ) {
			$job_data = [
				'run_id'     => rand( 1, 1000000 ),
				'processed'  => 0,
				'deleted'    => 0,
				'job_runs'   => 1,
				'last_key'   => null,
			];

			WC_Stripe_Logger::info(
				"Starting asynchronous cache cleanup [run_id: {$job_data['run_id']}]",
				[
					'max_rows' => $max_rows,
					'job_data' => $job_data,
				]
			);
		} elseif ( ! self::validate_stale_entries_async_job_data( $job_data ) ) {
			$run_id = $job_data['run_id'] ?? 'unknown';

			WC_Stripe_Logger::error(
				"Invalid job data. [run_id: {$run_id}]",
				[
					'max_rows' => $max_rows,
					'job_data' => $job_data,
				]
			);
			return;
		} else {
			WC_Stripe_Logger::info(
				"Continuing asynchronous cache cleanup [run_id: {$job_data['run_id']}]",
				[
					'max_rows' => $max_rows,
					'job_data' => $job_data,
				]
			);

			$job_data['job_runs']++;
		}

		$delete_result = self::delete_stale_entries( $max_rows, $job_data['last_key'] );

		$job_data['processed'] += $delete_result['processed'];
		$job_data['deleted']   += $delete_result['deleted'];
		$job_data['last_key']  = $delete_result['last_key'];

		if ( $delete_result['more_entries'] && null !== $delete_result['last_key'] ) {
			$job_delay = MINUTE_IN_SECONDS;

			WC_Stripe_Logger::info(
				"Asynchronous cache cleanup progress update [run_id: {$job_data['run_id']}]. Scheduling next run in {$job_delay} seconds.",
				[
					'max_rows'  => $max_rows,
					'job_data'  => $job_data,
				]
			);

			$schedule_result = as_schedule_single_action( time() + $job_delay, self::ASYNC_CLEANUP_ACTION, [ $max_rows, $job_data ], 'woocommerce-gateway-stripe' );

			if ( 0 === $schedule_result ) {
				WC_Stripe_Logger::error( "Failed to schedule next asynchronous cache cleanup run [run_id: {$job_data['run_id']}]", [ 'job_data' => $job_data ] );
			}

			return;
		}

		WC_Stripe_Logger::info(
			"Asynchronous cache cleanup complete: {$job_data['processed']} entries processed, {$job_data['deleted']} stale entries deleted [run_id: {$job_data['run_id']}]",
			[
				'max_rows' => $max_rows,
				'job_data' => $job_data,
			]
		);
	}

	/**
	 * Helper function to validate the job data for {@see delete_all_stale_entries_async()}.
	 *
	 * @param array $job_data The job data.
	 *
	 * @return bool True if the job data is valid. False otherwise.
	 */
	private static function validate_stale_entries_async_job_data( array $job_data ): bool {
		if ( ! isset( $job_data['run_id'] ) || ! is_int( $job_data['run_id'] ) ) {
			return false;
		}

		if ( ! isset( $job_data['processed'] ) || ! is_int( $job_data['processed'] ) ) {
			return false;
		}

		if ( ! isset( $job_data['deleted'] ) || ! is_int( $job_data['deleted'] ) ) {
			return false;
		}

		if ( ! isset( $job_data['last_key'] ) || ! is_string( $job_data['last_key'] ) ) {
			return false;
		}

		if ( ! isset( $job_data['job_runs'] ) || ! is_int( $job_data['job_runs'] ) ) {
			return false;
		}

		return true;
	}
}
