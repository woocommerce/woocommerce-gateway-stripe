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

	public function test_is_id_for_payment_method() {
		$this->assertTrue( WC_Stripe_Helper::is_id_for_payment_method( 'pm_1234' ) );
		$this->assertTrue( WC_Stripe_Helper::is_id_for_payment_method( 'pm_' ) );

		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( '_pm_1234' ) );
		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( '_pm_' ) );
		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( 'pm' ) );

		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( 'src_1234' ) );
		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( 'src_' ) );
		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( 'src' ) );

		$not_a_string = 1234;
		$this->assertFalse( WC_Stripe_Helper::is_id_for_payment_method( $not_a_string ) );
	}

	public function test_is_payment_method_object() {
		$payment_method       = new stdClass();
		$payment_method->type = 'payment_method';
		$this->assertTrue( WC_Stripe_Helper::is_payment_method_object( $payment_method ) );

		$empty = new stdClass();
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $empty ) );

		$not_payment_method       = new stdClass();
		$not_payment_method->type = 'not_payment_method';
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $not_payment_method ) );

		$not_an_object = 'this is not an object';
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $not_an_object ) );
	}

	public function test_is_reusable_source() {
		$payment_method       = new stdClass();
		$payment_method->type = 'payment_method';
		$this->assertTrue( WC_Stripe_Helper::is_reusable_source( $payment_method ) );

		$reusable_source        = new stdClass();
		$reusable_source->usage = 'reusable';
		$this->assertTrue( WC_Stripe_Helper::is_reusable_source( $reusable_source ) );

		$empty = new stdClass();
		$this->assertFalse( WC_Stripe_Helper::is_reusable_source( $empty ) );

		$non_reusable_source        = new stdClass();
		$non_reusable_source->usage = 'single_use';
		$this->assertFalse( WC_Stripe_Helper::is_reusable_source( $non_reusable_source ) );

		$not_an_object = 'this is not an object';
		$this->assertFalse( WC_Stripe_Helper::is_reusable_source( $not_an_object ) );
	}
}
