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
	 * When non-empty, is_subscription() returns true only for these product IDs. Used for mixed cart (e.g. multiple simple + one subscription).
	 *
	 * @var int[]
	 */
	private static array $subscription_product_ids = [];

	/**
	 * Set product IDs that should be treated as subscriptions. When non-empty, is_subscription( $product ) returns true only for these IDs.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return void
	 */
	public static function set_subscription_product_ids( array $product_ids ): void {
		self::$subscription_product_ids = $product_ids;
	}

	/**
	 * Get the length of the trial period for a subscription product.
	 *
	 * @param \WC_Product|null $product The product to get the trial length for.
	 * @return int
	 */
	public static function get_trial_length( $product = null ): int {
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
	 * @param \WC_Product|int|null $product The product or product ID to check if it is a subscription.
	 * @return bool
	 */
	public static function is_subscription( $product = null ): bool {
		if ( ! empty( self::$subscription_product_ids ) && null !== $product ) {
			$product_id = $product instanceof \WC_Product ? $product->get_id() : $product;
			return in_array( $product_id, self::$subscription_product_ids, true );
		}
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

	/**
	 * Stub: get the subscription interval.
	 *
	 * @param \WC_Product|null $product The product.
	 * @return string
	 */
	public static function get_interval( $product = null ): string {
		return '1';
	}

	/**
	 * Stub: get the subscription period.
	 *
	 * @param \WC_Product|null $product The product.
	 * @return string
	 */
	public static function get_period( $product = null ): string {
		return 'month';
	}

	/**
	 * Stub: get the subscription price.
	 *
	 * @param \WC_Product|null $product The product.
	 * @return string
	 */
	public static function get_price( $product = null ): string {
		return '0';
	}

	/**
	 * Stub: get the subscription sign-up fee.
	 *
	 * @param mixed $product The product.
	 * @return string
	 */
	public static function get_sign_up_fee( $product = null ): string {
		return '0';
	}
}
