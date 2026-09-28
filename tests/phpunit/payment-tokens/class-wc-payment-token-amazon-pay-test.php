<?php

/**
 * Class WC_Payment_Token_Amazon_Pay_Test tests.
 */
class WC_Payment_Token_Amazon_Pay_Test extends WP_UnitTestCase {

	/**
	 * Instance of WC_Payment_Token_Amazon_Pay to test.
	 *
	 * @var WC_Payment_Token_Amazon_Pay
	 */
	protected $token;

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
	public function test_token_type_is_amazon_pay(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			$this->token->get_type(),
			'The token "type" property should match amazon_pay.'
		);
	}

	/**
	 * Test for `get_display_name()` when email is set.
	 */
	public function test_get_display_name(): void {
		$this->token->set_email( 'john.doe@example.com' );

		$this->assertEquals(
			'Amazon Pay (john.doe@example.com)',
			$this->token->get_display_name(),
			'get_display_name() should include the email address.'
		);
	}

	/**
	 * Test for `get_display_name()` when email is empty.
	 */
	public function test_get_display_name_empty_email(): void {
		$this->token->set_email( '' );

		$this->assertEquals(
			'Amazon Pay ()',
			$this->token->get_display_name(),
			'get_display_name() should format properly when email is empty.'
		);
	}

	/**
	 * Test for `validate`.
	 *
	 * @param string|null $token_value The token string to set.
	 * @param bool        $expected    Whether the token should pass validation.
	 * @return void
	 * @dataProvider provide_test_validate
	 */
	public function test_validate( ?string $token_value, bool $expected ): void {
		if ( null !== $token_value ) {
			$this->token->set_token( $token_value );
		}
		$this->assertSame( $expected, $this->token->validate() );
	}

	/**
	 * Data provider for `test_validate`.
	 *
	 * @return array
	 */
	public function provide_test_validate(): array {
		return [
			'valid token'  => [ 'pm_test_amazon_123', true ],
			'empty token'  => [ '', false ],
			'no token set' => [ null, false ],
		];
	}

	/**
	 * Test setting and retrieving the email property.
	 */
	public function test_set_and_get_email(): void {
		$this->token->set_email( 'john.doe@example.com' );
		$this->assertEquals(
			'john.doe@example.com',
			$this->token->get_email(),
			'The email property should match the value that was set.'
		);
	}

	/**
	 * Test for `is_equal_payment_method()`.
	 *
	 * @param string $token_email         The email set on the token.
	 * @param object $payment_method_mock The payment method mock.
	 * @param bool   $expected            The expected result.
	 * @param string $message             The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_email, object $payment_method_mock, bool $expected, string $message ): void {
		$this->token->set_email( $token_email );

		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method_mock ), $message );
	}

	/**
	 * Data provider for `test_is_equal_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		return [
			'type and email match'             => [
				'john.doe@example.com',
				(object) [
					'type'            => WC_Stripe_Payment_Methods::AMAZON_PAY,
					'billing_details' => (object) [
						'email' => 'john.doe@example.com',
					],
				],
				true,
				'is_equal_payment_method() should return true when type and email match.',
			],
			'mismatched type'                  => [
				'john.doe@example.com',
				(object) [
					'type'            => 'card',
					'billing_details' => (object) [
						'email' => 'john.doe@example.com',
					],
				],
				false,
				'is_equal_payment_method() should return false when the type is not amazon_pay.',
			],
			'mismatched email'                 => [
				'john.doe@example.com',
				(object) [
					'type'            => WC_Stripe_Payment_Methods::AMAZON_PAY,
					'billing_details' => (object) [
						'email' => 'different_email@example.com',
					],
				],
				false,
				'is_equal_payment_method() should return false when the email does not match.',
			],
			'empty token email'                => [
				'',
				(object) [
					'type'            => WC_Stripe_Payment_Methods::AMAZON_PAY,
					'billing_details' => (object) [
						'email' => '',
					],
				],
				true,
				'is_equal_payment_method() should return true when both emails are empty.',
			],
			'missing billing_details property' => [
				'john.doe@example.com',
				(object) [
					'type' => WC_Stripe_Payment_Methods::AMAZON_PAY,
				],
				false,
				'is_equal_payment_method() should return false when the billing_details property is missing.',
			],
			'missing email in billing_details' => [
				'john.doe@example.com',
				(object) [
					'type'            => WC_Stripe_Payment_Methods::AMAZON_PAY,
					'billing_details' => (object) [],
				],
				false,
				'is_equal_payment_method() should return false when the email property is missing from billing_details.',
			],
		];
	}
}
