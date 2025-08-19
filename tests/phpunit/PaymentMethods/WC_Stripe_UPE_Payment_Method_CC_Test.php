<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Method_CC;
use WooCommerce\Stripe\Tests\Helpers\OC_Test_Helper;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_CC.
 */
class WC_Stripe_UPE_Payment_Method_CC_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_title`.
	 *
	 * @param array|bool $payment_details Payment details.
	 * @param bool       $optimized_checkout_setting Optimized Checkout flag.
	 * @param array      $query_params Query parameters.
	 * @param string     $expected Expected title.
	 * @return void
	 *
	 * @dataProvider provide_test_get_title
	 */
	public function test_get_title( $payment_details, $optimized_checkout_setting, $query_params, $expected ) {
		if ( $optimized_checkout_setting ) {
			OC_Test_Helper::enable_oc();
		}

		if ( is_array( $payment_details ) ) {
			$payment_details = json_decode( wp_json_encode( $payment_details ) );
		}
		if ( ! empty( $query_params ) ) {
			$_GET = array_merge( $_GET, $query_params );
		}

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
		$actual         = $payment_method->get_title( $payment_details );

		// Clean up.
		OC_Test_Helper::disable_oc();

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider for `test_get_title`.
	 *
	 * @return array
	 */
	public function provide_test_get_title() {
		return [
			'optimized checkout, with payment details' => [
				'payment details'         => [
					'type' => WC_Stripe_Payment_Methods::ALIPAY,
				],
				'optimized checkout flag' => true,
				'query params'            => [],
				'expected'                => 'Alipay',
			],
			'optimized checkout, block checkout page / pay for order' => [
				'payment details'         => false,
				'optimized checkout flag' => true,
				'query params'            => [
					'pay_for_order' => 'true',
				],
				'expected'                => 'Stripe',
			],
			'Google Pay'                               => [
				'payment details'         => [
					'card' => [
						'wallet' => [
							'type' => WC_Stripe_Payment_Methods::GOOGLE_PAY,
						],
					],
				],
				'optimized checkout flag' => false,
				'query params'            => [],
				'expected'                => 'Google Pay (Stripe)',
			],
			'default, hardcoded'                       => [
				'payment details'         => false,
				'optimized checkout flag' => false,
				'query params'            => [],
				'expected'                => 'Credit / Debit Card',
			],
		];
	}

	/**
	 * Test for `get_testing_instructions`.
	 *
	 * @param bool   $optimized_checkout_setting Optimized Checkout setting.
	 * @param string $expected Expected instructions.
	 * @return void
	 *
	 * @dataProvider provide_test_get_testing_instructions
	 */
	public function test_get_testing_instructions( $optimized_checkout_setting, $expected ) {
		if ( $optimized_checkout_setting ) {
			OC_Test_Helper::enable_oc();
		}

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
		$actual         = $payment_method->get_testing_instructions();

		// Clean up
		OC_Test_Helper::disable_oc();

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Provider for `get_testing_instructions`.
	 *
	 * @return array
	 */
	public function provide_test_get_testing_instructions() {
		return [
			'Optimized Checkout enabled'  => [
				'optimized checkout setting' => true,
				'expected'                   => '<div id="wc-stripe-payment-method-instructions-card" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.</div><div id="wc-stripe-payment-method-instructions-blik" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use any 6-digit number to authorize payment.</div><div id="wc-stripe-payment-method-instructions-sepa_debit" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">here</a>.</div>',
			],
			'Optimized Checkout disabled' => [
				'optimized checkout setting' => false,
				'expected'                   => '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.',
			],
		];
	}
}
