<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Method_ACH;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_ACH.
 */
class WC_Stripe_UPE_Payment_Method_ACH_Test extends WP_UnitTestCase {
	/**
	 * Tests for create_payment_token_for_user.
	 */
	public function test_create_payment_token_for_user() {
		$payment_method = (object) [
			'id'                           => 'pm_test_ach_123',
			WC_Stripe_Payment_Methods::ACH => (object) [
				'last4'        => '6789',
				'bank_name'    => 'Test Bank',
				'account_type' => 'checking',
				'fingerprint'  => 'fp_test_123',
			],
		];

		$ach_payment_method = new WC_Stripe_UPE_Payment_Method_ACH();
		$user_id            = 1234;

		$token = $ach_payment_method->create_payment_token_for_user( $user_id, $payment_method );

		$this->assertInstanceOf( 'WC_Payment_Token_ACH', $token );
		$this->assertEquals( $user_id, $token->get_user_id() );
		$this->assertEquals( $payment_method->id, $token->get_token() );
		$this->assertEquals( $payment_method->{WC_Stripe_Payment_Methods::ACH}->last4, $token->get_last4() );
		$this->assertEquals( $payment_method->{WC_Stripe_Payment_Methods::ACH}->bank_name, $token->get_bank_name() );
		$this->assertEquals( $payment_method->{WC_Stripe_Payment_Methods::ACH}->account_type, $token->get_account_type() );
		$this->assertEquals( $payment_method->{WC_Stripe_Payment_Methods::ACH}->fingerprint, $token->get_fingerprint() );
	}

	/**
	 * Tests that create_payment_token_for_user returns null when $payment_method is missing
	 * the `id` or `us_bank_account` properties.
	 */
	public function test_create_payment_token_for_user_returns_null_when_missing_required_properties() {
		$ach_payment_method = new WC_Stripe_UPE_Payment_Method_ACH();
		$user_id            = 1234;

		// Case 1: Missing 'id'.
		$payment_method_missing_id = (object) [
			WC_Stripe_Payment_Methods::ACH => (object) [
				'last4'        => '6789',
				'bank_name'    => 'Test Bank',
				'account_type' => 'checking',
				'fingerprint'  => 'fp_test_123',
			],
		];

		$token = $ach_payment_method->create_payment_token_for_user( $user_id, $payment_method_missing_id );
		$this->assertNull( $token, 'Token should be null when the "id" property is missing.' );

		// Case 2: Missing 'us_bank_account'.
		$payment_method_missing_us_bank_account = (object) [
			'id' => 'pm_test_ach_123',
		];

		$token = $ach_payment_method->create_payment_token_for_user( $user_id, $payment_method_missing_us_bank_account );
		$this->assertNull( $token, 'Token should be null when the "us_bank_account" property is missing.' );
	}
}
