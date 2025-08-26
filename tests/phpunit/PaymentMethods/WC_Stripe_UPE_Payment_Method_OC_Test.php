<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Method_OC;
use WooCommerce\Stripe\Tests\Helpers\OC_Test_Helper;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_OC.
 */
class WC_Stripe_UPE_Payment_Method_OC_Test extends WP_UnitTestCase {
	/**
	 * Tests for `__construct` method.
	 *
	 * @return void
	 */
	public function test_instance() {
		$payment_method = new WC_Stripe_UPE_Payment_Method_OC();

		$this->assertSame( 'Stripe', $payment_method->title );
		$this->assertContains( 'subscriptions', $payment_method->supports );
		$this->assertContains( 'tokenization', $payment_method->supports );
	}

	/**
	 * Tests for `get_title` method.
	 *
	 * @return void
	 *
	 * @dataProvider provide_test_get_title
	 */
	public function test_get_title( $payment_details, $query_params, $expected ) {
		if ( is_array( $payment_details ) ) {
			$payment_details = json_decode( wp_json_encode( $payment_details ) );
		}
		if ( ! empty( $query_params ) ) {
			$_GET = array_merge( $_GET, $query_params );
		}

		$payment_method = new WC_Stripe_UPE_Payment_Method_OC();
		$actual         = $payment_method->get_title( $payment_details );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider for `test_get_title`.
	 *
	 * @return array
	 */
	public function provide_test_get_title() {
		return [
			'with payment details' => [
				'payment details'         => [
					'type' => WC_Stripe_Payment_Methods::ALIPAY,
				],
				'query params'            => [],
				'expected'                => 'Alipay',
			],
			'block checkout page / pay for order' => [
				'payment details'         => false,
				'query params'            => [
					'pay_for_order' => 'true',
				],
				'expected'                => 'Stripe',
			],
			'default, hardcoded'                       => [
				'payment details'         => false,
				'query params'            => [],
				'expected'                => 'Stripe',
			],
		];
	}

	/**
	 * Tests for `is_available`, `get_retrievable_type`, `is_capability_active`, and `requires_automatic_capture` methods.
	 * @return void
	 */
	public function test_feature_methods() {
		$payment_method = new WC_Stripe_UPE_Payment_Method_OC();

		$this->assertFalse( $payment_method->is_available() );
		$this->assertEquals( WC_Stripe_Payment_Methods::CARD, $payment_method->get_retrievable_type() );
		$this->assertTrue( $payment_method->is_capability_active() );
		$this->assertFalse( $payment_method->requires_automatic_capture() );
	}

	/**
	 * Test for `get_testing_instructions`.
	 *
	 * @return void
	 */
	public function test_get_testing_instructions() {
		$expected = '<div id="wc-stripe-payment-method-instructions-card" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.</div><div id="wc-stripe-payment-method-instructions-blik" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use any 6-digit number to authorize payment.</div><div id="wc-stripe-payment-method-instructions-sepa_debit" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">here</a>.</div>';

		$payment_method = new WC_Stripe_UPE_Payment_Method_OC();
		$actual         = $payment_method->get_testing_instructions();

		$this->assertEquals( $expected, $actual );
	}
}
