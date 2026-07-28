<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Becs_Debit.
 */
class WC_Stripe_UPE_Payment_Method_Becs_Debit_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Tests for create_payment_token_for_user.
	 */
	public function test_create_payment_token_for_user() {
		$payment_method = (object) [
			'id'                                  => 'pm_test_au_becs_debit_123',
			WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
				'bsb_number'  => '000000',
				'fingerprint' => 'xOFKhDPeaJYLGTc2',
				'last4'       => '3456',
			],
		];

		$becs_debit_payment_method = new WC_Stripe_UPE_Payment_Method_Becs_Debit();
		$user_id                   = 1234;

		$token = $becs_debit_payment_method->create_payment_token_for_user( $user_id, $payment_method );

		$this->assertInstanceOf( 'WC_Payment_Token_Becs_Debit', $token );
		$this->assertEquals( $user_id, $token->get_user_id() );
		$this->assertEquals( $payment_method->id, $token->get_token() );
		$this->assertEquals( $payment_method->{WC_Stripe_Payment_Methods::BECS_DEBIT}->last4, $token->get_last4() );
		$this->assertEquals( $payment_method->{WC_Stripe_Payment_Methods::BECS_DEBIT}->fingerprint, $token->get_fingerprint() );
	}

	/**
	 * Tests that create_payment_token_for_user returns null when $payment_method is missing
	 * the `id` or BECS specific properties.
	 */
	public function test_create_payment_token_for_user_returns_null_when_missing_required_properties() {
		$becs_debit_payment_method = new WC_Stripe_UPE_Payment_Method_Becs_Debit();
		$user_id                   = 1234;

		// Case 1: Missing 'id'.
		$payment_method_missing_id = (object) [
			WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
				'bsb_number'  => '000000',
				'fingerprint' => 'xOFKhDPeaJYLGTc2',
				'last4'       => '3456',
			],
		];

		$token = $becs_debit_payment_method->create_payment_token_for_user( $user_id, $payment_method_missing_id );
		$this->assertNull( $token, 'Token should be null when the "id" property is missing.' );

		// Case 2: Missing specific properties for BECS.
		$payment_method_missing_becs_properties = (object) [
			'id' => 'pm_test_becs_123',
		];

		$token = $becs_debit_payment_method->create_payment_token_for_user( $user_id, $payment_method_missing_becs_properties );
		$this->assertNull( $token, sprintf( 'Token should be null when the "%s" property is missing.', WC_Stripe_Payment_Methods::BECS_DEBIT ) );
	}

	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Becs_Debit::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Becs_Debit::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'AU is supported'     => [ WC_Stripe_Country_Code::AUSTRALIA, true ],
			'US is not supported' => [ WC_Stripe_Country_Code::UNITED_STATES, false ],
			'GB is not supported' => [ WC_Stripe_Country_Code::UNITED_KINGDOM, false ],
			'ZZ is not supported' => [ 'ZZ', false ],
		];
	}
}
