<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Klarna.
 */
class WC_Stripe_UPE_Payment_Method_Klarna_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * WC_Stripe_UPE_Payment_Method_Klarna instance.
	 *
	 * @var WC_Stripe_UPE_Payment_Method_Klarna
	 */
	protected $instance;

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->instance = new WC_Stripe_UPE_Payment_Method_Klarna();
	}

	/**
	 * Tests for `get_retrievable_type()`.
	 *
	 * @return void
	 */
	public function test_get_retrievable_type() {
		$this->assertSame( WC_Stripe_Payment_Methods::KLARNA, $this->instance->get_retrievable_type() );
	}

	/**
	 * Tests for `create_payment_token_for_user()`.
	 *
	 * @return void
	 */
	public function test_create_payment_token_for_user() {
		$payment_method = (object) [
			'id'     => 'pm_123',
			'klarna' => (object) [
				'dob' => (object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
			],
		];

		$token = $this->instance->create_payment_token_for_user( 1, $payment_method );

		$this->assertSame( 'stripe_klarna', $token->get_gateway_id() );
		$this->assertSame( 'pm_123', $token->get_token() );
		$this->assertSame( 1, $token->get_user_id() );
		$this->assertSame( '2000-02-01', $token->get_dob() );
	}

	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Klarna::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Klarna::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'GB is supported'     => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'AU is supported'     => [ WC_Stripe_Country_Code::AUSTRALIA, true ],
			'DE is supported'     => [ WC_Stripe_Country_Code::GERMANY, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}

	/**
	 * Tests for {@see WC_Stripe_UPE_Payment_Method_Klarna::get_available_billing_countries()}.
	 *
	 * @dataProvider provide_test_get_available_billing_countries
	 *
	 * @param string $account_country The account country.
	 * @param string $currency        The store currency.
	 * @param array $expected         The expected billing countries.
	 */
	public function test_get_available_billing_countries( string $account_country, string $currency, array $expected ): void {
		$currency_filter = function () use ( $currency ) {
			return $currency;
		};
		add_filter( 'woocommerce_currency', $currency_filter );

		try {
			$this->run_get_available_billing_countries_test( WC_Stripe_UPE_Payment_Method_Klarna::class, $account_country, $expected );
		} finally {
			remove_filter( 'woocommerce_currency', $currency_filter );
		}
	}

	/**
	 * Data provider for {@see test_get_available_billing_countries()}.
	 *
	 * @return array
	 */
	public function provide_test_get_available_billing_countries(): array {
		$euro_countries = [
			WC_Stripe_Country_Code::AUSTRIA,
			WC_Stripe_Country_Code::BELGIUM,
			WC_Stripe_Country_Code::FINLAND,
			WC_Stripe_Country_Code::FRANCE,
			WC_Stripe_Country_Code::GREECE,
			WC_Stripe_Country_Code::GERMANY,
			WC_Stripe_Country_Code::IRELAND,
			WC_Stripe_Country_Code::ITALY,
			WC_Stripe_Country_Code::NETHERLANDS,
			WC_Stripe_Country_Code::PORTUGAL,
			WC_Stripe_Country_Code::SPAIN,
		];

		return [
			'US and PR are supported for US accounts'            => [
				'account_country' => WC_Stripe_Country_Code::UNITED_STATES,
				'currency'        => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected'        => [ WC_Stripe_Country_Code::UNITED_STATES, WC_Stripe_Country_Code::PUERTO_RICO ],
			],
			'US and PR are supported for PR accounts'            => [
				'account_country' => WC_Stripe_Country_Code::PUERTO_RICO,
				'currency'        => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected'        => [ WC_Stripe_Country_Code::UNITED_STATES, WC_Stripe_Country_Code::PUERTO_RICO ],
			],
			'GB is supported for GB accounts using GBP'          => [
				'account_country' => WC_Stripe_Country_Code::UNITED_KINGDOM,
				'currency'        => WC_Stripe_Currency_Code::POUND_STERLING,
				'expected'        => [ WC_Stripe_Country_Code::UNITED_KINGDOM ],
			],
			'AU is supported for AU accounts using AUD'          => [
				'account_country' => WC_Stripe_Country_Code::AUSTRALIA,
				'currency'        => WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR,
				'expected'        => [ WC_Stripe_Country_Code::AUSTRALIA ],
			],
			'CA is supported for CA accounts using CAD'          => [
				'account_country' => WC_Stripe_Country_Code::CANADA,
				'currency'        => WC_Stripe_Currency_Code::CANADIAN_DOLLAR,
				'expected'        => [ WC_Stripe_Country_Code::CANADA ],
			],
			'CA is supported for CA accounts using USD'          => [
				'account_country' => WC_Stripe_Country_Code::CANADA,
				'currency'        => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected'        => [ WC_Stripe_Country_Code::CANADA ],
			],
			'GB is supported for NL accounts using GBP'          => [
				'account_country' => WC_Stripe_Country_Code::NETHERLANDS,
				'currency'        => WC_Stripe_Currency_Code::POUND_STERLING,
				'expected'        => [ WC_Stripe_Country_Code::UNITED_KINGDOM ],
			],
			'Euro countries supported for AT accounts using EUR' => [
				'account_country' => WC_Stripe_Country_Code::AUSTRIA,
				'currency'        => WC_Stripe_Currency_Code::EURO,
				'expected'        => $euro_countries,
			],
			'Euro countries supported for NL accounts using EUR' => [
				'account_country' => WC_Stripe_Country_Code::NETHERLANDS,
				'currency'        => WC_Stripe_Currency_Code::EURO,
				'expected'        => $euro_countries,
			],
			'CH supported for CH accounts using CHF'             => [
				'account_country' => WC_Stripe_Country_Code::SWITZERLAND,
				'currency'        => WC_Stripe_Currency_Code::SWISS_FRANC,
				'expected'        => [ WC_Stripe_Country_Code::SWITZERLAND ],
			],
			'CH supported for FR accounts using CHF'             => [
				'account_country' => WC_Stripe_Country_Code::FRANCE,
				'currency'        => WC_Stripe_Currency_Code::SWISS_FRANC,
				'expected'        => [ WC_Stripe_Country_Code::SWITZERLAND ],
			],
			'PL supported for PL accounts using PLN'             => [
				'account_country' => WC_Stripe_Country_Code::POLAND,
				'currency'        => WC_Stripe_Currency_Code::POLISH_ZLOTY,
				'expected'        => [ WC_Stripe_Country_Code::POLAND ],
			],
			'PL supported for DE accounts using PLN'             => [
				'account_country' => WC_Stripe_Country_Code::GERMANY,
				'currency'        => WC_Stripe_Currency_Code::POLISH_ZLOTY,
				'expected'        => [ WC_Stripe_Country_Code::POLAND ],
			],
		];
	}
}
