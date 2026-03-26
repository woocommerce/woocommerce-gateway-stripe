<?php

/**
 * Class WC_Payment_Token_Amazon_Pay_Test tests.
 *
 */
class WC_Payment_Token_Amazon_Pay_Test extends WP_UnitTestCase {

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
	 * Test for `is_equal_payment_method`.
	 *
	 * @param string $payment_method_type  The type set on the payment method mock.
	 * @param string $payment_method_email The email set on the payment method mock's billing_details.
	 * @param bool   $expected             The expected result.
	 * @param string $message              The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $payment_method_type, string $payment_method_email, bool $expected, string $message ) {
		$payment_method_mock = (object) [
			'type'            => $payment_method_type,
			'billing_details' => (object) [
				'email' => $payment_method_email,
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
			'type and email match'   => [ WC_Stripe_Payment_Methods::AMAZON_PAY, 'john.doe@example.com', true, 'is_equal_payment_method() should return true when type and email match.' ],
			'mismatched type'        => [ 'card', 'john.doe@example.com', false, 'is_equal_payment_method() should return false when the type is not amazon_pay.' ],
			'mismatched email'       => [ WC_Stripe_Payment_Methods::AMAZON_PAY, 'different_email@example.com', false, 'is_equal_payment_method() should return false when the email does not match.' ],
		];
	}
}
