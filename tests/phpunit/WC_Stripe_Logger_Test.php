<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Helper;
use WC_Stripe_Logger;
use WC_Stripe_Settings;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_Logger.
 *
 * Class WC_Stripe_Logger_Test.
 */
class WC_Stripe_Logger_Test extends WP_UnitTestCase {
	/**
	 * Test for `can_log`.
	 *
	 * @return void
	 */
	public function test_can_log() {
		$this->assertFalse( WC_Stripe_Logger::can_log() );

		$stripe_settings            = WC_Stripe_Settings::get_instance()->get_gateway_settings();
		$stripe_settings['logging'] = 'yes';
		WC_Stripe_Settings::get_instance()->update_gateway_settings( $stripe_settings );

		$this->assertTrue( WC_Stripe_Logger::can_log() );
	}
}
