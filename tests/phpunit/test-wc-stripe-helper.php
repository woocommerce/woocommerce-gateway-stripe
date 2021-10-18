<?php
/**
 * These tests make assertions against class WC_Stripe_Helper.
 *
 * @package WooCommerce_Stripe/Tests/Helper
 */

/**
 * WC_Stripe_Helper_Test class.
 */
class WC_Stripe_Helper_Test extends WP_UnitTestCase {
	public function test_convert_to_stripe_locale() {
		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'en_GB' );
		$this->assertEquals( 'en-GB', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'fr_FR' );
		$this->assertEquals( 'fr', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'fr_CA' );
		$this->assertEquals( 'fr-CA', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'es_UY' );
		$this->assertEquals( 'es', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'es_EC' );
		$this->assertEquals( 'es-419', $result );
	}

	public function test_should_enqueue_in_current_tab_section() {
		global $current_tab, $current_section;
		$current_tab     = 'checkout';
		$current_section = 'stripe';

		$result = WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe' );
		$this->assertTrue( $result );

		$result = WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'onboarding', 'stripe' );
		$this->assertFalse( $result );

		unset( $current_tab );
		unset( $current_section );
	}
}
