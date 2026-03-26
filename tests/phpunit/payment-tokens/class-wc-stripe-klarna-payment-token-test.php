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

	protected function setUp(): void {
		$this->token = new WC_Stripe_Klarna_Payment_Token();
	}

	/**
	 * Tests for `get_display_name()`.
	 *
	 * @param string $dob The DOB to set (empty string means no DOB).
	 * @return void
	 * @dataProvider provide_test_get_display_name
	 */
	public function test_get_display_name( string $dob ) {
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
	 * Tests for the DOB getters and setters.
	 *
	 * @param object $dob_object The DOB object to set.
	 * @param string $expected   The expected formatted DOB string.
	 * @return void
	 * @dataProvider provide_test_getters_setters
	 */
	public function test_getters_setters( object $dob_object, string $expected ) {
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
			'February 1, 2000'  => [
				(object) [
					'day' => 1,
					'month' => 2,
					'year' => 2000,
				],
				'2000-02-01',
			],
			'October 18, 1999'  => [
				(object) [
					'day' => 18,
					'month' => 10,
					'year' => 1999,
				],
				'1999-10-18',
			],
		];
	}

	/**
	 * Tests for `is_equal_payment_method()`.
	 *
	 * @param string $token_id        The token ID to set.
	 * @param object $dob             The DOB object to set on the token.
	 * @param object $payment_method  The payment method to compare against.
	 * @param bool   $expected        Whether the payment methods should be considered equal.
	 * @return void
	 * @dataProvider provide_test_is_equal_payment_method
	 */
	public function test_is_equal_payment_method( string $token_id, object $dob, object $payment_method, bool $expected ) {
		$this->token->set_token( $token_id );
		$this->token->set_dob_from_object( $dob );
		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method ) );
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
					'day' => 1,
					'month' => 2,
					'year' => 2000,
				],
			],
		];

		$different_dob_pm = (object) [
			'id'     => 'pm_123',
			'type'   => WC_Stripe_Payment_Methods::KLARNA,
			'klarna' => (object) [
				'dob' => (object) [
					'day' => 2,
					'month' => 2,
					'year' => 2000,
				],
			],
		];

		return [
			'equal payment method'   => [
				'pm_123',
				(object) [
					'day' => 1,
					'month' => 2,
					'year' => 2000,
				],
				$matching_pm,
				true,
			],
			'different DOB'          => [
				'pm_123',
				(object) [
					'day' => 1,
					'month' => 2,
					'year' => 2000,
				],
				$different_dob_pm,
				false,
			],
		];
	}
}
