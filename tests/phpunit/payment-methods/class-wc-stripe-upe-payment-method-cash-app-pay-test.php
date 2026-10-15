<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Cash_App_Pay.
 */
class WC_Stripe_UPE_Payment_Method_Cash_App_Pay_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Cash_App_Pay::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'GB is not supported' => [ WC_Stripe_Country_Code::UNITED_KINGDOM, false ],
			'CA is not supported' => [ WC_Stripe_Country_Code::CANADA, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}

	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Cash_App_Pay::get_available_billing_countries()}
	 * only returns the account country, as Cash App Pay is domestic-only.
	 *
	 * @param string   $account_country The account country.
	 * @param string[] $expected_result The expected billing countries.
	 * @return void
	 *
	 * @dataProvider provide_test_get_available_billing_countries
	 */
	public function test_get_available_billing_countries( string $account_country, array $expected_result ): void {
		$this->run_get_available_billing_countries_test( WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_get_available_billing_countries()}.
	 *
	 * @return array
	 */
	public function provide_test_get_available_billing_countries(): array {
		return [
			'US account allows US only'            => [ WC_Stripe_Country_Code::UNITED_STATES, [ WC_Stripe_Country_Code::UNITED_STATES ] ],
			'lowercase account country normalized' => [ 'us', [ WC_Stripe_Country_Code::UNITED_STATES ] ],
			'unsupported PR account allows none'   => [ WC_Stripe_Country_Code::PUERTO_RICO, [ '' ] ],
			'unsupported CA account allows none'   => [ WC_Stripe_Country_Code::CANADA, [ '' ] ],
			'unknown account country allows none'  => [ '', [ '' ] ],
		];
	}
}
