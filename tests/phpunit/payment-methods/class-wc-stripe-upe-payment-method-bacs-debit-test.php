<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Bacs_Debit.
 */
class WC_Stripe_UPE_Payment_Method_Bacs_Debit_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Bacs_Debit::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Bacs_Debit::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'GB is supported'     => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'US is not supported' => [ WC_Stripe_Country_Code::UNITED_STATES, false ],
			'IE is not supported' => [ WC_Stripe_Country_Code::IRELAND, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}

	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Bacs_Debit::get_available_billing_countries()}
	 * only returns the account country, as Bacs Direct Debit is domestic-only.
	 *
	 * @param string   $account_country The account country.
	 * @param string[] $expected_result The expected billing countries.
	 * @return void
	 *
	 * @dataProvider provide_test_get_available_billing_countries
	 */
	public function test_get_available_billing_countries( string $account_country, array $expected_result ): void {
		$this->run_get_available_billing_countries_test( WC_Stripe_UPE_Payment_Method_Bacs_Debit::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_get_available_billing_countries()}.
	 *
	 * @return array
	 */
	public function provide_test_get_available_billing_countries(): array {
		return [
			'GB account allows GB only'            => [ WC_Stripe_Country_Code::UNITED_KINGDOM, [ WC_Stripe_Country_Code::UNITED_KINGDOM ] ],
			'lowercase account country normalized' => [ 'gb', [ WC_Stripe_Country_Code::UNITED_KINGDOM ] ],
			'unsupported IE account allows none'   => [ WC_Stripe_Country_Code::IRELAND, [ '' ] ],
			'unsupported US account allows none'   => [ WC_Stripe_Country_Code::UNITED_STATES, [ '' ] ],
			'unknown account country allows none'  => [ '', [ '' ] ],
		];
	}
}
