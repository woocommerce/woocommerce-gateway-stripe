<?php
/**
 * Class WC_Payment_Token_ACH tests.
 */
class WC_Payment_Token_ACH_Test extends WP_UnitTestCase {

	/**
	 * WC_Payment_Token_ACH instance.
	 *
	 * @var WC_Payment_Token_ACH
	 */
	protected $token;

	protected function setUp(): void {
		$this->token = new WC_Payment_Token_ACH();
	}

	public function test_get_display_name() {
		$this->token->set_bank_name( 'Test Bank' );
		$this->token->set_account_type( 'checking' );
		$this->token->set_last4( '1234' );

		$expected_display_name = 'Checking account ending in 1234 (Test Bank)';
		$this->assertEquals( $expected_display_name, $this->token->get_display_name() );
	}

	public function test_validate() {
		$this->token->set_token( 'pm_test_1234' );
		$this->token->set_bank_name( 'Test Bank' );
		$this->token->set_account_type( 'checking' );
		$this->token->set_last4( '1234' );
		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->assertTrue( $this->token->validate() );

		$this->token->set_last4( '' );
		$this->assertFalse( $this->token->validate() );

		$this->token->set_last4( '1234' );
		$this->token->set_bank_name( '' );
		$this->assertFalse( $this->token->validate() );

		$this->token->set_bank_name( 'Test Bank' );
		$this->token->set_account_type( '' );
		$this->assertFalse( $this->token->validate() );

		$this->token->set_account_type( 'checking' );
		$this->token->set_fingerprint( '' );
		$this->assertFalse( $this->token->validate() );

		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->token->set_token( '' );
		$this->assertFalse( $this->token->validate() );
	}

	public function test_get_and_set_bank_name() {
		$this->token->set_bank_name( 'Test Bank' );
		$this->assertEquals( 'Test Bank', $this->token->get_bank_name() );
	}

	public function test_get_and_set_account_type() {
		$this->token->set_account_type( 'savings' );
		$this->assertEquals( 'savings', $this->token->get_account_type() );
	}

	public function test_get_and_set_last4() {
		$this->token->set_last4( '1234' );
		$this->assertEquals( '1234', $this->token->get_last4() );
	}

	public function test_is_equal_payment_method() {
		$payment_method                                   = new stdClass();
		$payment_method->type                             = WC_Stripe_Payment_Methods::ACH;
		$payment_method->{WC_Stripe_Payment_Methods::ACH} = new stdClass();
		$payment_method->{WC_Stripe_Payment_Methods::ACH}->fingerprint = 'test_fingerprint';

		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->assertTrue( $this->token->is_equal_payment_method( $payment_method ) );

		$this->token->set_fingerprint( 'different_fingerprint' );
		$this->assertFalse( $this->token->is_equal_payment_method( $payment_method ) );
	}
}
