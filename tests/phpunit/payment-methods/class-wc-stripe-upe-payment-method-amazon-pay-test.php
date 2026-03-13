<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Amazon_Pay.
 */
class WC_Stripe_UPE_Payment_Method_Amazon_Pay_Test extends \WP_UnitTestCase {

	/**
	 * Provide test data for {@see test_supported_currencies()}.
	 */
	public function provide_test_supported_currencies(): array {
		$all_supported_currencies = [
			\WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR,
			\WC_Stripe_Currency_Code::SWISS_FRANC,
			\WC_Stripe_Currency_Code::DANISH_KRONE,
			\WC_Stripe_Currency_Code::EURO,
			\WC_Stripe_Currency_Code::POUND_STERLING,
			\WC_Stripe_Currency_Code::HONG_KONG_DOLLAR,
			\WC_Stripe_Currency_Code::JAPANESE_YEN,
			\WC_Stripe_Currency_Code::NORWEGIAN_KRONE,
			\WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR,
			\WC_Stripe_Currency_Code::SWEDISH_KRONA,
			\WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			\WC_Stripe_Currency_Code::SOUTH_AFRICAN_RAND,
		];

		return [
			'AT supports all currencies' => [ 'AT', $all_supported_currencies ],
			'BE supports all currencies' => [ 'BE', $all_supported_currencies ],
			'CY supports all currencies' => [ 'CY', $all_supported_currencies ],
			'DK supports all currencies' => [ 'DK', $all_supported_currencies ],
			'FR supports all currencies' => [ 'FR', $all_supported_currencies ],
			'DE supports all currencies' => [ 'DE', $all_supported_currencies ],
			'HU supports all currencies' => [ 'HU', $all_supported_currencies ],
			'IE supports all currencies' => [ 'IE', $all_supported_currencies ],
			'IT supports all currencies' => [ 'IT', $all_supported_currencies ],
			'LU supports all currencies' => [ 'LU', $all_supported_currencies ],
			'NL supports all currencies' => [ 'NL', $all_supported_currencies ],
			'PT supports all currencies' => [ 'PT', $all_supported_currencies ],
			'ES supports all currencies' => [ 'ES', $all_supported_currencies ],
			'SE supports all currencies' => [ 'SE', $all_supported_currencies ],
			'CH supports all currencies' => [ 'CH', $all_supported_currencies ],
			'GB supports all currencies' => [ 'GB', $all_supported_currencies ],
			'US only supports USD'       => [ 'US', [ \WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ] ],
			// We are not validating the country as valid in this method. Codify that expectation in some test cases.
			'GR returns all currencies'  => [ 'GR', $all_supported_currencies ],
			'ZA returns all currencies'  => [ 'ZA', $all_supported_currencies ],
		];
	}

	/**
	 * Test \WC_Stripe_UPE_Payment_Method_Amazon_Pay->get_supported_currencies().
	 *
	 * @param string   $account_country     The country code for the Stripe account.
	 * @param string[] $expected_currencies The expected currencies.
	 * @dataProvider provide_test_supported_currencies
	 */
	public function test_supported_currencies( string $account_country, array $expected_currencies ): void {
		$mock_account = $this->createMock( \WC_Stripe_Account::class );

		$mock_account->method( 'get_account_country' )
			->willReturn( $account_country );

		$stripe_instance = \WC_Stripe::get_instance();
		$initial_account = $stripe_instance->account;
		$stripe_instance->account = $mock_account;

		$amazon_pay           = new \WC_Stripe_UPE_Payment_Method_Amazon_Pay();
		$supported_currencies = $amazon_pay->get_supported_currencies();

		// Reset account before asserting.
		$stripe_instance->account = $initial_account;

		$this->assertEquals( $expected_currencies, $supported_currencies );
	}

	/**
	 * Test \WC_Stripe_UPE_Payment_Method_Amazon_Pay::get_amazon_pay_supported_currencies().
	 *
	 * @param string   $account_country     The country code for the Stripe account.
	 * @param string[] $expected_currencies The expected currencies.
	 * @dataProvider provide_test_supported_currencies
	 */
	public function test_get_amazon_pay_supported_currencies( string $account_country, array $expected_currencies ): void {
		$mock_account = $this->createMock( \WC_Stripe_Account::class );

		$mock_account->method( 'get_account_country' )
			->willReturn( $account_country );

		$stripe_instance = \WC_Stripe::get_instance();
		$initial_account = $stripe_instance->account;
		$stripe_instance->account = $mock_account;

		$supported_currencies = \WC_Stripe_UPE_Payment_Method_Amazon_Pay::get_amazon_pay_supported_currencies();

		// Reset account before asserting.
		$stripe_instance->account = $initial_account;

		$this->assertEquals( $expected_currencies, $supported_currencies );
	}

	public function provide_test_is_available_for_account_country(): array {
		return [
			'AT is available'      => [ 'AT', true ],
			'BE is available'      => [ 'BE', true ],
			'CY is available'      => [ 'CY', true ],
			'DK is available'      => [ 'DK', true ],
			'FR is available'      => [ 'FR', true ],
			'DE is available'      => [ 'DE', true ],
			'HU is available'      => [ 'HU', true ],
			'IE is available'      => [ 'IE', true ],
			'IT is available'      => [ 'IT', true ],
			'LU is available'      => [ 'LU', true ],
			'NL is available'      => [ 'NL', true ],
			'PT is available'      => [ 'PT', true ],
			'ES is available'      => [ 'ES', true ],
			'SE is available'      => [ 'SE', true ],
			'CH is available'      => [ 'CH', true ],
			'GB is available'      => [ 'GB', true ],
			'US is available'      => [ 'US', true ],
			'ZA is not available'  => [ 'ZA', false ],
			'CA is not available'  => [ 'CA', false ],
			'GR is not available'  => [ 'GR', false ],
		];
	}

	/**
	 * Test the `is_available_for_account_country()` method.
	 *
	 * @param string $account_country       The country code for the Stripe account.
	 * @param bool   $expected_availability The expected availability.
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_availability ): void {
		$mock_account = $this->createMock( \WC_Stripe_Account::class );
		$mock_account->method( 'get_account_country' )
			->willReturn( $account_country );

		$stripe_instance = \WC_Stripe::get_instance();
		$initial_account = $stripe_instance->account;
		$stripe_instance->account = $mock_account;

		$amazon_pay   = new \WC_Stripe_UPE_Payment_Method_Amazon_Pay();
		$is_available = $amazon_pay->is_available_for_account_country();

		// Reset account before asserting.
		$stripe_instance->account = $initial_account;

		$this->assertEquals( $expected_availability, $is_available );
	}

	/**
	 * Test \WC_Stripe_UPE_Payment_Method_Amazon_Pay::is_amazon_pay_available_for_account_country().
	 *
	 * @param string $account_country       The country code for the Stripe account.
	 * @param bool   $expected_availability The expected availability.
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_amazon_pay_available_for_account_country( string $account_country, bool $expected_availability ): void {
		$mock_account = $this->createMock( \WC_Stripe_Account::class );
		$mock_account->method( 'get_account_country' )
			->willReturn( $account_country );

		$stripe_instance = \WC_Stripe::get_instance();
		$initial_account = $stripe_instance->account;

		try {
			$stripe_instance->account = $mock_account;

			$is_available = \WC_Stripe_UPE_Payment_Method_Amazon_Pay::is_amazon_pay_available_for_account_country();

			$this->assertEquals( $expected_availability, $is_available );
		} finally {
			$stripe_instance->account = $initial_account;
		}
	}
}
