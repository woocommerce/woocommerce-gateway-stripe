<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_CC.
 */
class WC_Stripe_UPE_Payment_Method_CC_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_title`.
	 *
	 * @param array|bool $payment_details Payment details.
	 * @param array      $query_params Query parameters.
	 * @param string     $expected Expected title.
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

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
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
			'Google Pay'         => [
				'payment details' => [
					'card' => [
						'wallet' => [
							'type' => WC_Stripe_Payment_Methods::GOOGLE_PAY,
						],
					],
				],
				'query params'    => [],
				'expected'        => 'Google Pay (Stripe)',
			],
			'default, hardcoded' => [
				'payment details' => false,
				'query params'    => [],
				'expected'        => 'Credit / Debit Card',
			],
		];
	}

	/**
	 * Test for `get_testing_instructions`.
	 *
	 * @return void
	 */
	public function test_get_testing_instructions() {
		$expected = '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.';

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
		$actual         = $payment_method->get_testing_instructions();

		$this->assertEquals( $expected, $actual );
	}
}
