<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Alipay.
 */
class WC_Stripe_UPE_Payment_Method_Alipay_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Alipay::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Alipay::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'HK is supported'     => [ WC_Stripe_Country_Code::HONG_KONG, true ],
			'MY is supported'     => [ WC_Stripe_Country_Code::MALAYSIA, true ],
			'JP is supported'     => [ WC_Stripe_Country_Code::JAPAN, true ],
			'PL is not supported' => [ WC_Stripe_Country_Code::POLAND, false ],
			'MX is not supported' => [ WC_Stripe_Country_Code::MEXICO, false ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}
}
