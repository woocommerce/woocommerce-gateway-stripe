<?php

/**
 * Class WC_Payment_Token_CashApp tests.
 */
class WC_Payment_Token_CashApp_Test extends WP_UnitTestCase {

	/**
	 * WC_Payment_Token_CashApp instance.
	 *
	 * @var WC_Payment_Token_CashApp
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_CashApp();
	}

	/**
	 * Test that the token type is correctly set as cashapp.
	 */
	public function test_token_type_is_cashapp(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::CASHAPP_PAY,
			$this->token->get_type(),
			'The token "type" property should match CASHAPP_PAY.'
		);
	}

	/**
	 * Test for `get_display_name()` when a cashtag is set.
	 */
	public function test_get_display_name_with_cashtag(): void {
		$this->token->set_cashtag( '$testuser' );

		$this->assertEquals(
			'Cash App Pay ($testuser)',
			$this->token->get_display_name(),
			'get_display_name() should include the cashtag when one is set.'
		);
	}

	/**
	 * Test for `get_display_name()` when no cashtag is set.
	 */
	public function test_get_display_name_without_cashtag(): void {
		$this->assertEquals(
			'Cash App Pay',
			$this->token->get_display_name(),
			'get_display_name() should return the generic label when no cashtag is set.'
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
			'valid token'  => [ 'pm_test_cashapp_123', true ],
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
			'cashtag' => [ 'set_cashtag', 'get_cashtag', '$cashuser', 'The cashtag property should match the value that was set.' ],
		];
	}

	/**
	 * Test for `is_equal_payment_method()`.
	 *
	 * @param string $token_cashtag          The cashtag set on the token.
	 * @param string $payment_method_type    The type set on the payment method mock.
	 * @param string $payment_method_cashtag The cashtag set on the payment method mock.
	 * @param bool   $expected               The expected result.
	 * @param string $message                The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_cashtag, string $payment_method_type, string $payment_method_cashtag, bool $expected, string $message ): void {
		$this->token->set_cashtag( $token_cashtag );

		$payment_method_mock = (object) [
			'type'    => $payment_method_type,
			'cashapp' => (object) [
				'cashtag' => $payment_method_cashtag,
			],
		];

		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method_mock ), $message );
	}

	/**
	 * Data provider for `test_is_equal_payment_method()`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		return [
			'type and cashtag match' => [ '$cashuser', WC_Stripe_Payment_Methods::CASHAPP_PAY, '$cashuser', true, 'is_equal_payment_method() should return true when type and cashtag match.' ],
			'mismatched type'        => [ '$cashuser', 'card', '$cashuser', false, 'is_equal_payment_method() should return false when the type is not cashapp.' ],
			'mismatched cashtag'     => [ '$cashuser', WC_Stripe_Payment_Methods::CASHAPP_PAY, '$otheruser', false, 'is_equal_payment_method() should return false when the cashtag does not match.' ],
			'empty token cashtag'    => [ '', WC_Stripe_Payment_Methods::CASHAPP_PAY, '', true, 'is_equal_payment_method() should return true when both cashtags are empty.' ],
		];
	}
}
