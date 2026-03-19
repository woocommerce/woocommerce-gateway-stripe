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

	/**
	 * cart_contains_free_trial mock.
	 *
	 * @var function
	 */
	public static $cart_contains_free_trial_result = null;

	public static function cart_contains_subscription() {
		return self::$cart_contains_subscription_result;
	}

	public static function set_cart_contains_subscription( $result ) {
		self::$cart_contains_subscription_result = $result;
	}

	public static function cart_contains_free_trial() {
		return self::$cart_contains_free_trial_result;
	}

	public static function set_cart_contains_free_trial( $result ) {
		self::$cart_contains_free_trial_result = $result;
	}

	/**
	 * Stub: get the recurring shipping package key.
	 *
	 * @param string $package_key The package key.
	 * @param int    $recurring_cart_key The recurring cart key.
	 * @return string
	 */
	public static function get_recurring_shipping_package_key( $package_key = '', $recurring_cart_key = 0 ): string {
		return $package_key;
	}
}
