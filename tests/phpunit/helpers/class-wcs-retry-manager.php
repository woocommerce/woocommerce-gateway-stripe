<?php
/**
 * Mock for the WCS_Retry_Manager static facade used by the Radar-block path
 * in WC_Stripe_Subscriptions_Trait::process_subscription_payment().
 *
 * This helper should ONLY be used for unit tests.
 */

/**
 * Mock for the static facade the trait uses.
 */
class WCS_Retry_Manager {
	/**
	 * Whether retries are enabled (toggle this in tests).
	 *
	 * @var bool
	 */
	public static $retry_enabled = true;

	/**
	 * Retry returned by store()->get_last_retry_for_order(). Set to null to simulate
	 * "no pending retry exists".
	 *
	 * @var WCS_Retry|null
	 */
	public static $last_retry = null;

	/**
	 * Singleton store instance.
	 *
	 * @var WCS_Retry_Store_Mock|null
	 */
	private static $store = null;

	/**
	 * @return bool
	 */
	public static function is_retry_enabled() {
		return self::$retry_enabled;
	}

	/**
	 * @return WCS_Retry_Store_Mock
	 */
	public static function store() {
		if ( null === self::$store ) {
			self::$store = new WCS_Retry_Store_Mock();
		}
		return self::$store;
	}

	/**
	 * Reset state between tests.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$retry_enabled = true;
		self::$last_retry    = null;
	}
}
