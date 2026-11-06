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

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_ACH();
	}

	/**
	 * Test that the token type is correctly set as us_bank_account (ACH).
	 */
	public function test_token_type_is_ach(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::ACH,
			$this->token->get_type(),
			'The token "type" property should match ACH.'
		);
	}

	/**
	 * Test for `get_display_name()`.
	 */
	public function test_get_display_name(): void {
		$this->token->set_bank_name( 'Test Bank' );
		$this->token->set_account_type( 'checking' );
		$this->token->set_last4( '1234' );

		$expected_display_name = 'Checking account ending in 1234 (Test Bank)';
		$this->assertEquals( $expected_display_name, $this->token->get_display_name() );
	}

	/**
	 * Test for `get_display_name()` with partial or empty values.
	 *
	 * @param string $bank_name    The bank name to set.
	 * @param string $account_type The account type to set.
	 * @param string $last4        The last 4 digits to set.
	 * @param string $expected     The expected display name.
	 * @return void
	 * @dataProvider provide_test_get_display_name_variations
	 */
	public function test_get_display_name_variations( string $bank_name, string $account_type, string $last4, string $expected ): void {
		$this->token->set_bank_name( $bank_name );
		$this->token->set_account_type( $account_type );
		$this->token->set_last4( $last4 );

		$this->assertEquals( $expected, $this->token->get_display_name() );
	}

	/**
	 * Data provider for `test_get_display_name_variations`.
	 *
	 * @return array
	 */
	public function provide_test_get_display_name_variations(): array {
		return [
			'savings account'    => [ 'First National', 'savings', '5678', 'Savings account ending in 5678 (First National)' ],
			'empty bank name'    => [ '', 'checking', '1234', 'Checking account ending in 1234 ()' ],
			'empty account type' => [ 'Test Bank', '', '1234', ' account ending in 1234 (Test Bank)' ],
			'empty last4'        => [ 'Test Bank', 'checking', '', 'Checking account ending in  (Test Bank)' ],
		];
	}

	/**
	 * Test for `validate`.
	 *
	 * @param array $fields   Property setters (method name → value) to apply to the token.
	 * @param bool  $expected Whether the token should pass validation.
	 * @return void
	 * @dataProvider provide_test_validate
	 */
	public function test_validate( array $fields, bool $expected ): void {
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
			'token'        => [ 'set_token', 'get_token', 'pm_test_ach_1234', 'The token property should match the value that was set.' ],
			'bank_name'    => [ 'set_bank_name', 'get_bank_name', 'Test Bank', 'The bank_name property should match the value that was set.' ],
			'account_type' => [ 'set_account_type', 'get_account_type', 'savings', 'The account_type property should match the value that was set.' ],
			'last4'        => [ 'set_last4', 'get_last4', '1234', 'The last4 property should match the value that was set.' ],
			'fingerprint'  => [ 'set_fingerprint', 'get_fingerprint', 'test_fingerprint_ach', 'The fingerprint property should match the value that was set.' ],
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
			'type and fingerprint match'             => [
				'test_fingerprint',
				(object) [
					'type'                         => WC_Stripe_Payment_Methods::ACH,
					WC_Stripe_Payment_Methods::ACH => (object) [
						'fingerprint' => 'test_fingerprint',
						'last4'       => '1234',
					],
				],
				true,
				'is_equal_payment_method() should return true when type and fingerprint match.',
			],
			'mismatched type'                        => [
				'test_fingerprint',
				(object) [
					'type'                         => 'card',
					WC_Stripe_Payment_Methods::ACH => (object) [
						'fingerprint' => 'test_fingerprint',
						'last4'       => '1234',
					],
				],
				false,
				'is_equal_payment_method() should return false when the type is not ACH (us_bank_account).',
			],
			'mismatched fingerprint'                 => [
				'test_fingerprint',
				(object) [
					'type'                         => WC_Stripe_Payment_Methods::ACH,
					WC_Stripe_Payment_Methods::ACH => (object) [
						'fingerprint' => 'different_fingerprint',
						'last4'       => '1234',
					],
				],
				false,
				'is_equal_payment_method() should return false when the fingerprint does not match.',
			],
			'empty token fingerprint'                => [
				'',
				(object) [
					'type'                         => WC_Stripe_Payment_Methods::ACH,
					WC_Stripe_Payment_Methods::ACH => (object) [
						'fingerprint' => '',
						'last4'       => '1234',
					],
				],
				true,
				'is_equal_payment_method() should return true when both fingerprints are empty.',
			],
			'missing us_bank_account property'       => [
				'test_fingerprint',
				(object) [
					'type' => WC_Stripe_Payment_Methods::ACH,
				],
				false,
				'is_equal_payment_method() should return false when the us_bank_account property is missing.',
			],
			'missing fingerprint in us_bank_account' => [
				'test_fingerprint',
				(object) [
					'type'                         => WC_Stripe_Payment_Methods::ACH,
					WC_Stripe_Payment_Methods::ACH => (object) [
						'last4' => '1234',
					],
				],
				false,
				'is_equal_payment_method() should return false when the fingerprint property is missing from us_bank_account.',
			],
		];
	}
}
