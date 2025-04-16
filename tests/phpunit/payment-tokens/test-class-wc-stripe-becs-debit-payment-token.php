<?php
/**
 * Class WC_Payment_Token_Becs_Debit tests.
 */
class WC_Payment_Token_Becs_Debit_Test extends WP_UnitTestCase {

	/**
	 * WC_Payment_Token_Becs_Debit instance.
	 *
	 * @var WC_Payment_Token_Becs_Debit
	 */
	protected $token;

	protected function setUp(): void {
		$this->token = new WC_Payment_Token_Becs_Debit();
	}

	public function test_get_display_name() {
		$this->token->set_last4( '4356' );

		$expected_display_name = 'BECS Direct Debit ending in 4356';
		$this->assertEquals( $expected_display_name, $this->token->get_display_name() );
	}

	public function test_validate() {
		$this->token->set_token( 'pm_test_1234' );
		$this->token->set_last4( '1234' );
		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->assertTrue( $this->token->validate() );

		$this->token->set_last4( '' );
		$this->assertFalse( $this->token->validate() );

		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->token->set_token( '' );
		$this->assertFalse( $this->token->validate() );
	}

	public function test_get_and_set_last4() {
		$this->token->set_last4( '1234' );
		$this->assertEquals( '1234', $this->token->get_last4() );
	}

	public function test_is_equal_payment_method() {
		$payment_method       = new stdClass();
		$payment_method->type = WC_Stripe_Payment_Methods::BECS_DEBIT;
		$payment_method->{WC_Stripe_Payment_Methods::BECS_DEBIT}              = new stdClass();
		$payment_method->{WC_Stripe_Payment_Methods::BECS_DEBIT}->fingerprint = 'test_fingerprint';

		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->assertTrue( $this->token->is_equal_payment_method( $payment_method ) );

		$this->token->set_fingerprint( 'different_fingerprint' );
		$this->assertFalse( $this->token->is_equal_payment_method( $payment_method ) );
	}
}
