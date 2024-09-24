<?php
/**
 * Class WC_Stripe_Payment_Intent_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Payment_Intent
 */

/**
 * Class WC_Stripe_Payment_Intent tests.
 */
class WC_Stripe_Payment_Intent_Test extends WP_UnitTestCase {
	/**
	 * Mock card payment intent template.
	 */
	const MOCK_PAYMENT_INTENT = [
		'id'      => 'pi_mock',
		'object'  => 'payment_intent',
		'status'  => 'succeeded',
		'charges' => [
			'total_count' => 1,
			'data'        => [
				[
					'id'                     => 'ch_mock',
					'captured'               => true,
					'payment_method_details' => [],
					'status'                 => 'succeeded',
				],
			],
		],
	];

	/**
	 * Test for `from_response` method.
	 *
	 * @return void
	 */
	public function test_from_response() {
		$payment_intent = WC_Stripe_Payment_Intent::from_response( (object) self::MOCK_PAYMENT_INTENT );

		$this->assertInstanceOf( WC_Stripe_Payment_Intent::class, $payment_intent );
	}

	/**
	 * Test for `to_object` method.
	 *
	 * @return void
	 */
	public function test_to_object() {
		$payment_intent        = WC_Stripe_Payment_Intent::from_response( (object) self::MOCK_PAYMENT_INTENT );
		$payment_intent_object = $payment_intent->to_object();

		$this->assertIsObject( $payment_intent_object );
		$this->assertSame( 'pi_mock', $payment_intent_object->id );
		$this->assertSame( 'succeeded', $payment_intent_object->status );
		$this->assertSame( 1, $payment_intent_object->charges->total_count );
		$this->assertSame( 'ch_mock', $payment_intent_object->charges->data[0]->id );
		$this->assertTrue( $payment_intent_object->charges->data[0]->captured );
		$this->assertSame( 'succeeded', $payment_intent_object->charges->data[0]->status );
	}

	/**
	 * Test for `is_requires_payment_method` method.
	 *
	 * @return void
	 */
	public function requires_confirmation_or_action() {
		$payment_intent = WC_Stripe_Payment_Intent::from_response( (object) self::MOCK_PAYMENT_INTENT );

		$this->assertFalse( $payment_intent->requires_confirmation_or_action() );
	}

	/**
	 * Test for `is_requires_payment_method` method.
	 *
	 * @return void
	 */
	public function test_contains_wallet_or_voucher_method() {
		$payment_intent = WC_Stripe_Payment_Intent::from_response( (object) self::MOCK_PAYMENT_INTENT );

		$this->assertFalse( $payment_intent->contains_wallet_or_voucher_method() );
	}

	/**
	 * Test for `is_requires_payment_method` method.
	 *
	 * @return void
	 */
	public function test_contains_redirect_next_action() {
		$payment_intent = WC_Stripe_Payment_Intent::from_response( (object) self::MOCK_PAYMENT_INTENT );

		$this->assertFalse( $payment_intent->contains_redirect_next_action() );
	}

	/**
	 * Test for `is_requires_payment_method` method.
	 *
	 * @return void
	 */
	public function test_is_successful() {
		$payment_intent = WC_Stripe_Payment_Intent::from_response( (object) self::MOCK_PAYMENT_INTENT );

		$this->assertTrue( $payment_intent->is_successful() );
	}

	/**
	 * Test for `is_requires_payment_method` method.
	 *
	 * @return void
	 * @throws WC_Stripe_Exception If the charge object cannot be created.
	 */
	public function test_get_latest_charge() {
		$mock_payment_intent                     = self::MOCK_PAYMENT_INTENT;
		$mock_payment_intent['charges']          = (object) $mock_payment_intent['charges'];
		$mock_payment_intent['charges']->data[0] = (object) $mock_payment_intent['charges']->data[0];
		$payment_intent                          = WC_Stripe_Payment_Intent::from_response( (object) $mock_payment_intent );
		$latest_charge                           = $payment_intent->get_latest_charge();

		$this->assertIsObject( $latest_charge );
		$this->assertSame( 'ch_mock', $latest_charge->id );
		$this->assertTrue( $latest_charge->captured );
		$this->assertSame( 'succeeded', $latest_charge->status );
	}
}
