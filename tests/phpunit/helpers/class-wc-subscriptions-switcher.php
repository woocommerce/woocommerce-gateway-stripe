<?php

/**
 * A helper class for setting up mocks for WC_Subscriptions_Switcher functions.
 */
class WC_Subscriptions_Switcher {

	/**
	 * Mock value for cart_contains_switches.
	 *
	 * @var array
	 */
	public static $cart_contains_switches = false;

	public static function cart_contains_switches() {
		return self::$cart_contains_switches;
	}
}
