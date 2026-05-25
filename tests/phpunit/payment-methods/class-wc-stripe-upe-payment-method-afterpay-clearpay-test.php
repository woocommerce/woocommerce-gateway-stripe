<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay.
 */
class WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::class, $account_country, $expected_result );
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
			'AU is supported'     => [ WC_Stripe_Country_Code::AUSTRALIA, true ],
			'NZ is supported'     => [ WC_Stripe_Country_Code::NEW_ZEALAND, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'DE is not supported' => [ WC_Stripe_Country_Code::GERMANY, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}
}
