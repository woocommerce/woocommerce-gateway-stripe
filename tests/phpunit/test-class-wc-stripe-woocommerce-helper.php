<?php
/**
 * Class WC_Stripe_WooCommerce_Helper_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_WooCommerce_Helper
 */

/**
 * Class WC_Stripe_WooCommerce_Helper tests.
 */
class WC_Stripe_WooCommerce_Helper_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_WooCommerce_Helper
	 */
	private $woocommerce_helper;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->woocommerce_helper = new WC_Stripe_WooCommerce_Helper();
	}

	/**
	 * Test for `is_custom_orders_table_enabled` method.
	 *
	 * @return void
	 */
	public function test_is_custom_orders_table_enabled() {
		$this->assertFalse( $this->woocommerce_helper->is_custom_orders_table_enabled() );
	}
}
