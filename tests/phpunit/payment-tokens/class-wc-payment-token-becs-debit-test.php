<?php

/**
 * Class WC_Payment_Token_Becs_Debit tests.
 */
class WC_Payment_Token_Becs_Debit_Test extends WP_UnitTestCase {

	/**
	 * Instance of WC_Payment_Token_Becs_Debit to test.
	 *
	 * @var WC_Payment_Token_Becs_Debit
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Payment_Token_Becs_Debit();
	}

	/**
	 * Test that the token type is correctly set as becs_debit (au_becs_debit).
	 */
	public function test_token_type_is_becs_debit(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::BECS_DEBIT,
			$this->token->get_type(),
			'The token "type" property should match BECS_DEBIT.'
		);
	}

	/**
	 * Test for `get_display_name()` when last4 is set.
	 */
	public function test_get_display_name(): void {
		$this->token->set_last4( '4356' );

		$expected_display_name = 'BECS Direct Debit ending in 4356';
		$this->assertEquals( $expected_display_name, $this->token->get_display_name() );
	}

	/**
	 * Test for `get_display_name()` when last4 is empty.
	 */
	public function test_get_display_name_empty_last4(): void {
		$this->token->set_last4( '' );

		$expected_display_name = 'BECS Direct Debit ending in ';
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
			'set_token'       => 'pm_test_becs_123',
			'set_last4'       => '4356',
			'set_fingerprint' => 'test_fingerprint',
		];
		return [
			'all valid'           => [ $valid, true ],
			'missing last4'       => [ array_merge( $valid, [ 'set_last4' => '' ] ), false ],
			'missing fingerprint' => [ array_merge( $valid, [ 'set_fingerprint' => '' ] ), false ],
			'missing token'       => [ array_merge( $valid, [ 'set_token' => '' ] ), false ],
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
			'last4'               => [ 'set_last4', 'get_last4', '4356', 'The last4 property should match the value that was set.' ],
			'payment_method_type' => [ 'set_payment_method_type', 'get_payment_method_type', 'au_becs_debit_test', 'The payment_method_type property should match the value that was set.' ],
			'fingerprint'         => [ 'set_fingerprint', 'get_fingerprint', 'test_fp_becs_123', 'The fingerprint property should match the value that was set.' ],
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
			'type and fingerprint match'           => [
				'test_fp_becs_123',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::BECS_DEBIT,
					WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
						'fingerprint' => 'test_fp_becs_123',
						'last4'       => '4356',
					],
				],
				true,
				'is_equal_payment_method() should return true when type and fingerprint match.',
			],
			'mismatched type'                      => [
				'test_fp_becs_123',
				(object) [
					'type'                                => 'card',
					WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
						'fingerprint' => 'test_fp_becs_123',
						'last4'       => '4356',
					],
				],
				false,
				'is_equal_payment_method() should return false when the type is not au_becs_debit.',
			],
			'mismatched fingerprint'               => [
				'test_fp_becs_123',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::BECS_DEBIT,
					WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
						'fingerprint' => 'different_fp',
						'last4'       => '4356',
					],
				],
				false,
				'is_equal_payment_method() should return false when the fingerprint does not match.',
			],
			'empty token fingerprint'              => [
				'',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::BECS_DEBIT,
					WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
						'fingerprint' => '',
						'last4'       => '4356',
					],
				],
				true,
				'is_equal_payment_method() should return true when both fingerprints are empty.',
			],
			'missing au_becs_debit property'       => [
				'test_fp_becs_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::BECS_DEBIT,
				],
				false,
				'is_equal_payment_method() should return false when the au_becs_debit property is missing.',
			],
			'missing fingerprint in au_becs_debit' => [
				'test_fp_becs_123',
				(object) [
					'type'                                => WC_Stripe_Payment_Methods::BECS_DEBIT,
					WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
						'last4' => '4356',
					],
				],
				false,
				'is_equal_payment_method() should return false when the fingerprint property is missing from au_becs_debit.',
			],
		];
	}
}
