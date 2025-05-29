<?php

namespace WooCommerce\Stripe\Tests\Compat;

use WC_Stripe_Subscriptions_Helper;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_Subscriptions_Helper_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Subscriptions_Helper
 *
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
