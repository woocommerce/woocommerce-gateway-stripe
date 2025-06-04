<?php

namespace WooCommerce\Stripe\Tests;

/**
 * Tests for the WC_Stripe_Page_Helper class.
 */
class WC_Stripe_Page_Helper_Test extends \WP_UnitTestCase {
	/**
	 * Test if the subscription edit page is correctly identified.
	 */
	public function test_is_subscription_edit_page() {
		// Simulate a subscription edit page query parameters.
		$_GET = [
			'post'   => 123,
			'action' => 'edit',
			'page'   => 'wc-orders--shop_subscription',
			'id'     => 456,
		];

		$this->assertTrue( \WC_Stripe_Page_Helper::is_subscription_edit_page() );
	}
}
