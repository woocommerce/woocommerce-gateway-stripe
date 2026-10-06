<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Affirm.
 */
class WC_Stripe_UPE_Payment_Method_Affirm_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Affirm::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Affirm::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'CA is supported'     => [ WC_Stripe_Country_Code::CANADA, true ],
			'GB is not supported' => [ WC_Stripe_Country_Code::UNITED_KINGDOM, false ],
			'DE is not supported' => [ WC_Stripe_Country_Code::GERMANY, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}

	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Affirm::get_available_billing_countries()}
	 * only returns the account country, as Affirm is domestic-only.
	 *
	 * @param string   $account_country The account country.
	 * @param string[] $expected_result The expected billing countries.
	 * @return void
	 *
	 * @dataProvider provide_test_get_available_billing_countries
	 */
	public function test_get_available_billing_countries( string $account_country, array $expected_result ): void {
		$this->run_get_available_billing_countries_test( WC_Stripe_UPE_Payment_Method_Affirm::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_get_available_billing_countries()}.
	 *
	 * @return array
	 */
	public function provide_test_get_available_billing_countries(): array {
		return [
			'US account allows US only'            => [ WC_Stripe_Country_Code::UNITED_STATES, [ WC_Stripe_Country_Code::UNITED_STATES ] ],
			'CA account allows CA only'            => [ WC_Stripe_Country_Code::CANADA, [ WC_Stripe_Country_Code::CANADA ] ],
			'lowercase account country normalized' => [ 'ca', [ WC_Stripe_Country_Code::CANADA ] ],
			'unsupported account allows none'      => [ WC_Stripe_Country_Code::UNITED_KINGDOM, [ '' ] ],
			'unknown account country allows none'  => [ '', [ '' ] ],
		];
	}
}
