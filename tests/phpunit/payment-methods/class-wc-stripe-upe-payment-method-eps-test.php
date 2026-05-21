<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Eps.
 */
class WC_Stripe_UPE_Payment_Method_Eps_Test extends WP_UnitTestCase {
	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Eps::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$mock_account = $this->createMock( WC_Stripe_Account::class );
		$mock_account->method( 'get_account_country' )->willReturn( $account_country );

		$wc_stripe = WC_Stripe::get_instance();

		$initial_account = $wc_stripe->account;
		try {
			$wc_stripe->account = $mock_account;

			$payment_method = new WC_Stripe_UPE_Payment_Method_Eps();

			$this->assertSame( $expected_result, $payment_method->is_available_for_account_country() );
		} finally {
			$wc_stripe->account = $initial_account;
		}
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'AT is supported'     => [ WC_Stripe_Country_Code::AUSTRIA, true ],
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'DE is supported'     => [ WC_Stripe_Country_Code::GERMANY, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'TH is not supported' => [ WC_Stripe_Country_Code::THAILAND, false ],
			'AE is not supported' => [ WC_Stripe_Country_Code::UNITED_ARAB_EMIRATES, false ],
			'ZZ is supported'     => [ 'ZZ', true ],
		];
	}
}
