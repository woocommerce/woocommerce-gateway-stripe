<?php
/**
 * Deposits WC_Deposits_Product_Manager helper.
 *
 * This helper class should ONLY be used for unit tests.
 */
class WC_Deposits_Product_Manager {

	/**
	 * The mocked value for deposits_enabled().
	 *
	 * @var bool
	 */
	private static bool $deposits_enabled_result = false;

	/**
	 * Set the value to be returned from deposits_enabled().
	 *
	 * @param bool $result The value to return from deposits_enabled().
	 * @return void
	 */
	public static function set_deposits_enabled( bool $result ): void {
		self::$deposits_enabled_result = $result;
	}

	/**
	 * Whether deposits are enabled for the given product.
	 *
	 * @param int $product_id The product ID to check.
	 * @return bool
	 */
	public static function deposits_enabled( $product_id ): bool {
		return self::$deposits_enabled_result;
	}

	/**
	 * Stub: get the deposit type for a product.
	 *
	 * @param int $product_id The product ID.
	 * @return string
	 */
	public static function get_deposit_type( $product_id ): string {
		return '';
	}

	/**
	 * Stub: get the selected deposit type for a product.
	 *
	 * @param int $product_id The product ID.
	 * @return string
	 */
	public static function get_deposit_selected_type( $product_id ): string {
		return '';
	}

	/**
	 * Stub: get the deposit amount for a product.
	 *
	 * @param mixed  $product      The product or product ID.
	 * @param int    $plan_id      Optional plan ID.
	 * @param string $context      Optional context.
	 * @param mixed  $product_price Optional product price.
	 * @return string
	 */
	public static function get_deposit_amount( $product = 0, $plan_id = 0, $context = '', $product_price = null ): string {
		return '0';
	}
}
