<?php

/**
 * Class Test_WC_Payment_Token_Bacs_Debit tests.
 *
 */
class Test_WC_Payment_Token_Bacs_Debit extends WP_UnitTestCase {

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
	 * Test setting and retrieving the last4 property.
	 */
	public function test_set_and_get_last4() {
		$this->token->set_last4( '1234' );
		$this->assertEquals(
			'1234',
			$this->token->get_last4(),
			'The last4 property should match the value that was set.'
		);
	}

	/**
	 * Test setting and retrieving the payment method type property.
	 */
	public function test_set_and_get_payment_method_type() {
		// By default it should be set to WC_Stripe_Payment_Methods::BACS_DEBIT. We will overwrite it for testing.
		$this->token->set_payment_method_type( 'bacs_debit_test' );
		$this->assertEquals(
			'bacs_debit_test',
			$this->token->get_payment_method_type(),
			'The payment_method_type property should match the value that was set.'
		);
	}

	/**
	 * Test setting and retrieving the fingerprint property (from WC_Stripe_Fingerprint_Trait).
	 */
	public function test_set_and_get_fingerprint() {
		$this->token->set_fingerprint( 'test_fp_123' );
		$this->assertEquals(
			'test_fp_123',
			$this->token->get_fingerprint(),
			'The fingerprint property should match the value that was set.'
		);
	}

	/**
	 * Test is_equal_payment_method() returns true when type and fingerprint match.
	 */
	public function test_is_equal_payment_method_returns_true_on_valid_object() {
		$this->token->set_fingerprint( 'test_fp_123' );
		$payment_method_mock = (object) [
			'type'       => WC_Stripe_Payment_Methods::BACS_DEBIT,
			'bacs_debit' => (object) [
				'fingerprint' => 'test_fp_123',
				'last4'       => '9999',
			],
		];

		$this->assertTrue(
			$this->token->is_equal_payment_method( $payment_method_mock ),
			'is_equal_payment_method() should return true when type and fingerprint match.'
		);
	}

	/**
	 * Test is_equal_payment_method() returns false for a mismatched type.
	 */
	public function test_is_equal_payment_method_returns_false_mismatched_type() {
		$this->token->set_fingerprint( 'test_fp_abc' );
		$payment_method_mock = (object) [
			'type'       => 'card', // This is not bacs_debit
			'bacs_debit' => (object) [
				'fingerprint' => 'test_fp_abc',
			],
		];

		$this->assertFalse(
			$this->token->is_equal_payment_method( $payment_method_mock ),
			'is_equal_payment_method() should return false when the type is not bacs_debit.'
		);
	}

	/**
	 * Test is_equal_payment_method() returns false for a mismatched fingerprint.
	 */
	public function test_is_equal_payment_method_returns_false_mismatched_fingerprint() {
		$this->token->set_fingerprint( 'test_fp_123' );
		$payment_method_mock = (object) [
			'type'       => WC_Stripe_Payment_Methods::BACS_DEBIT,
			'bacs_debit' => (object) [
				'fingerprint' => 'different_fp',
			],
		];

		$this->assertFalse(
			$this->token->is_equal_payment_method( $payment_method_mock ),
			'is_equal_payment_method() should return false when the fingerprint does not match.'
		);
	}
}
