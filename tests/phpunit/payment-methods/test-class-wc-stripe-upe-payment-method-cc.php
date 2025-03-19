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
}
