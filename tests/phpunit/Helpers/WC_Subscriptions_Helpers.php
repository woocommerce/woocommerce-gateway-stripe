<?php
/**
 * WC Subscription function mocks
 */

/**
 * A function to mock wcs_get_subscriptions_for_order.
 *
 * @param WC_Order $order
 * @return array
 */
function wcs_get_subscriptions_for_order( $order ) {
	if ( ! WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order ) {
		return [];
	}

	return (array) WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order;
}

/**
 * A function to mock wcs_get_subscriptions.
 *
 * @param array $args A set of name value pairs to determine the return value.
 * @return array
 */
function wcs_get_subscriptions( $args ) {
	if ( ! WC_Subscriptions_Helpers::$wcs_get_subscriptions ) {
		return [];
	}

	return (array) WC_Subscriptions_Helpers::$wcs_get_subscriptions;
}

/**
 * A function to mock wcs_is_subscription.
 *
 * @param $object mixed The object to check.
 * @return bool|null
 */
function wcs_is_subscription( $object ) {
	if ( null === WC_Subscriptions_Helpers::$wcs_is_subscription ) {
		return false;
	}

	return WC_Subscriptions_Helpers::$wcs_is_subscription;
}

/**
 * A helper class for setting up mocks for WC_Subscriptions functions.
 */
class WC_Subscriptions_Helpers {
	/**
	 * Mock for wcs_get_subscriptions_for_order.
	 *
	 * @var array
	 */
	public static $wcs_get_subscriptions_for_order = null;

	/**
	 * Mock for wcs_get_subscriptions.
	 *
	 * @var array
	 */
	public static $wcs_get_subscriptions = null;

	/**
	 * Mock for wcs_is_subscription.
	 *
	 * @var bool|null
	 */
	public static $wcs_is_subscription = null;
}
