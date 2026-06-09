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

	/**
	 * Test getter/setter pairs.
	 *
	 * @param string $setter The setter method name.
	 * @param string $getter The getter method name.
	 * @param string $value  The value to set and retrieve.
	 * @return void
	 * @dataProvider provide_test_getters_setters
	 */
	public function test_getters_setters( string $setter, string $getter, string $value ) {
		$this->token->$setter( $value );
		$this->assertEquals( $value, $this->token->$getter() );
	}

	/**
	 * Data provider for `test_getters_setters`.
	 *
	 * @return array
	 */
	public function provide_test_getters_setters(): array {
		return [
			'token'       => [ 'set_token', 'get_token', 'pm_test_1234' ],
			'fingerprint' => [ 'set_fingerprint', 'get_fingerprint', 'test_fingerprint' ],
			'bank_name'   => [ 'set_bank_name', 'get_bank_name', 'Sample Bank' ],
			'last4'       => [ 'set_last4', 'get_last4', '5678' ],
		];
	}

	/**
	 * Test for `is_equal_payment_method`.
	 *
	 * @param string $token_fingerprint The fingerprint set on the token.
	 * @param bool   $expected          Whether the payment method should be considered equal.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_fingerprint, bool $expected ) {
		$payment_method       = new stdClass();
		$payment_method->type = WC_Stripe_Payment_Methods::ACSS_DEBIT;
		$payment_method->{WC_Stripe_Payment_Methods::ACSS_DEBIT}              = new stdClass();
		$payment_method->{WC_Stripe_Payment_Methods::ACSS_DEBIT}->fingerprint = 'test_fingerprint';

		$this->token->set_fingerprint( $token_fingerprint );
		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method ) );
	}

	/**
	 * Data provider for `test_is_equal_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		return [
			'matching fingerprint'   => [ 'test_fingerprint', true ],
			'mismatched fingerprint' => [ 'different_fingerprint', false ],
		];
	}
}
