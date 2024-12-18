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
		WC_Stripe_Helper::set_payment_awaiting_action( $order );

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

	/**
	 * Test for disable_edit_for_uncaptured_orders().
	 */
	public function test_disable_edit_for_uncaptured_orders() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'bacs' );
		$order->save();

		// Test when payment method is not stripe.
		$this->assertTrue( $this->order_handler->disable_edit_for_uncaptured_orders( true, $order ) );
		$this->assertFalse( $this->order_handler->disable_edit_for_uncaptured_orders( false, $order ) );

		$order->set_payment_method( 'stripe' );
		$order->save();

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturnOnConsecutiveCalls(
				(object) [
					'intent_id' => 'pi_mock1',
					'status'    => 'succeeded',
				],
				(object) [
					'intent_id' => 'pi_mock2',
					'status'    => 'requires_capture',
				]
			);

		// Test when intent is succeeded.
		$this->assertTrue( $this->order_handler->disable_edit_for_uncaptured_orders( true, $order ) );

		// Test when intent is requires_capture.
		$this->assertFalse( $this->order_handler->disable_edit_for_uncaptured_orders( true, $order ) );
	}
}
