<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_CC.
 */
class WC_Stripe_UPE_Payment_Method_CC_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_title`.
	 *
	 * @param array  $settings Settings.
	 * @param array|bool  $payment_details Payment details.
	 * @param string $expected Expected title.
	 * @return void
	 * @dataProvider provide_test_get_title
	 */
	public function test_get_title( $settings, $payment_details, $expected ) {
		if ( is_array( $payment_details ) ) {
			$payment_details = json_decode( wp_json_encode( $payment_details ) );
		}
		if ( ! empty( $settings['key'] ) ) {
			update_option( $settings['key'], $settings['value'] );
		}
		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();

		$this->assertEquals( $expected, $payment_method->get_title( $payment_details ) );
	}

	/**
	 * Data provider for `test_get_title`.
	 *
	 * @return array
	 */
	public function provide_test_get_title() {
		return [
			'Google Pay'             => [
				'settings'        => [],
				'payment details' => [
					'card' => [
						'wallet' => [
							'type' => WC_Stripe_Payment_Methods::GOOGLE_PAY,
						],
					],
				],
				'expected'        => 'Google Pay (Stripe)',
			],
			'default, from settings' => [
				'settings'        => [
					'key'   => 'woocommerce_stripe_card_settings',
					'value' => [
						'title' => 'Card Custom Title',
					],
				],
				'payment details' => false,
				'expected'        => 'Card Custom Title',
			],
			'default, hardcoded'     => [
				'settings'        => [],
				'payment details' => false,
				'expected'        => 'Credit / Debit Card',
			],
		];
	}

	/**
	 * Test for `get_testing_instructions`.
	 *
	 * @param bool   $optimized_checkout_flag Optimized Checkout flag.
	 * @param string $expected Expected instructions.
	 * @return void
	 *
	 * @dataProvider provide_test_get_testing_instructions
	 */
	public function test_get_testing_instructions( $optimized_checkout_flag, $expected ) {
		update_option( WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME, $optimized_checkout_flag ? 'yes' : 'no' );

		$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['optimized_checkout_element'] = $optimized_checkout_flag ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();

		$this->assertEquals( $expected, $payment_method->get_testing_instructions() );
	}

	/**
	 * Provider for `get_testing_instructions`.
	 *
	 * @return array
	 */
	public function provide_test_get_testing_instructions() {
		return [
			'Optimized Checkout enabled'  => [
				'optimized checkout flag' => true,
				'expected'                => '<div id="wc-stripe-payment-method-instructions-card" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.</div><div id="wc-stripe-payment-method-instructions-blik" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use any 6-digit number to authorize payment.</div><div id="wc-stripe-payment-method-instructions-sepa_debit" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">here</a>.</div>',
			],
			'Optimized Checkout disabled' => [
				'optimized checkout flag' => false,
				'expected'                => '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.',
			],
		];
	}
}
