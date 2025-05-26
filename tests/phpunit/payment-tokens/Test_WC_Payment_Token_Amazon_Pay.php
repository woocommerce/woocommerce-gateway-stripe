<?php

/**
 * Class Test_WC_Payment_Token_Amazon_Pay tests.
 *
 */
class Test_WC_Payment_Token_Amazon_Pay extends WP_UnitTestCase {

	/**
	 * Instance of WC_Payment_Token_Amazon_Pay to test.
	 *
	 * @var WC_Payment_Token_Amazon_Pay
	 */
	private $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_Amazon_Pay();
		$this->token->set_email( 'john.doe@example.com' );
	}

	/**
	 * Test that the token type is correctly set as amazon_pay.
	 */
	public function test_token_type_is_amazon_pay() {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			$this->token->get_type(),
			'The token "type" property should match amazon_pay.'
		);
	}

	/**
	 * Test setting and retrieving the email property.
	 */
	public function test_set_and_get_email() {
		$this->token->set_email( 'john.doe@example.com' );
		$this->assertEquals(
			'john.doe@example.com',
			$this->token->get_email(),
			'The email property should match the value that was set.'
		);
	}

	/**
	 * Test is_equal_payment_method() returns true when type and email match.
	 */
	public function test_is_equal_payment_method_returns_true_on_valid_object() {
		$payment_method_mock = (object) [
			'type'            => WC_Stripe_Payment_Methods::AMAZON_PAY,
			'billing_details' => (object) [
				'email' => 'john.doe@example.com',
			],
		];

		$this->assertTrue(
			$this->token->is_equal_payment_method( $payment_method_mock ),
			'is_equal_payment_method() should return true when type and email match.'
		);
	}

	/**
	 * Test is_equal_payment_method() returns false for a mismatched type.
	 */
	public function test_is_equal_payment_method_returns_false_mismatched_type() {
		$payment_method_mock = (object) [
			'type'            => 'card',
			'billing_details' => (object) [
				'email' => 'john.doe@example.com',
			],
		];

		$this->assertFalse(
			$this->token->is_equal_payment_method( $payment_method_mock ),
			'is_equal_payment_method() should return false when the type is not amazon_pay.'
		);
	}

	/**
	 * Test is_equal_payment_method() returns false for a mismatched email.
	 */
	public function test_is_equal_payment_method_returns_false_mismatched_email() {
		$payment_method_mock = (object) [
			'type'            => WC_Stripe_Payment_Methods::AMAZON_PAY,
			'billing_details' => (object) [
				'email' => 'different_email@example.com',
			],
		];

		$this->assertFalse(
			$this->token->is_equal_payment_method( $payment_method_mock ),
			'is_equal_payment_method() should return false when the email does not match.'
		);
	}
}
