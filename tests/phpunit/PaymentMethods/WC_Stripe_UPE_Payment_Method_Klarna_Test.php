<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Gateway;
use WC_Stripe_UPE_Payment_Method_Klarna;
use WooCommerce\Stripe\Tests\Helpers\OC_Test_Helper;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Klarna.
 */
class WC_Stripe_UPE_Payment_Method_Klarna_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_retrievable_type()`.
	 *
	 * @return void
	 */
	public function test_get_retrievable_type() {
		$instance = new WC_Stripe_UPE_Payment_Method_Klarna();
		$this->assertSame( WC_Stripe_Payment_Methods::KLARNA, $instance->get_retrievable_type() );
	}

	/**
	 * Tests for `create_payment_token_for_user()`.
	 *
	 * @return void
	 */
	public function test_create_payment_token_for_user() {
		$instance       = new WC_Stripe_UPE_Payment_Method_Klarna();
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

		$token = $instance->create_payment_token_for_user( 1, $payment_method );

		$this->assertSame( 'stripe_klarna', $token->get_gateway_id() );
		$this->assertSame( 'pm_123', $token->get_token() );
		$this->assertSame( 1, $token->get_user_id() );
		$this->assertSame( '2000-02-01', $token->get_dob() );
	}
}
