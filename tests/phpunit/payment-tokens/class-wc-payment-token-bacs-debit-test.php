<?php

/**
 * Class WC_Payment_Token_Bacs_Debit tests.
 *
 */
class WC_Payment_Token_Bacs_Debit_Test extends WP_UnitTestCase {

	/**
	 * Instance of WC_Payment_Token_Bacs_Debit to test.
	 *
	 * @var WC_Payment_Token_Bacs_Debit
	 */
	private $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_Bacs_Debit();
	}

	/**
	 * Test that the token type is correctly set as bacs_debit.
	 */
	public function test_token_type_is_bacs_debit() {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::BACS_DEBIT,
			$this->token->get_type(),
			'The token "type" property should match BACS_DEBIT.'
		);
	}

	/**
	 * Test getter/setter pairs.
	 *
	 * @param string $setter  The setter method name.
	 * @param string $getter  The getter method name.
	 * @param string $value   The value to set and retrieve.
	 * @param string $message The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_getters_setters
	 */
	public function test_getters_setters( string $setter, string $getter, string $value, string $message ) {
		$this->token->$setter( $value );
		$this->assertEquals( $value, $this->token->$getter(), $message );
	}

	/**
	 * Data provider for `test_getters_setters`.
	 *
	 * @return array
	 */
	public function provide_test_getters_setters(): array {
		return [
			'last4'               => [ 'set_last4', 'get_last4', '1234', 'The last4 property should match the value that was set.' ],
			'payment_method_type' => [ 'set_payment_method_type', 'get_payment_method_type', 'bacs_debit_test', 'The payment_method_type property should match the value that was set.' ],
			'fingerprint'         => [ 'set_fingerprint', 'get_fingerprint', 'test_fp_123', 'The fingerprint property should match the value that was set.' ],
		];
	}

	/**
	 * Test for `is_equal_payment_method`.
	 *
	 * @param string $token_fingerprint          The fingerprint set on the token.
	 * @param string $payment_method_type        The type set on the payment method mock.
	 * @param string $payment_method_fingerprint The fingerprint set on the payment method mock.
	 * @param bool   $expected                   The expected result.
	 * @param string $message                    The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_fingerprint, string $payment_method_type, string $payment_method_fingerprint, bool $expected, string $message ) {
		$this->token->set_fingerprint( $token_fingerprint );
		$payment_method_mock = (object) [
			'type'                                => $payment_method_type,
			WC_Stripe_Payment_Methods::BACS_DEBIT => (object) [
				'fingerprint' => $payment_method_fingerprint,
				'last4'       => '9999',
			],
		];

		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method_mock ), $message );
	}

	/**
	 * Data provider for `test_is_equal_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		return [
			'type and fingerprint match' => [ 'test_fp_123', WC_Stripe_Payment_Methods::BACS_DEBIT, 'test_fp_123', true, 'is_equal_payment_method() should return true when type and fingerprint match.' ],
			'mismatched type'            => [ 'test_fp_abc', 'card', 'test_fp_abc', false, 'is_equal_payment_method() should return false when the type is not bacs_debit.' ],
			'mismatched fingerprint'     => [ 'test_fp_123', WC_Stripe_Payment_Methods::BACS_DEBIT, 'different_fp', false, 'is_equal_payment_method() should return false when the fingerprint does not match.' ],
		];
	}
}
