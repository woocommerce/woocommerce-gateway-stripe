<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Page_Helper;
use WP_UnitTestCase;

/**
 */
class WC_Stripe_Page_Helper_Test extends WP_UnitTestCase {
	/**
	 * Test if the subscription edit page is correctly identified.
	 */
	public function test_is_subscription_edit_page() {
		add_filter( 'wc_stripe_is_subscription_edit_page', '__return_true' );
		$this->assertTrue( WC_Stripe_Page_Helper::is_subscription_edit_page() );

		remove_filter( 'wc_stripe_is_subscription_edit_page', '__return_true' );
		$this->assertFalse( WC_Stripe_Page_Helper::is_subscription_edit_page() );
	}
}
