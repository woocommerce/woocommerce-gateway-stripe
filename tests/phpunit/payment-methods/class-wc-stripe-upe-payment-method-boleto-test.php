<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Boleto.
 */
class WC_Stripe_UPE_Payment_Method_Boleto_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Boleto::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Boleto::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'BR is supported'     => [ WC_Stripe_Country_Code::BRAZIL, true ],
			'US is not supported' => [ WC_Stripe_Country_Code::UNITED_STATES, false ],
			'MX is not supported' => [ WC_Stripe_Country_Code::MEXICO, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}
}
