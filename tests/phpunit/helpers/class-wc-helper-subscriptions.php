<?php
/**
 * Subscriptions helpers.
 */

function wcs_get_subscription( $subscription ) {
	if ( ! WC_Subscriptions::$wcs_get_subscription ) {
		return;
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
	 * @var function
	 */
	public static $wcs_get_subscription = null;

	public static function set_wcs_get_subscription( $function ) {
		self::$wcs_get_subscription = $function;
	}
}
