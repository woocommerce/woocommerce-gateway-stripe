<?php

namespace WooCommerce\Stripe\Tests\PaymentTokens;

use stdClass;
use WC_Payment_Token_Klarna;
use WC_Stripe_Payment_Methods;
use WP_UnitTestCase;

/**
 * Class WC_Payment_Token_Klarna tests.
 */
class WC_Payment_Token_Klarna_Test extends WP_UnitTestCase {

	/**
	 * WC_Payment_Token_Klarna instance.
	 *
	 * @var WC_Payment_Token_Klarna
	 */
	protected $token;

	protected function setUp(): void {
		$this->token = new WC_Payment_Token_Klarna();
	}

	/**
	 * Tests for `get_display_name()`.
	 *
	 * @return void
	 */
	public function test_get_display_name() {
		// No DOB.
		$this->token->set_dob( '' );
		$this->assertSame( 'Klarna', $this->token->get_display_name() );

		// With DOB.
		$this->token->set_dob( '2000-02-01' );
		$this->assertSame( 'Klarna', $this->token->get_display_name() );
	}

	/**
	 * Tests for the DOB getters and setters.
	 *
	 * @return void
	 */
	public function test_getters_setters() {
		$this->token->set_dob_from_object(
			(object) [
				'day'   => 1,
				'month' => 2,
				'year'  => 2000,
			]
		);
		$this->assertSame( '2000-02-01', $this->token->get_dob() );

		$this->token->set_dob_from_object(
			(object) [
				'day'   => 18,
				'month' => 10,
				'year'  => 1999,
			]
		);
		$this->assertSame( '1999-10-18', $this->token->get_dob() );
	}

	/**
	 * Tests for `is_equal_payment_method()`.
	 *
	 * @return void
	 */
	public function test_is_equal_payment_method() {
		$payment_method = (object) [
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

		// Equal
		$this->token->set_token( 'pm_123' );
		$this->token->set_dob_from_object( $payment_method->klarna->dob );
		$this->assertTrue( $this->token->is_equal_payment_method( $payment_method ) );

		// Different DOB.
		$this->token->set_type( WC_Stripe_Payment_Methods::KLARNA );
		$payment_method->id = 'pm_123';
		$payment_method->klarna->dob->day = 2;
		$this->assertFalse( $this->token->is_equal_payment_method( $payment_method ) );
	}
}
