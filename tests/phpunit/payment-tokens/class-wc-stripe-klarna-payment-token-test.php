<?php

/**
 * Class WC_Stripe_Klarna_Payment tests.
 */
class WC_Stripe_Klarna_Payment_Token_Test extends WP_UnitTestCase {

	/**
	 * WC_Stripe_Klarna_Payment_Token instance.
	 *
	 * @var WC_Stripe_Klarna_Payment_Token
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Stripe_Klarna_Payment_Token();
	}

	/**
	 * Test that the token type is correctly set as klarna.
	 */
	public function test_token_type_is_klarna(): void {
		$this->assertEquals(
			WC_Stripe_Payment_Methods::KLARNA,
			$this->token->get_type(),
			'The token "type" property should match KLARNA.'
		);
	}

	/**
	 * Tests for `get_display_name()`.
	 *
	 * @param string $dob The DOB to set (empty string means no DOB).
	 * @return void
	 * @dataProvider provide_test_get_display_name
	 */
	public function test_get_display_name( string $dob ): void {
		$this->token->set_dob( $dob );
		$this->assertSame( 'Klarna', $this->token->get_display_name() );
	}

	/**
	 * Data provider for `test_get_display_name`.
	 *
	 * @return array
	 */
	public function provide_test_get_display_name(): array {
		return [
			'no DOB'   => [ '' ],
			'with DOB' => [ '2000-02-01' ],
		];
	}

	/**
	 * Test getter and setter for token ID.
	 */
	public function test_token_get_and_set(): void {
		$this->token->set_token( 'pm_test_klarna_123' );
		$this->assertEquals(
			'pm_test_klarna_123',
			$this->token->get_token(),
			'The token property should match the value that was set.'
		);
	}

	/**
	 * Tests for the DOB getters and setters.
	 *
	 * @param object $dob_object The DOB object to set.
	 * @param string $expected   The expected formatted DOB string.
	 * @return void
	 * @dataProvider provide_test_getters_setters
	 */
	public function test_getters_setters( object $dob_object, string $expected ): void {
		$this->token->set_dob_from_object( $dob_object );
		$this->assertSame( $expected, $this->token->get_dob() );
	}

	/**
	 * Data provider for `test_getters_setters`.
	 *
	 * @return array
	 */
	public function provide_test_getters_setters(): array {
		return [
			'February 1, 2000' => [
				(object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
				'2000-02-01',
			],
			'October 18, 1999' => [
				(object) [
					'day'   => 18,
					'month' => 10,
					'year'  => 1999,
				],
				'1999-10-18',
			],
			'empty DOB object' => [
				(object) [],
				'',
			],
		];
	}

	/**
	 * Tests for `is_equal_payment_method()`.
	 *
	 * @param string      $token_id       The token ID to set.
	 * @param object|null $dob            The DOB object to set on the token (null means no DOB).
	 * @param object      $payment_method The payment method to compare against.
	 * @param bool        $expected       Whether the payment methods should be considered equal.
	 * @param string      $message        The assertion failure message.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_id, ?object $dob, object $payment_method, bool $expected, string $message ): void {
		$this->token->set_token( $token_id );
		if ( null !== $dob ) {
			$this->token->set_dob_from_object( $dob );
		} else {
			$this->token->set_dob( '' );
		}
		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method ), $message );
	}

	/**
	 * Data provider for `test_is_equal_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		$matching_pm = (object) [
			'id'     => 'pm_123',
			'type'   => WC_Stripe_Payment_Methods::KLARNA,
			'klarna' => (object) [
				'dob' => (object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
			],
		];

		$different_dob_pm = (object) [
			'id'     => 'pm_123',
			'type'   => WC_Stripe_Payment_Methods::KLARNA,
			'klarna' => (object) [
				'dob' => (object) [
					'day'   => 2,
					'month' => 2,
					'year'  => 2000,
				],
			],
		];

		return [
			'equal payment method'                                  => [
				'pm_123',
				(object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
				$matching_pm,
				true,
				'is_equal_payment_method() should return true when type and DOB match.',
			],
			'different DOB'                                         => [
				'pm_123',
				(object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
				$different_dob_pm,
				false,
				'is_equal_payment_method() should return false when DOB does not match.',
			],
			'mismatched type'                                       => [
				'pm_123',
				(object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
				(object) [
					'id'     => 'pm_123',
					'type'   => 'card',
					'klarna' => (object) [
						'dob' => (object) [
							'day'   => 1,
							'month' => 2,
							'year'  => 2000,
						],
					],
				],
				false,
				'is_equal_payment_method() should return false when payment method type is not klarna.',
			],
			'both have no DOB'                                      => [
				'pm_123',
				null,
				(object) [
					'id'     => 'pm_123',
					'type'   => WC_Stripe_Payment_Methods::KLARNA,
					'klarna' => (object) [],
				],
				true,
				'is_equal_payment_method() should return true when both token and payment method have no DOB.',
			],
			'token has no DOB but payment method has DOB'           => [
				'pm_123',
				null,
				$matching_pm,
				false,
				'is_equal_payment_method() should return false when token has no DOB but payment method has DOB.',
			],
			'token has DOB but payment method has no klarna object' => [
				'pm_123',
				(object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
				(object) [
					'id'   => 'pm_123',
					'type' => WC_Stripe_Payment_Methods::KLARNA,
				],
				false,
				'is_equal_payment_method() should return false when payment method has no klarna object.',
			],
			'token has DOB but payment method klarna has empty DOB' => [
				'pm_123',
				(object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
				(object) [
					'id'     => 'pm_123',
					'type'   => WC_Stripe_Payment_Methods::KLARNA,
					'klarna' => (object) [
						'dob' => (object) [],
					],
				],
				false,
				'is_equal_payment_method() should return false when payment method klarna has an empty DOB object.',
			],
		];
	}
}
