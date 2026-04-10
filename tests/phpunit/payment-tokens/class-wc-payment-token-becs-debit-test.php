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

	/**
	 * Test for `validate`.
	 *
	 * @param array $fields   Property setters (method name → value) to apply to the token.
	 * @param bool  $expected Whether the token should pass validation.
	 * @return void
	 * @dataProvider provide_test_validate
	 */
	public function test_validate( array $fields, bool $expected ) {
		foreach ( $fields as $setter => $value ) {
			$this->token->$setter( $value );
		}
		$this->assertSame( $expected, $this->token->validate() );
	}

	/**
	 * Data provider for `test_validate`.
	 *
	 * @return array
	 */
	public function provide_test_validate(): array {
		$valid = [
			'set_token'       => 'pm_test_1234',
			'set_last4'       => '1234',
			'set_fingerprint' => 'test_fingerprint',
		];
		return [
			'all valid'     => [ $valid, true ],
			'missing last4' => [ array_merge( $valid, [ 'set_last4' => '' ] ), false ],
			'missing token' => [ array_merge( $valid, [ 'set_token' => '' ] ), false ],
		];
	}

	public function test_get_and_set_last4() {
		$this->token->set_last4( '1234' );
		$this->assertEquals( '1234', $this->token->get_last4() );
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
		$payment_method->type = WC_Stripe_Payment_Methods::BECS_DEBIT;
		$payment_method->{WC_Stripe_Payment_Methods::BECS_DEBIT}              = new stdClass();
		$payment_method->{WC_Stripe_Payment_Methods::BECS_DEBIT}->fingerprint = 'test_fingerprint';

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
