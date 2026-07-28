<?php
/**
 * Subscriptions helpers.
 */

/**
 * @param $subscription mixed Subscription ID or object.
 * @return WC_Subscription|false
 */
function wcs_get_subscription( $subscription ) {
	if ( ! WC_Subscriptions::$wcs_get_subscription ) {
		return false;
	}
	return ( WC_Subscriptions::$wcs_get_subscription )( $subscription );
}

/**
 * Class WC_Subscriptions.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscriptions {
	/**
	 * @var string
	 */
	public static $version = '6.3.2';

	/**
	 * wcs_get_subscription mock.
	 *
	 * @var callable
	 */
	public static $wcs_get_subscription = null;

	/**
	 * @param callable $callback Function to call when wcs_get_subscription is called.
	 * @return void
	 */
	public static function set_wcs_get_subscription( $callback ) {
		self::$wcs_get_subscription = $callback;
	}

	/**
	 * Stub: check if the current site is a duplicate/staging site.
	 *
	 * @return bool
	 */
	public static function is_duplicate_site(): bool {
		return false;
	}
}
