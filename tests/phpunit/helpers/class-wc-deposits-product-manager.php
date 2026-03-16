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
}
