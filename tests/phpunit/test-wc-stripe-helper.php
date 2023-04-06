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

	public function test_add_payment_method_to_request_array_should_add_source_to_request() {
		$source_id = 'src_mock';
		$request   = WC_Stripe_Helper::add_payment_method_to_request_array( $source_id, [] );

		$this->assertArrayHasKey( 'source', $request, 'Source ID was not added to request array' );
		$this->assertEquals( $source_id, $request['source'] );
	}

	public function test_add_payment_method_to_request_array_should_add_payment_method_to_request() {
		$payment_method_id = 'pm_mock';
		$request           = WC_Stripe_Helper::add_payment_method_to_request_array( $payment_method_id, [] );

		$this->assertArrayHasKey( 'payment_method', $request, 'Payment Method ID was not added to request array' );
		$this->assertEquals( $payment_method_id, $request['payment_method'] );
	}

	public function test_add_payment_method_to_request_array_should_not_add_non_source_or_payment_method_to_request() {
		$not_a_payment_method_id = 'cus_mock';
		$request                 = WC_Stripe_Helper::add_payment_method_to_request_array( $not_a_payment_method_id, [] );

		$this->assertArrayNotHasKey( 'payment_method', $request, 'Payment Method ID was added to request array when it should not have' );
		$this->assertArrayNotHasKey( 'source', $request, 'Source was added to request array when it should not have' );
		$this->assertEmpty( $request, 'Request array is not empty when it should be empty' );
	}

	public function test_is_payment_method_object() {
		$payment_method         = new stdClass();
		$payment_method->object = 'payment_method';
		$this->assertTrue( WC_Stripe_Helper::is_payment_method_object( $payment_method ) );

		$empty = new stdClass();
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $empty ) );

		$not_payment_method         = new stdClass();
		$not_payment_method->object = 'not_payment_method';
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $not_payment_method ) );
	}

	public function test_is_reusable_source() {
		$payment_method         = new stdClass();
		$payment_method->object = 'payment_method';
		$this->assertTrue( WC_Stripe_Helper::is_reusable_payment_method( $payment_method ) );

		$reusable_source        = new stdClass();
		$reusable_source->usage = 'reusable';
		$this->assertTrue( WC_Stripe_Helper::is_reusable_payment_method( $reusable_source ) );

		$empty = new stdClass();
		$this->assertFalse( WC_Stripe_Helper::is_reusable_payment_method( $empty ) );

		$non_reusable_source        = new stdClass();
		$non_reusable_source->usage = 'single_use';
		$this->assertFalse( WC_Stripe_Helper::is_reusable_payment_method( $non_reusable_source ) );
	}

	public function test_is_card_payment_method() {
		$card_payment_method         = new stdClass();
		$card_payment_method->object = 'payment_method';
		$card_payment_method->type   = 'card';
		$this->assertTrue( WC_Stripe_Helper::is_card_payment_method( $card_payment_method ) );

		$card_source         = new stdClass();
		$card_source->object = 'source';
		$card_source->type   = 'card';
		$this->assertTrue( WC_Stripe_Helper::is_card_payment_method( $card_source ) );

		$non_card_payment_method         = new stdClass();
		$non_card_payment_method->object = 'payment_method';
		$non_card_payment_method->type   = 'not_card';
		$this->assertFalse( WC_Stripe_Helper::is_card_payment_method( $non_card_payment_method ) );

		$non_card_source         = new stdClass();
		$non_card_source->object = 'source';
		$non_card_source->type   = 'not_card';
		$this->assertFalse( WC_Stripe_Helper::is_card_payment_method( $non_card_source ) );

		$not_payment_method_or_source         = new stdClass();
		$not_payment_method_or_source->object = 'not_payment_method_or_source';
		$this->assertFalse( WC_Stripe_Helper::is_card_payment_method( $not_payment_method_or_source ) );
	}

	public function test_get_payment_method_from_intent() {
		$source         = 'src_mock';
		$payment_method = 'pm_mock';

		$intent_with_source         = new stdClass();
		$intent_with_source->source = $source;
		$this->assertEquals( $source, WC_Stripe_Helper::get_payment_method_from_intent( $intent_with_source ) );

		$intent_with_payment_method                 = new stdClass();
		$intent_with_payment_method->payment_method = $payment_method;
		$this->assertEquals( $payment_method, WC_Stripe_Helper::get_payment_method_from_intent( $intent_with_payment_method ) );

		$intent_with_neither_source_nor_payment_method = new stdClass();
		$this->assertNull( WC_Stripe_Helper::get_payment_method_from_intent( $intent_with_neither_source_nor_payment_method ) );
	}
}
