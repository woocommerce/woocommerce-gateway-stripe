<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_BLIK.
 */
class WC_Stripe_UPE_Payment_Method_BLIK_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_BLIK::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_BLIK::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'GB is supported'     => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'DE is supported'     => [ WC_Stripe_Country_Code::GERMANY, true ],
			'PL is supported'     => [ WC_Stripe_Country_Code::POLAND, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'MX is not supported' => [ WC_Stripe_Country_Code::MEXICO, false ],
			'NZ is not supported' => [ WC_Stripe_Country_Code::NEW_ZEALAND, false ],
			'TH is not supported' => [ WC_Stripe_Country_Code::THAILAND, false ],
			// Note that BLIK uses a list of unsupported countries, so ZZ will be reported as supported as it
			// is not in the list of unsupported countries.
			'ZZ is supported'     => [ 'ZZ', true ],
		];
	}
}
