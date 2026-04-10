<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Klarna.
 */
class WC_Stripe_UPE_Payment_Method_Klarna_Test extends WP_UnitTestCase {
	/**
	 * WC_Stripe_UPE_Payment_Method_Klarna instance.
	 *
	 * @var WC_Stripe_UPE_Payment_Method_Klarna
	 */
	protected $instance;

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->instance = new WC_Stripe_UPE_Payment_Method_Klarna();
	}

	/**
	 * Tests for `get_retrievable_type()`.
	 *
	 * @return void
	 */
	public function test_get_retrievable_type() {
		$this->assertSame( WC_Stripe_Payment_Methods::KLARNA, $this->instance->get_retrievable_type() );
	}

	/**
	 * Tests for `create_payment_token_for_user()`.
	 *
	 * @return void
	 */
	public function test_create_payment_token_for_user() {
		$payment_method = (object) [
			'id'     => 'pm_123',
			'klarna' => (object) [
				'dob' => (object) [
					'day'   => 1,
					'month' => 2,
					'year'  => 2000,
				],
			],
		];

		$token = $this->instance->create_payment_token_for_user( 1, $payment_method );

		$this->assertSame( 'stripe_klarna', $token->get_gateway_id() );
		$this->assertSame( 'pm_123', $token->get_token() );
		$this->assertSame( 1, $token->get_user_id() );
		$this->assertSame( '2000-02-01', $token->get_dob() );
	}
}
