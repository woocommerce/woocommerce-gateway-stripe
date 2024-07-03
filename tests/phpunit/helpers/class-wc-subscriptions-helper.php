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
 * A helper class for setting up mocks for WC_Subscriptions functions.
 */
class WC_Subscriptions_Helpers {

	/**
	 * Mock for wcs_get_subscriptions_for_order.
	 *
	 * @var array
	 */
	public static $wcs_get_subscriptions_for_order = null;
}
