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
		return isset( self::PREFETCH_CONFIG[ $key ] ) && self::PREFETCH_CONFIG[ $key ] > 0;
	}

	/**
	 * Maybe queue a prefetch for a cache key.
	 *
	 * @param string $key         The unprefixed cache key to prefetch.
	 * @param int    $expiry_time The expiry time of the cache entry.
	 */
	public function maybe_queue_prefetch( string $key, int $expiry_time ): void {
		if ( ! $this->should_prefetch_cache_key( $key ) ) {
			return;
		}

		$prefetch_window = self::PREFETCH_CONFIG[ $key ];

		// If now plus the prefetch window is before the expiry time, do not trigger a prefetch.
		if ( ( time() + $prefetch_window ) < $expiry_time ) {
			return;
		}

		$logging_context = [
			'cache_key' => $key,
			'expiry_time' => $expiry_time,
		];

		if ( $this->is_prefetch_queued( $key ) ) {
			WC_Stripe_Logger::debug( 'Cache prefetch already pending', $logging_context );
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
			WC_Stripe_Logger::debug( 'Enqueued cache prefetch', $logging_context );
		}
	}

	/**
	 * Check if a prefetch is already queued up.
	 *
	 * @param string $key The unprefixed cache key to check.
	 * @return bool True if a prefetch is queued up, false otherwise.
	 */
	private function is_prefetch_queued( string $key ): bool {
		if ( ! isset( self::PREFETCH_CONFIG[ $key ] ) ) {
			return false;
		}

		$prefetch_option_key = $this->get_prefetch_option_name( $key );

		$prefetch_option = get_option( $prefetch_option_key, false );
		// We use ctype_digit() and the (string) cast to ensure we handle the option value being returned as a string.
		if ( ! ctype_digit( (string) $prefetch_option ) ) {
			return false;
		}

		$now             = time();
		$prefetch_window = self::PREFETCH_CONFIG[ $key ];

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
					'reason'    => 'invalid_key',
				]
			);
			return;
		}

		if ( ! $this->should_prefetch_cache_key( $key ) ) {
			WC_Stripe_Logger::warning(
				'Invalid cache prefetch key',
				[
					'cache_key' => $key,
					'reason'    => 'unsupported_cache_key',
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
