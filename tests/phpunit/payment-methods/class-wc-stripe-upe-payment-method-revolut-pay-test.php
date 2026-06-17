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

	/**
	 * Supported currencies depend on the account country: UK charges GBP, EEA charges EUR/RON/HUF/PLN/DKK.
	 *
	 * @param string   $account_country     The Stripe account country.
	 * @param string[] $expected_currencies The expected supported currencies.
	 * @return void
	 *
	 * @dataProvider provide_test_supported_currencies
	 */
	public function test_supported_currencies( string $account_country, array $expected_currencies ): void {
		$mock_account = $this->createMock( WC_Stripe_Account::class );
		$mock_account->method( 'get_account_country' )->willReturn( $account_country );

		$stripe_instance          = WC_Stripe::get_instance();
		$initial_account          = $stripe_instance->account;
		$stripe_instance->account = $mock_account;

		try {
			$supported_currencies = ( new WC_Stripe_UPE_Payment_Method_Revolut_Pay() )->get_supported_currencies();
		} finally {
			$stripe_instance->account = $initial_account;
		}

		$this->assertEquals( $expected_currencies, $supported_currencies );
	}

	/**
	 * Data provider for {@see test_supported_currencies()}.
	 *
	 * @return array
	 */
	public function provide_test_supported_currencies(): array {
		$eea = [
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::ROMANIAN_LEU,
			WC_Stripe_Currency_Code::HUNGARIAN_FORINT,
			WC_Stripe_Currency_Code::POLISH_ZLOTY,
			WC_Stripe_Currency_Code::DANISH_KRONE,
		];

		return [
			'UK charges GBP only'           => [ WC_Stripe_Country_Code::UNITED_KINGDOM, [ WC_Stripe_Currency_Code::POUND_STERLING ] ],
			'FR charges EEA set'            => [ WC_Stripe_Country_Code::FRANCE, $eea ],
			'DE charges EEA set'            => [ WC_Stripe_Country_Code::GERMANY, $eea ],
			'PL charges EEA set'            => [ WC_Stripe_Country_Code::POLAND, $eea ],
			'US (unsupported) charges none' => [ WC_Stripe_Country_Code::UNITED_STATES, [] ],
			'unknown country charges none'  => [ '', [] ],
		];
	}
}
