<?php
/**
 * Class WC_Stripe_Subscriptions_Helper_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Subscriptions_Helper
 */

/**
 * Class WC_Stripe_Subscriptions_Helper tests.
 */
class WC_Stripe_Subscriptions_Helper_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_subscriptions_enabled`.
	 *
	 * @return void
	 */
	public function test_is_subscriptions_enabled() {
		$this->assertTrue( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() );
	}
}
