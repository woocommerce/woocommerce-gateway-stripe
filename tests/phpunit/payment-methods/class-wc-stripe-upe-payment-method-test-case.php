<?php

/**
 * Base test case for payment method tests.
 */
abstract class WC_Stripe_UPE_Payment_Method_Test_Case extends WP_UnitTestCase {

	/**
	 * Run a test for `is_available_for_account_country()` method.
	 *
	 * @param string $payment_method_class The payment method class to test.
	 * @param string $account_country      The account country to test.
	 * @param bool   $expected_result      The expected result.
	 * @return void
	 */
	public function run_is_available_for_account_country_test( string $payment_method_class, string $account_country, bool $expected_result ): void {
		$mock_account = $this->createMock( WC_Stripe_Account::class );
		$mock_account->method( 'get_account_country' )
			->willReturn( $account_country );

		$wc_stripe = WC_Stripe::get_instance();

		$initial_account = $wc_stripe->account;
		try {
			$wc_stripe->account = $mock_account;

			$payment_method = new $payment_method_class();

			$this->assertSame( $expected_result, $payment_method->is_available_for_account_country() );
		} finally {
			$wc_stripe->account = $initial_account;
		}
	}
}
