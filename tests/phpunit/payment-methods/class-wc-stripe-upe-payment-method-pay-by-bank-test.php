<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Pay_By_Bank.
 */
class WC_Stripe_UPE_Payment_Method_Pay_By_Bank_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {

	/**
	 * @var WC_Stripe_UPE_Payment_Method_Pay_By_Bank
	 */
	private WC_Stripe_UPE_Payment_Method_Pay_By_Bank $method;

	public function set_up(): void {
		parent::set_up();
		$this->method = new WC_Stripe_UPE_Payment_Method_Pay_By_Bank();
	}

	public function test_stripe_id(): void {
		$this->assertSame( 'pay_by_bank', $this->method->get_id() );
	}

	public function test_is_not_reusable(): void {
		$this->assertFalse( $this->method->is_reusable() );
	}

	public function test_requires_automatic_capture(): void {
		$this->assertTrue( $this->method->requires_automatic_capture() );
	}

	public function test_supported_currencies(): void {
		$currencies = $this->method->get_supported_currencies();
		$this->assertContains( WC_Stripe_Currency_Code::EURO, $currencies );
		$this->assertContains( WC_Stripe_Currency_Code::POUND_STERLING, $currencies );
		$this->assertNotContains( WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, $currencies );
	}

	/**
	 * Test that is_available_for_account_country() returns true when SUPPORTED_ACCOUNT_COUNTRIES
	 * is empty (Pay by Bank has broad merchant country support; Stripe enforces at the account level).
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Pay_By_Bank::class, $account_country, $expected_result );
	}

	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'  => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'GB is supported'  => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'DE is supported'  => [ WC_Stripe_Country_Code::GERMANY, true ],
			'FR is supported'  => [ WC_Stripe_Country_Code::FRANCE, true ],
		];
	}
}
