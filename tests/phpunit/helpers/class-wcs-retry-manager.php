<?php
/**
 * Mock for the WCS_Retry_Manager static facade used by the Radar-block path
 * in WC_Stripe_Subscriptions_Trait::process_subscription_payment().
 *
 * This helper should ONLY be used for unit tests.
 *
 * Properties prefixed with `mock_` and methods prefixed with `mock_` are
 * test-only seams. The remaining static methods (is_retry_enabled(), store())
 * mirror the real WCS_Retry_Manager API that production code calls.
 */

/**
 * Mock for the static facade the trait uses.
 */
class WCS_Retry_Manager {
	/**
	 * Test-only toggle for whether retries are enabled. Not part of the real API.
	 *
	 * @var bool
	 */
	public static $mock_retry_enabled = true;

	/**
	 * Test-only seed for the retry returned by store()->get_last_retry_for_order().
	 * Set to null to simulate "no pending retry exists". Not part of the real API.
	 *
	 * @var WCS_Retry|null
	 */
	public static $mock_last_retry = null;

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
		return self::$mock_retry_enabled;
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
	 * Test-only helper to reset state between tests. Not part of the real API.
	 *
	 * @return void
	 */
	public static function mock_reset() {
		self::$mock_retry_enabled = true;
		self::$mock_last_retry    = null;
	}
}
