<?php

/**
 * Class WC_Payment_Token_Link tests.
 */
class WC_Payment_Token_Link_Test extends WP_UnitTestCase {

	/**
	 * WC_Payment_Token_Link instance.
	 *
	 * @var WC_Payment_Token_Link
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_Link();
	}

	/**
	 * Test that the token type is correctly set as link.
	 */
	public function test_token_type_is_link(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::LINK,
			$this->token->get_type(),
			'The token "type" property should match LINK.'
		);
	}

	/**
	 * Test for `get_display_name()` when email is set.
	 */
	public function test_get_display_name(): void {
		$this->token->set_email( 'user@example.com' );

		$this->assertEquals(
			'Stripe Link (user@example.com)',
			$this->token->get_display_name(),
			'get_display_name() should include the email address.'
		);
	}

	/**
	 * Test for `get_display_name()` when email is empty.
	 */
	public function test_get_display_name_empty_email(): void {
		$this->assertEquals(
			'Stripe Link ()',
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
			'valid token'  => [ 'pm_test_link_123', true ],
			'empty token'  => [ '', false ],
			'no token set' => [ null, false ],
		];
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
	public function test_getters_setters( string $setter, string $getter, string $value, string $message ): void {
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
			'email' => [ 'set_email', 'get_email', 'user@example.com', 'The email property should match the value that was set.' ],
		];
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
			'type and email match'  => [
				'user@example.com',
				(object) [
					'type' => WC_Stripe_Payment_Methods::LINK,
					'link' => (object) [
						'email' => 'user@example.com',
					],
				],
				true,
				'is_equal_payment_method() should return true when type and email match.',
			],
			'mismatched type'       => [
				'user@example.com',
				(object) [
					'type' => 'card',
					'link' => (object) [
						'email' => 'user@example.com',
					],
				],
				false,
				'is_equal_payment_method() should return false when the type is not link.',
			],
			'mismatched email'      => [
				'user@example.com',
				(object) [
					'type' => WC_Stripe_Payment_Methods::LINK,
					'link' => (object) [
						'email' => 'other@example.com',
					],
				],
				false,
				'is_equal_payment_method() should return false when the email does not match.',
			],
			'empty token email'     => [
				'',
				(object) [
					'type' => WC_Stripe_Payment_Methods::LINK,
					'link' => (object) [
						'email' => '',
					],
				],
				true,
				'is_equal_payment_method() should return true when both emails are empty.',
			],
			'missing link property' => [
				'user@example.com',
				(object) [
					'type' => WC_Stripe_Payment_Methods::LINK,
				],
				false,
				'is_equal_payment_method() should return false when the link property is missing.',
			],
			'missing email in link' => [
				'user@example.com',
				(object) [
					'type' => WC_Stripe_Payment_Methods::LINK,
					'link' => (object) [],
				],
				false,
				'is_equal_payment_method() should return false when the email property is missing from link.',
			],
		];
	}
}
