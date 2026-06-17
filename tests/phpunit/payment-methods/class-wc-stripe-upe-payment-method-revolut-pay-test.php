<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Revolut_Pay.
 */
class WC_Stripe_UPE_Payment_Method_Revolut_Pay_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Revolut_Pay::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Revolut_Pay::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'GB is supported'     => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'FR is supported'     => [ WC_Stripe_Country_Code::FRANCE, true ],
			'DE is supported'     => [ WC_Stripe_Country_Code::GERMANY, true ],
			'PL is supported'     => [ WC_Stripe_Country_Code::POLAND, true ],
			'US is not supported' => [ WC_Stripe_Country_Code::UNITED_STATES, false ],
			'CA is not supported' => [ WC_Stripe_Country_Code::CANADA, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}

	/**
	 * Revolut Pay supports separate authorization and capture.
	 *
	 * @return void
	 */
	public function test_requires_automatic_capture_is_false(): void {
		$payment_method = new WC_Stripe_UPE_Payment_Method_Revolut_Pay();
		$this->assertFalse( $payment_method->requires_automatic_capture() );
	}

	/**
	 * Revolut Pay is reusable so it can back subscriptions via Stripe mandates.
	 *
	 * @return void
	 */
	public function test_is_reusable_returns_true(): void {
		$payment_method = new WC_Stripe_UPE_Payment_Method_Revolut_Pay();
		$this->assertTrue( $payment_method->is_reusable() );
	}

	/**
	 * A saved Revolut Pay token is created from the Stripe payment method.
	 *
	 * @return void
	 */
	public function test_create_payment_token_for_user(): void {
		$payment_method = (object) [ 'id' => 'pm_123' ];
		$instance       = new WC_Stripe_UPE_Payment_Method_Revolut_Pay();

		$token = $instance->create_payment_token_for_user( 1, $payment_method );

		$this->assertInstanceOf( WC_Stripe_Revolut_Pay_Payment_Token::class, $token );
		$this->assertSame( 'stripe_revolut_pay', $token->get_gateway_id() );
		$this->assertSame( 'pm_123', $token->get_token() );
		$this->assertSame( 1, $token->get_user_id() );
	}
}
