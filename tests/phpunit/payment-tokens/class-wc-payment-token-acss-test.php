<?php

/**
 * Class WC_Payment_Token_ACSS_Test tests.
 */
class WC_Payment_Token_ACSS_Test extends WP_UnitTestCase {

	/**
	 * Instance of WC_Payment_Token_ACSS to test.
	 *
	 * @var WC_Payment_Token_ACSS
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_ACSS();
	}

	/**
	 * Test that the token type is correctly set as acss_debit.
	 */
	public function test_token_type_is_acss_debit(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
			$this->token->get_type(),
			'The token "type" property should match ACSS_DEBIT.'
		);
	}

	/**
	 * Test for `get_display_name()` when bank name and last4 are set.
	 */
	public function test_get_display_name(): void {
		$this->token->set_bank_name( 'Test Bank' );
		$this->token->set_last4( '9876' );

		$this->assertEquals(
			'Test Bank ending in 9876',
			$this->token->get_display_name(),
			'get_display_name() should format properly with bank name and last4.'
		);
	}

	/**
	 * Test for `get_display_name()` with partial or empty values.
	 *
	 * @param string $bank_name The bank name to set.
	 * @param string $last4     The last 4 digits to set.
	 * @param string $expected  The expected display name.
	 * @return void
	 * @dataProvider provide_test_get_display_name_empty_values
	 */
	public function test_get_display_name_empty_values( string $bank_name, string $last4, string $expected ): void {
		$this->token->set_bank_name( $bank_name );
		$this->token->set_last4( $last4 );

		$this->assertEquals( $expected, $this->token->get_display_name() );
	}

	/**
	 * Data provider for `test_get_display_name_empty_values`.
	 *
	 * @return array
	 */
	public function provide_test_get_display_name_empty_values(): array {
		return [
			'empty bank name' => [ '', '9876', ' ending in 9876' ],
			'empty last4'     => [ 'Test Bank', '', 'Test Bank ending in ' ],
			'both empty'      => [ '', '', ' ending in ' ],
		];
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
			'valid token'  => [ 'pm_test_acss_123', true ],
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
			'token'               => [ 'set_token', 'get_token', 'pm_test_1234', 'The token property should match the value that was set.' ],
			'fingerprint'         => [ 'set_fingerprint', 'get_fingerprint', 'test_fingerprint', 'The fingerprint property should match the value that was set.' ],
			'bank_name'           => [ 'set_bank_name', 'get_bank_name', 'Sample Bank', 'The bank_name property should match the value that was set.' ],
			'last4'               => [ 'set_last4', 'get_last4', '5678', 'The last4 property should match the value that was set.' ],
			'payment_method_type' => [ 'set_payment_method_type', 'get_payment_method_type', 'acss_debit_test', 'The payment_method_type property should match the value that was set.' ],
		];
	}

	/**
	 * Test for `is_equal_payment_method()`.
	 *
	 * @param string $token_fingerprint   The fingerprint set on the token.
	 * @param object $payment_method_mock The payment method mock.
	 * @param bool   $expected            The expected result.
	 * @param string $message             The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_fingerprint, object $payment_method_mock, bool $expected, string $message ): void {
		$this->token->set_fingerprint( $token_fingerprint );

		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method_mock ), $message );
	}

	/**
	 * Data provider for `test_is_equal_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		return [
			'type and fingerprint match'        => [
				'test_fp_123',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::ACSS_DEBIT,
					WC_Stripe_Payment_Methods::ACSS_DEBIT => (object) [
						'fingerprint' => 'test_fp_123',
						'last4'       => '9876',
					],
				],
				true,
				'is_equal_payment_method() should return true when type and fingerprint match.',
			],
			'mismatched type'                   => [
				'test_fp_abc',
				(object) [
					'type'                                => 'card',
					WC_Stripe_Payment_Methods::ACSS_DEBIT => (object) [
						'fingerprint' => 'test_fp_abc',
						'last4'       => '9876',
					],
				],
				false,
				'is_equal_payment_method() should return false when the type is not acss_debit.',
			],
			'mismatched fingerprint'            => [
				'test_fp_123',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::ACSS_DEBIT,
					WC_Stripe_Payment_Methods::ACSS_DEBIT => (object) [
						'fingerprint' => 'different_fp',
						'last4'       => '9876',
					],
				],
				false,
				'is_equal_payment_method() should return false when the fingerprint does not match.',
			],
			'empty token fingerprint'           => [
				'',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::ACSS_DEBIT,
					WC_Stripe_Payment_Methods::ACSS_DEBIT => (object) [
						'fingerprint' => '',
						'last4'       => '9876',
					],
				],
				true,
				'is_equal_payment_method() should return true when both fingerprints are empty.',
			],
			'missing acss_debit property'       => [
				'test_fp_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::ACSS_DEBIT,
				],
				false,
				'is_equal_payment_method() should return false when the acss_debit property is missing.',
			],
			'missing fingerprint in acss_debit' => [
				'test_fp_123',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::ACSS_DEBIT,
					WC_Stripe_Payment_Methods::ACSS_DEBIT => (object) [
						'last4' => '9876',
					],
				],
				false,
				'is_equal_payment_method() should return false when the fingerprint property is missing from acss_debit.',
			],
		];
	}
}
