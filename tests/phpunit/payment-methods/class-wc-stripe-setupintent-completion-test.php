<?php
/**
 * Tests that a zero-total order only settles on an exactly-succeeded SetupIntent.
 *
 * @package WooCommerce_Stripe/Tests
 */

/**
 * Covers the exact-success gate in
 * WC_Stripe_UPE_Payment_Gateway::process_order_for_confirmed_intent() for the
 * zero-total (SetupIntent) branch.
 */
class WC_Stripe_SetupIntent_Completion_Test extends WP_UnitTestCase {

	/**
	 * Builds a gateway with the two API-touching methods stubbed and a
	 * zero-total pending order bound to a matching stored SetupIntent id.
	 *
	 * @param string $status The SetupIntent status to return.
	 * @return array{gateway: object, order_id: int}
	 */
	private function make_gateway_and_zero_total_order( string $status ): array {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->onlyMethods( [ 'is_payment_needed', 'stripe_request' ] )
			->getMock();

		// Zero total => SetupIntent branch.
		$gateway->method( 'is_payment_needed' )->willReturn( false );

		$order = WC_Helper_Order::create_order();
		$order->set_total( 0 );
		$order->set_status( 'pending' );
		$order->update_meta_data( '_stripe_upe_payment_type', 'card' );
		$order->save();
		$order_id = $order->get_id();

		$intent_id = 'seti_test_' . $order_id;
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $intent_id );
		$order->save();

		$intent = (object) [
			'id'                   => $intent_id,
			'object'               => 'setup_intent',
			'status'               => $status,
			'payment_method_types' => [ 'card' ],
			'last_setup_error'     => null,
			'payment_method'       => (object) [
				'id'   => 'pm_test',
				'type' => 'card',
				'card' => (object) [
					'brand' => 'visa',
					'last4' => '4242',
				],
			],
		];
		$gateway->method( 'stripe_request' )->willReturn( $intent );

		return [
			'gateway'  => $gateway,
			'order_id' => $order_id,
		];
	}

	/**
	 * Every non-succeeded SetupIntent status must leave a zero-total order
	 * unpaid and pending — no completion, no paid date.
	 *
	 * @dataProvider provide_non_success_statuses
	 *
	 * @param string $status A non-succeeded SetupIntent status.
	 * @return void
	 */
	public function test_non_success_setup_intent_does_not_settle_order( string $status ) {
		$context = $this->make_gateway_and_zero_total_order( $status );

		$context['gateway']->process_order_for_confirmed_intent(
			wc_get_order( $context['order_id'] ),
			'seti_test_' . $context['order_id'],
			false
		);

		$order = wc_get_order( $context['order_id'] );
		$this->assertSame( 'pending', $order->get_status(), "Status {$status} must not settle the order." );
		$this->assertNull( $order->get_date_paid(), "Status {$status} must not set a paid date." );
	}

	/**
	 * Data provider: non-succeeded SetupIntent statuses (incl. missing/unknown).
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_non_success_statuses(): array {
		return [
			'requires_action'         => [ 'requires_action' ],
			'requires_confirmation'   => [ 'requires_confirmation' ],
			'processing'              => [ 'processing' ],
			'requires_payment_method' => [ 'requires_payment_method' ],
			'canceled'                => [ 'canceled' ],
			'unknown'                 => [ 'some_unexpected_status' ],
			'empty'                   => [ '' ],
		];
	}

	/**
	 * A SetupIntent that only succeeds asynchronously is finalized by the webhook, so that path
	 * must run the gateway's confirmed-intent handling rather than just completing the order —
	 * otherwise the token, mandate and payment-method title are never persisted.
	 *
	 * @return void
	 */
	public function test_webhook_delegates_succeeded_setup_intent_to_the_gateway() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 0 );
		$order->set_status( 'pending' );
		$order->save();

		$intent_id = 'seti_webhook_' . $order->get_id();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_setup_intent_id( $order, $intent_id );
		$order->save_meta_data();

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		// The whole point: the webhook must route through the gateway, not call payment_complete() itself.
		$gateway->expects( $this->once() )
			->method( 'process_order_for_confirmed_intent' )
			->with(
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $passed_order->get_id() === $order->get_id();
					}
				),
				$intent_id,
				true
			);

		$reflection = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$reflection->setAccessible( true );
		$original = $reflection->getValue( WC_Stripe::get_instance() );
		$reflection->setValue( WC_Stripe::get_instance(), $gateway );

		try {
			$handler      = new WC_Stripe_Webhook_Handler();
			$notification = (object) [
				'type' => 'setup_intent.succeeded',
				'data' => (object) [
					'object' => (object) [
						'id'     => $intent_id,
						'object' => 'setup_intent',
						'status' => 'succeeded',
					],
				],
			];

			$handler->process_setup_intent( $notification );
		} finally {
			$reflection->setValue( WC_Stripe::get_instance(), $original );
		}
	}

	/**
	 * The webhook path reaches is_payment_needed() with no cart loaded, so it
	 * must resolve from the order alone: null-cart safe and driven by the
	 * order total.
	 *
	 * @dataProvider provide_cartless_orders
	 *
	 * @param float $total    The order total.
	 * @param bool  $expected Whether payment should be needed.
	 *
	 * @return void
	 */
	public function test_is_payment_needed_resolves_from_order_when_cart_is_unavailable( float $total, bool $expected ) {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->onlyMethods( [ 'stripe_request' ] )
			->getMock();

		$order = WC_Helper_Order::create_order();
		$order->set_total( $total );
		$order->save();

		$cart      = WC()->cart;
		WC()->cart = null;

		try {
			$this->assertSame( $expected, $gateway->is_payment_needed( $order->get_id() ) );
		} finally {
			WC()->cart = $cart;
		}
	}

	/**
	 * Data provider: order totals and whether payment is needed for them.
	 *
	 * @return array<string, array{0: float, 1: bool}>
	 */
	public function provide_cartless_orders(): array {
		return [
			'zero-total order (SetupIntent path)' => [ 0.0, false ],
			'order with an amount due'            => [ 50.0, true ],
		];
	}

	/**
	 * An exactly-succeeded SetupIntent still settles the zero-total order,
	 * preserving current behavior.
	 *
	 * @return void
	 */
	public function test_succeeded_setup_intent_settles_order() {
		$context = $this->make_gateway_and_zero_total_order( 'succeeded' );

		$context['gateway']->process_order_for_confirmed_intent(
			wc_get_order( $context['order_id'] ),
			'seti_test_' . $context['order_id'],
			false
		);

		$order = wc_get_order( $context['order_id'] );
		$this->assertTrue(
			$order->has_status( [ 'processing', 'completed' ] ),
			'A succeeded SetupIntent should settle the order.'
		);
		$this->assertNotNull( $order->get_date_paid(), 'A succeeded SetupIntent should set a paid date.' );
	}
}
