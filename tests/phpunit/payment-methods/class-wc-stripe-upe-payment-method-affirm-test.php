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
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Affirm::is_available_for_billing_country()}
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
		$this->run_is_available_for_billing_country_test( WC_Stripe_UPE_Payment_Method_Affirm::class, $account_country, $country_code, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_billing_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_billing_country(): array {
		return [
			'US shopper is supported for US account'        => [
				'account_country' => WC_Stripe_Country_Code::UNITED_STATES,
				'country_code'    => WC_Stripe_Country_Code::UNITED_STATES,
				'expected_result' => true,
			],
			'CA shopper is supported for CA account'        => [
				'account_country' => WC_Stripe_Country_Code::CANADA,
				'country_code'    => WC_Stripe_Country_Code::CANADA,
				'expected_result' => true,
			],
			'lowercase country is supported for US account' => [
				'account_country' => WC_Stripe_Country_Code::UNITED_STATES,
				'country_code'    => 'us',
				'expected_result' => true,
			],
			'CA shopper is not supported for US account'    => [
				'account_country' => WC_Stripe_Country_Code::UNITED_STATES,
				'country_code'    => WC_Stripe_Country_Code::CANADA,
				'expected_result' => false,
			],
			'US shopper is not supported for CA account'    => [
				'account_country' => WC_Stripe_Country_Code::CANADA,
				'country_code'    => WC_Stripe_Country_Code::UNITED_STATES,
				'expected_result' => false,
			],
			'unknown country is not supported'              => [
				'account_country' => WC_Stripe_Country_Code::UNITED_STATES,
				'country_code'    => '',
				'expected_result' => false,
			],
			'unknown account country rejects all'           => [
				'account_country' => '',
				'country_code'    => WC_Stripe_Country_Code::UNITED_STATES,
				'expected_result' => false,
			],
		];
	}
}
