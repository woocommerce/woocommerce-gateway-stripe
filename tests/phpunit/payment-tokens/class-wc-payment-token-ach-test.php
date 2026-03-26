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
			'set_token'        => 'pm_test_1234',
			'set_bank_name'    => 'Test Bank',
			'set_account_type' => 'checking',
			'set_last4'        => '1234',
			'set_fingerprint'  => 'test_fingerprint',
		];
		return [
			'all valid'            => [ $valid, true ],
			'missing last4'        => [ array_merge( $valid, [ 'set_last4' => '' ] ), false ],
			'missing bank_name'    => [ array_merge( $valid, [ 'set_bank_name' => '' ] ), false ],
			'missing account_type' => [ array_merge( $valid, [ 'set_account_type' => '' ] ), false ],
			'missing fingerprint'  => [ array_merge( $valid, [ 'set_fingerprint' => '' ] ), false ],
			'missing token'        => [ array_merge( $valid, [ 'set_token' => '' ] ), false ],
		];
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
			'bank_name'    => [ 'set_bank_name', 'get_bank_name', 'Test Bank' ],
			'account_type' => [ 'set_account_type', 'get_account_type', 'savings' ],
			'last4'        => [ 'set_last4', 'get_last4', '1234' ],
		];
	}

	/**
	 * Test for `is_equal_payment_method`.
	 *
	 * @param string $token_fingerprint    The fingerprint set on the token.
	 * @param bool   $expected             Whether the payment method should be considered equal.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_fingerprint, bool $expected ) {
		$payment_method       = new stdClass();
		$payment_method->type = WC_Stripe_Payment_Methods::ACH;
		$payment_method->{WC_Stripe_Payment_Methods::ACH}              = new stdClass();
		$payment_method->{WC_Stripe_Payment_Methods::ACH}->fingerprint = 'test_fingerprint';

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
			'matching fingerprint'    => [ 'test_fingerprint', true ],
			'mismatched fingerprint'  => [ 'different_fingerprint', false ],
		];
	}
}
