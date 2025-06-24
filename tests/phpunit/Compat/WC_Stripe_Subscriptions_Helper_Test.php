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

	/**
	 * Test for `test_is_manual_renewal_required`.
	 *
	 * @return void
	 */
	public function test_is_manual_renewal_required() {
		// This test assumes that manual renewal is not required.
		// If the logic changes, this test should be updated accordingly.
		$this->assertFalse( WC_Stripe_Subscriptions_Helper::is_manual_renewal_required() );
	}

	/**
	 * Test for `is_manual_renewal_enabled`.
	 *
	 * @return void
	 */
	public function test_is_manual_renewal_enabled() {
		// This test assumes that manual renewal is not enabled.
		// If the logic changes, this test should be updated accordingly.
		$this->assertFalse( WC_Stripe_Subscriptions_Helper::is_manual_renewal_enabled() );
	}
}
