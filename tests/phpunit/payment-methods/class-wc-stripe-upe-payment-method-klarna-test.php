<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Klarna.
 */
class WC_Stripe_UPE_Payment_Method_Klarna_Test extends WP_UnitTestCase {
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
		$mock_account = $this->createMock( WC_Stripe_Account::class );
		$mock_account->method( 'get_account_country' )->willReturn( $account_country );

		$wc_stripe = WC_Stripe::get_instance();

		$initial_account = $wc_stripe->account;
		try {
			$wc_stripe->account = $mock_account;

			$payment_method = new WC_Stripe_UPE_Payment_Method_Klarna();

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
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'GB is supported'     => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'AU is supported'     => [ WC_Stripe_Country_Code::AUSTRALIA, true ],
			'DE is supported'     => [ WC_Stripe_Country_Code::GERMANY, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'JP is not supported' => [ WC_Stripe_Country_Code::JAPAN, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}
}
