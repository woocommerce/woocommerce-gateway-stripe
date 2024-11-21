<?php
/**
 * Class WC_Stripe_Mode
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Mode
 */

/**
 * Class WC_Stripe_Mode tests.
 */
class WC_Stripe_Mode_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_live` method.
	 *
	 * @return void
	 */
	public function test_is_live() {
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->assertFalse( WC_Stripe_Mode::is_live() );

		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->assertTrue( WC_Stripe_Mode::is_live() );
	}

	/**
	 * Test for `is_test` method.
	 *
	 * @return void
	 */
	public function test_is_test() {
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->assertTrue( WC_Stripe_Mode::is_test() );

		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->assertFalse( WC_Stripe_Mode::is_test() );
	}
}
