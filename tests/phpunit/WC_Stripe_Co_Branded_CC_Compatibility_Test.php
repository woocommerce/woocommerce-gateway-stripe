<?php
/**
 * These tests make assertions against the class WC_Stripe_Co_Branded_CC_Compatibility
 */

/**
 * Class WC_Stripe_Co_Branded_CC_Compatibility_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Co_Branded_CC_Compatibility
 */
class WC_Stripe_Co_Branded_CC_Compatibility_Test extends WP_UnitTestCase {
	/**
	 * Test for is_wc_supported.
	 */
	public function test_is_wc_supported() {
		$helper = new WC_Stripe_Co_Branded_CC_Compatibility();
		$this->assertSame( defined( 'WC_VERSION' ) && WC_VERSION > WC_Stripe_Co_Branded_CC_Compatibility::MIN_WC_VERSION, $helper->is_wc_supported() );
	}
}
