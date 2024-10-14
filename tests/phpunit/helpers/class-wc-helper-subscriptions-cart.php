<?php
/**
 * Subscription WC_Subscription_Cart helper.
 */

/**
 * Class WC_Subscription_Cart.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscriptions_Cart {
	/**
	 * cart_contains_subscription mock.
	 *
	 * @var function
	 */
	public static $cart_contains_subscription_result = null;

	public static function cart_contains_subscription() {
		return self::$cart_contains_subscription_result;
	}

	public static function set_cart_contains_subscription( $result ) {
		self::$cart_contains_subscription_result = $result;
	}
}
