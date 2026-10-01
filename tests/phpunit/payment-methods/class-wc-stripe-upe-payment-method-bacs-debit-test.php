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
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Bacs_Debit::is_available_for_billing_country()}
	 * only permits shoppers billed in the Stripe account's country.
	 *
	 * @param string $account_country The account country.
	 * @param string $country_code    The billing country to check.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_billing_country
	 */
	public function test_is_available_for_billing_country( string $account_country, string $country_code, bool $expected_result ): void {
		$this->run_is_available_for_billing_country_test( WC_Stripe_UPE_Payment_Method_Bacs_Debit::class, $account_country, $country_code, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_billing_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_billing_country(): array {
		return [
			'GB shopper is supported for GB account'     => [
				'account_country' => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'country_code'    => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'expected_result' => true,
			],
			'IE shopper is not supported for GB account' => [
				'account_country' => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'country_code'    => WC_Stripe_Country_Code::IRELAND,
				'expected_result' => false,
			],
			'US shopper is not supported for GB account' => [
				'account_country' => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'country_code'    => WC_Stripe_Country_Code::UNITED_STATES,
				'expected_result' => false,
			],
			'unknown country is not supported'           => [
				'account_country' => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'country_code'    => '',
				'expected_result' => false,
			],
			'unknown account country rejects all'        => [
				'account_country' => '',
				'country_code'    => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'expected_result' => false,
			],
		];
	}
}
