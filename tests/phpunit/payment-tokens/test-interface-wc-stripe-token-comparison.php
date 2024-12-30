<?php
/**
 * Trait WC_Stripe_Token_Comparison_Interface tests.
 */
class WC_Stripe_Token_Comparison_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_equal_payment_method`.
	 *
	 * @param string $token_type Token type.
	 * @param object $payment_method Payment method object.
	 * @param boolean $expected Whether the payment method is equal.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( $token_type, $payment_method, $expected ) {
		switch ( $token_type ) {
			case WC_Stripe_Payment_Methods::SEPA:
				$token = new WC_Payment_Token_SEPA();
				$token->set_fingerprint( '123abc' );
				break;
			case WC_Stripe_Payment_Methods::LINK:
				$token = new WC_Payment_Token_Link();
				$token->set_email( 'john.doe@example.com' );
				break;
			case WC_Stripe_Payment_Methods::CASHAPP_PAY:
				$token = new WC_Payment_Token_CashApp();
				$token->set_cashtag( '$test_cashtag' );
				break;
			case 'CC':
			default:
				$token = new WC_Stripe_Payment_Token_CC();
				$token->set_fingerprint( '123abc' );
		}

		$this->assertEquals( $expected, $token->is_equal_payment_method( $payment_method ) );
	}

	/**
	 * Data provider for `test_is_equal`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method() {
		return [
			'Unknown method' => [
				'token type'     => 'unknown',
				'payment method' => (object) [
					'type' => 'unknown',
				],
				'expected'       => false,
			],
			'CC, not equal'  => [
				'token type'     => 'CC',
				'payment_method' => (object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'fingerprint' => '456def',
					],
				],
				'expected'       => false,
			],
			'CC, equal'      => [
				'token type'     => 'CC',
				'payment method' => (object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'fingerprint' => '123abc',
					],
				],
				'expected'       => true,
			],
			'SEPA, equal'    => [
				'token type'     => WC_Stripe_Payment_Methods::SEPA,
				'payment method' => (object) [
					'type'       => WC_Stripe_Payment_Methods::SEPA_DEBIT,
					'sepa_debit' => (object) [
						'fingerprint' => '123abc',
					],
				],
				'expected'       => true,
			],
			'Link, equal'    => [
				'token type'     => WC_Stripe_Payment_Methods::LINK,
				'payment method' => (object) [
					'type' => WC_Stripe_Payment_Methods::LINK,
					'link' => (object) [
						'email' => 'john.doe@example.com',
					],
				],
				'expected'       => true,
			],
			'CashApp, equal' => [
				'token type'     => WC_Stripe_Payment_Methods::CASHAPP_PAY,
				'payment method' => (object) [
					'type'    => WC_Stripe_Payment_Methods::CASHAPP_PAY,
					'cashapp' => (object) [
						'cashtag' => '$test_cashtag',
					],
				],
				'expected'       => true,
			],
		];
	}
}
