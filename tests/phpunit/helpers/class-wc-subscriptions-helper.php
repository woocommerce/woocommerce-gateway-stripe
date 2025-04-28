<?php
/**
 * WC Subscription function mocks
 */

/**
 * A function to mock wcs_get_subscriptions_for_order.
 *
 * @param int|WC_Order $order The order object or ID.
 * @return array
 */
function wcs_get_subscriptions_for_order( $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
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
function wcs_get_subscriptions( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	if ( ! WC_Subscriptions_Helpers::$wcs_get_subscriptions ) {
		return [];
	}

	return (array) WC_Subscriptions_Helpers::$wcs_get_subscriptions;
}

/**
 * A function to mock wcs_get_subscriptions_for_renewal_order.
 *
 * @param int|WC_Order $order The order object or ID.
 * @return array
 */
function wcs_get_subscriptions_for_renewal_order( $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	if ( ! WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order ) {
		return [];
	}

	return (array) WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order;
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
	 * Mock for wcs_get_subscriptions_for_renewal_order.
	 *
	 * @var array
	 */
	public static $wcs_get_subscriptions_for_renewal_order = null;
}
