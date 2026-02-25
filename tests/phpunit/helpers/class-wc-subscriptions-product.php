<?php
/**
 * Subscription WC_Subscriptions_Product helper.
 */

/**
 * Class WC_Subscriptions_Product.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscriptions_Product {
	/**
	 * The mocked value for get_trial_length().
	 *
	 * @var int
	 */
	public static int $get_trial_length_result;

	/**
	 * The mocked value for is_subscription().
	 *
	 * @var bool
	 */
	public static bool $is_subscription_result;

	/**
	 * Get the length of the trial period for a subscription product.
	 *
	 * @return int
	 */
	public static function get_trial_length(): int {
		return self::$get_trial_length_result;
	}

	/**
	 * @param int $result The value to return from get_trial_length().
	 * @return void
	 */
	public static function set_trial_length( int $result ): void {
		self::$get_trial_length_result = $result;
	}

	/**
	 * Determine if a product is a subscription.
	 *
	 * @return bool
	 */
	public static function is_subscription(): bool {
		return self::$is_subscription_result;
	}

	/**
	 * Set the value to be returned from is_subscription().
	 *
	 * @param bool $result The value to return from is_subscription().
	 * @return void
	 */
	public static function set_is_subscription( bool $result ): void {
		self::$is_subscription_result = $result;
	}
}
