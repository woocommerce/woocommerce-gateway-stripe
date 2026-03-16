<?php
/**
 * Pre-Orders WC_Pre_Orders_Product helper.
 */

/**
 * Class WC_Pre_Orders_Product.
 *
 * This helper class should ONLY be used for unit tests.
 */
class WC_Pre_Orders_Product {

	/**
	 * The mocked value for product_is_charged_upon_release().
	 *
	 * @var bool
	 */
	private static bool $product_is_charged_upon_release_result = false;

	/**
	 * Set the value to be returned from product_is_charged_upon_release().
	 *
	 * @param bool $result The value to return from product_is_charged_upon_release().
	 * @return void
	 */
	public static function set_is_pre_order_charged_upon_release( bool $result ): void {
		self::$product_is_charged_upon_release_result = $result;
	}

	/**
	 * Determine if a product is charged upon release (pre-order).
	 *
	 * @param WC_Product $product The product to check.
	 * @return bool
	 */
	public static function product_is_charged_upon_release( $product ): bool {
		return self::$product_is_charged_upon_release_result;
	}
}
