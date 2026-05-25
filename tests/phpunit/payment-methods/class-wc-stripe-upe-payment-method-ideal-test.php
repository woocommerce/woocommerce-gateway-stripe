<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Ideal.
 */
class WC_Stripe_UPE_Payment_Method_Ideal_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Ideal::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Ideal::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'NL is supported'     => [ WC_Stripe_Country_Code::NETHERLANDS, true ],
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'DE is supported'     => [ WC_Stripe_Country_Code::GERMANY, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'TH is not supported' => [ WC_Stripe_Country_Code::THAILAND, false ],
			'AE is not supported' => [ WC_Stripe_Country_Code::UNITED_ARAB_EMIRATES, false ],
			'ZZ is supported'     => [ 'ZZ', true ],
		];
	}
}
