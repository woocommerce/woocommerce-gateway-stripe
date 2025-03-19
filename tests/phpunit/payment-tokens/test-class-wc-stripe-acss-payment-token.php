<?php
/**
 * Class WC_Payment_Token_ACSS_Test tests.
 */
class WC_Payment_Token_ACSS_Test extends WP_UnitTestCase {

	/**
	 * WC_Payment_Token_ACSS instance.
	 *
	 * @var WC_Payment_Token_ACSS
	 */
	protected $token;

	protected function setUp(): void {
		$this->token = new WC_Payment_Token_ACSS();
	}

	public function test_get_display_name() {
		$this->token->set_bank_name( 'Test Bank' );
		$this->token->set_last4( '9876' );

		$expected_display_name = 'Test Bank ending in 9876';
		$this->assertEquals( $expected_display_name, $this->token->get_display_name() );
	}

	public function test_get_and_set_token() {
		$this->token->set_token( 'pm_test_1234' );
		$this->assertEquals( 'pm_test_1234', $this->token->get_token() );
	}

	public function test_get_and_set_fingerprint() {
		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->assertEquals( 'test_fingerprint', $this->token->get_fingerprint() );
	}

	public function test_get_and_set_bank_name() {
		$this->token->set_bank_name( 'Sample Bank' );
		$this->assertEquals( 'Sample Bank', $this->token->get_bank_name() );
	}

	public function test_get_and_set_last4() {
		$this->token->set_last4( '5678' );
		$this->assertEquals( '5678', $this->token->get_last4() );
	}

	public function test_is_equal_payment_method() {
		$payment_method = new stdClass();
		$payment_method->type = WC_Stripe_Payment_Methods::ACSS_DEBIT;
		$payment_method->{WC_Stripe_Payment_Methods::ACSS_DEBIT} = new stdClass();
		$payment_method->{WC_Stripe_Payment_Methods::ACSS_DEBIT}->fingerprint = 'test_fingerprint';

		$this->token->set_fingerprint( 'test_fingerprint' );
		$this->assertTrue( $this->token->is_equal_payment_method( $payment_method ) );

		$this->token->set_fingerprint( 'different_fingerprint' );
		$this->assertFalse( $this->token->is_equal_payment_method( $payment_method ) );
	}
}
