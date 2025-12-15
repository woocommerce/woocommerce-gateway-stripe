<?php

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Class WC_Stripe_Database_Cache_Prefetch
 *
 * This class is responsible for prefetching cache keys.
 */
class WC_Stripe_Database_Cache_Prefetch {
	/**
	 * The action used for the asynchronous cache prefetch code.
	 *
	 * @var string
	 */
	public const ASYNC_PREFETCH_ACTION = 'wc_stripe_database_cache_prefetch_async';

	/**
	 * Configuration for cache prefetching.
	 *
	 * @var int[]
	 */
	protected const PREFETCH_CONFIG = [
		// Note that prefetching for account data is off by default.
		WC_Stripe_Account::ACCOUNT_CACHE_KEY                             => 0,
		WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY => 10,
	];

	/**
	 * The prefix used for prefetch tracking options.
	 *
	 * @var string
	 */
	private const PREFETCH_OPTION_PREFIX = 'wcstripe_prefetch_';

	/**
	 * The singleton instance.
	 */
	private static ?WC_Stripe_Database_Cache_Prefetch $instance = null;

	/**
	 * Static array to track pending prefetches which we have already queued up in the current request.
	 *
	 * @var bool[]
	 */
	private static array $pending_prefetches = [];

	/**
	 * Protected constructor to support singleton pattern.
	 */
	protected function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return WC_Stripe_Database_Cache_Prefetch The singleton instance.
	 */
	public static function get_instance(): WC_Stripe_Database_Cache_Prefetch {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if the unprefixed cache key has prefetch enabled.
	 *
	 * @param string $key The unprefixed cache key to check.
	 * @return bool True if the cache key can be prefetched, false otherwise.
	 */
	public function should_prefetch_cache_key( string $key ): bool {
		return $this->get_prefetch_window( $key ) > 0;
	}

	/**
	 * Maybe queue a prefetch for a cache key.
	 *
	 * @param string $key         The unprefixed cache key to prefetch.
	 * @param int    $expiry_time The expiry time of the cache entry.
	 */
	public function maybe_queue_prefetch( string $key, int $expiry_time ): void {
		$prefetch_window = $this->get_prefetch_window( $key );
		if ( 0 === $prefetch_window ) {
			return;
		}

		// If now plus the prefetch window is before the expiry time, do not trigger a prefetch.
		if ( ( time() + $prefetch_window ) < $expiry_time ) {
			return;
		}

		$logging_context = [
			'cache_key' => $key,
			'expiry_time' => $expiry_time,
		];

		if ( $this->is_prefetch_queued( $key ) || isset( self::$pending_prefetches[ $key ] ) ) {
			// Only log a message once per key per request.
			if ( ! isset( self::$pending_prefetches[ $key ] ) ) {
				WC_Stripe_Logger::debug( 'Cache prefetch already pending', $logging_context );
				self::$pending_prefetches[ $key ] = true;
			}
			return;
		}

		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			WC_Stripe_Logger::debug( 'Unable to enqueue cache prefetch: Action Scheduler is not initialized or available', $logging_context );
			return;
		}

		$prefetch_option_key = $this->get_prefetch_option_name( $key );

		$result = as_enqueue_async_action( self::ASYNC_PREFETCH_ACTION, [ $key ], 'woocommerce-gateway-stripe' );
		if ( 0 === $result ) {
			WC_Stripe_Logger::warning( 'Failed to enqueue cache prefetch', $logging_context );
		} else {
			update_option( $prefetch_option_key, time() );
			self::$pending_prefetches[ $key ] = true;
			WC_Stripe_Logger::debug( 'Enqueued cache prefetch', $logging_context );
		}
	}

	/**
	 * Reset the pending prefetches.
	 *
	 * @return void
	 */
	public function reset_pending_prefetches(): void {
		self::$pending_prefetches = [];
	}

	/**
	 * Get the prefetch window for a given cache key.
	 *
	 * @param string $key The unprefixed cache key to get the prefetch window for.
	 * @return int The prefetch window for the cache key. 0 indicates that prefetching is disabled for the key.
	 */
	private function get_prefetch_window( string $cache_key ): int {
		if ( ! isset( self::PREFETCH_CONFIG[ $cache_key ] ) ) {
			return 0;
		}

		$initial_prefetch_window = self::PREFETCH_CONFIG[ $cache_key ];

		/**
		 * Filters the cache prefetch window for a given cache key. Return 0 or less to disable prefetching for the key.
		 *
		 * @since 10.2.0
		 * @param int    $prefetch_window The prefetch window for the cache key.
		 * @param string $cache_key       The unprefixed cache key.
		 */
		$prefetch_window = apply_filters( 'wc_stripe_database_cache_prefetch_window', $initial_prefetch_window, $cache_key );

		// If the filter returns a non-integer, use the initial prefetch window.
		if ( ! is_int( $prefetch_window ) ) {
			return $initial_prefetch_window;
		}

		if ( $prefetch_window <= 0 ) {
			return 0;
		}

		return $prefetch_window;
	}

	/**
	 * Check if a prefetch is already queued up.
	 *
	 * @param string $key The unprefixed cache key to check.
	 * @return bool True if a prefetch is queued up, false otherwise.
	 */
	private function is_prefetch_queued( string $key ): bool {
		$prefetch_window = $this->get_prefetch_window( $key );
		if ( 0 === $prefetch_window ) {
			return false;
		}

		$prefetch_option_key = $this->get_prefetch_option_name( $key );

		$prefetch_option = get_option( $prefetch_option_key, false );
		// We use ctype_digit() and the (string) cast to ensure we handle the option value being returned as a string.
		if ( ! ctype_digit( (string) $prefetch_option ) ) {
			return false;
		}

		$now = time();

		if ( $prefetch_option >= ( $now - $prefetch_window ) ) {
			// If the prefetch entry expires in the future, or falls within the prefetch window for the key, we should consider the item live and queued.
			// We use a prefetch window buffer to account for latency on the prefetch processing and to make sure we don't prefetch more than once during the prefetch window.
			return true;
		}

		return false;
	}

	/**
	 * Get the name of the prefetch tracking option for a given cache key.
	 *
	 * @param string $key The unprefixed cache key to get the option name for.
	 * @return string The name of the prefetch option.
	 */
	private function get_prefetch_option_name( string $key ): string {
		return self::PREFETCH_OPTION_PREFIX . $key;
	}

	/**
	 * Handle the prefetch action. We are generally expecting this to be queued up by Action Scheduler using
	 * the action from {@see ASYNC_PREFETCH_ACTION}.
	 *
	 * @param string $key The unprefixed cache key to prefetch.
	 * @return void
	 */
	public function handle_prefetch_action( $key ): void {
		if ( ! is_string( $key ) || empty( $key ) ) {
			WC_Stripe_Logger::warning(
				'Invalid cache prefetch key',
				[
					'cache_key' => $key,
					'reason'    => 'invalid_cache_key',
				]
			);
			return;
		}

		// Specifically check PREFETCH_CONFIG to identify supported cache keys.
		if ( ! isset( self::PREFETCH_CONFIG[ $key ] ) ) {
			WC_Stripe_Logger::warning(
				'Invalid cache prefetch key',
				[
					'cache_key' => $key,
					'reason'    => 'unsupported_cache_key',
				]
			);
			return;
		}

		$prefetch_window = $this->get_prefetch_window( $key );
		if ( 0 === $prefetch_window ) {
			WC_Stripe_Logger::warning(
				'Cache prefetch key was disabled',
				[
					'cache_key' => $key,
					'reason'    => 'cache_key_disabled',
				]
			);
			return;
		}

		$this->prefetch_cache_key( $key );

		// Regardless of whether the prefetch was successful or not, we should remove the prefetch tracking option.
		delete_option( $this->get_prefetch_option_name( $key ) );
	}

	/**
	 * Helper method to implement prefetch/repopulation for supported cache entries.
	 *
	 * @param string $key The unprefixed cache key to prefetch.
	 * @return bool|null True if the prefetch was successful, false if the prefetch failed, or null if the prefetch was not attempted.
	 */
	protected function prefetch_cache_key( string $key ): ?bool {
		$prefetched = null;

		switch ( $key ) {
			case WC_Stripe_Account::ACCOUNT_CACHE_KEY:
				$account_data = WC_Stripe::get_instance()->account->get_cached_account_data( null, true );
				$prefetched = ! empty( $account_data );
				break;
			case WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY:
				if ( WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
					WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( true );
					$prefetched = true;
				} else {
					$prefetched = false;
					WC_Stripe_Logger::debug( 'Unable to prefetch PMC cache as settings sync is disabled', [ 'cache_key' => $key ] );
				}
				break;
			default:
				break;
		}

		if ( true === $prefetched ) {
			WC_Stripe_Logger::debug( 'Successfully prefetched cache key', [ 'cache_key' => $key ] );
		} elseif ( null === $prefetched ) {
			WC_Stripe_Logger::warning( 'Prefetch cache key not handled', [ 'cache_key' => $key ] );
		} else {
			WC_Stripe_Logger::debug( 'Failed to prefetch cache key', [ 'cache_key' => $key ] );
		}

		return $prefetched;
	}
}
