<?php

/**
 * Class WC_Stripe_Order_Handler tests.
 */
class WC_Stripe_Order_Handler_Test extends WP_UnitTestCase {

	/**
	 * Order handler instance.
	 *
	 * @var WC_Stripe_Order_Handler
	 */
	private $order_handler;

	public function set_up() {
		parent::set_up();

		$this->order_handler = $this->createPartialMock( WC_Stripe_Order_Handler::class, [ 'get_intent_from_order' ] );
	}

	public function test_prevent_cancelling_orders_awaiting_action() {
		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->set_payment_awaiting_action( $order );

		// Read in a fresh order object with meta like `date_modified` set.
		$order = wc_get_order( $order->get_id() );

		// Test when false is passed that the order is not cancelled.
		$this->assertFalse( $this->order_handler->prevent_cancelling_orders_awaiting_action( false, $order ) );

		// Test non-stripe payment method is cancelled.
		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		// Test a stripe order with no intent is cancelled.
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();
		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		// Test a stripe order with meta + intent is not cancelled.
		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->with( $order )
			->willReturn( (object) [ 'intent_id' => 'pm_mockintentID' ] );
		$this->assertFalse( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		// Test a stripe order with meta + intent but was modified more than a day ago is cancelled.
		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-2 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
	}

	public function test_prevent_cancelling_orders_that_have_been_paid() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		// Mimic the race outcome: payment captured (date_paid set) but status stuck at pending.
		$order->set_date_paid( time() );
		$order->set_status( 'pending' );
		$order->save();

		// Read in a fresh order object.
		$order = wc_get_order( $order->get_id() );

		// A paid order must never be cancelled as unpaid, and we shouldn't need to fetch the intent.
		$this->order_handler->expects( $this->never() )->method( 'get_intent_from_order' );

		$this->assertFalse( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
	}

	/**
	 * Surfacing a blocked paid-order cancellation is idempotent: one note and one action fire even
	 * though wc_cancel_unpaid_orders() re-runs the filter on every scheduled pass.
	 */
	public function test_surfaces_prevented_paid_order_cancellation_only_once() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_date_paid( time() );
		$order->set_status( 'pending' );
		$order->save();

		$order = wc_get_order( $order->get_id() );

		$fired = 0;
		add_action(
			'wc_stripe_paid_order_cancellation_prevented',
			function () use ( &$fired ) {
				$fired++;
			}
		);

		// First scheduled pass: the work happens here — meta flag set, action fired, note added.
		$this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order );

		$this->assertSame( 1, $fired, 'Action should fire on the first pass.' );
		$this->assertSame( 'yes', wc_get_order( $order->get_id() )->get_meta( '_stripe_paid_order_cancellation_prevented' ) );
		$this->assertCount( 1, $this->get_prevented_cancellation_notes( $order->get_id() ), 'Order note should be added on the first pass.' );

		// Second scheduled pass over the same stuck order: the meta flag short-circuits all side effects.
		$this->order_handler->prevent_cancelling_orders_awaiting_action( true, wc_get_order( $order->get_id() ) );

		$this->assertSame( 1, $fired, 'Action should not fire again on the second pass.' );
		$this->assertSame( 'yes', wc_get_order( $order->get_id() )->get_meta( '_stripe_paid_order_cancellation_prevented' ) );
		$this->assertCount( 1, $this->get_prevented_cancellation_notes( $order->get_id() ), 'Order note should not be added again on the second pass.' );
	}

	/**
	 * Returns the order notes that announce a prevented paid-order cancellation.
	 *
	 * @param int $order_id The order to read notes from.
	 * @return array The matching notes.
	 */
	private function get_prevented_cancellation_notes( $order_id ) {
		$notes = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 5,
			]
		);

		return array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->content, 'already been paid' );
			}
		);
	}
}
