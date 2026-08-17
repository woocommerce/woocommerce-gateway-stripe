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

	/**
	 * A SetupIntent that Stripe hasn't settled yet outlives the one-day awaiting-action window
	 * (bank microdeposits take days), so the order must not be auto-cancelled while it can still
	 * succeed — otherwise the later setup_intent.succeeded webhook finds a cancelled order.
	 *
	 * @param string $status   SetupIntent status returned for the order.
	 * @param bool   $expected Whether the order should still be cancelled.
	 * @dataProvider provide_setup_intent_statuses_for_cancellation
	 */
	public function test_does_not_cancel_orders_with_unsettled_setup_intent( string $status, bool $expected ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		// Well outside the one-day awaiting-action grace, so only the SetupIntent guard can save it.
		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-5 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'seti_mock',
					'object' => 'setup_intent',
					'status' => $status,
				]
			);

		$this->assertSame(
			$expected,
			$this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ),
			"SetupIntent status {$status} produced the wrong cancellation decision."
		);
	}

	/**
	 * Data provider for {@see test_does_not_cancel_orders_with_unsettled_setup_intent()}.
	 *
	 * @return array
	 */
	public function provide_setup_intent_statuses_for_cancellation(): array {
		return [
			'requires_action awaits microdeposits' => [ 'requires_action', false ],
			'requires_confirmation still settling' => [ 'requires_confirmation', false ],
			'processing still settling'            => [ 'processing', false ],
			'canceled is done'                     => [ 'canceled', true ],
			'requires_payment_method is done'      => [ 'requires_payment_method', true ],
		];
	}

	/**
	 * The guard is specific to SetupIntents: a PaymentIntent in the same status keeps the
	 * pre-existing one-day cancellation behaviour.
	 */
	public function test_payment_intent_awaiting_action_is_still_cancelled_after_a_day() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-5 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'pi_mock',
					'object' => 'payment_intent',
					'status' => 'requires_action',
				]
			);

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
	 * An order whose PaymentIntent Stripe reports as paid must be settled, not cancelled as unpaid.
	 *
	 * @param string $intent_status PaymentIntent status reported by Stripe.
	 * @dataProvider provide_paid_intent_statuses
	 */
	public function test_settles_order_with_paid_intent_instead_of_cancelling( $intent_status ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( 'pending' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$charge = (object) [
			'id'       => 'ch_mock',
			'captured' => true,
			'status'   => 'succeeded',
		];
		$intent = (object) [
			'id'     => 'pi_mock',
			'object' => 'payment_intent',
			'status' => $intent_status,
		];

		$order_handler = $this->createPartialMock(
			WC_Stripe_Order_Handler::class,
			[ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ]
		);
		$order_handler->method( 'get_intent_from_order' )->willReturn( $intent );
		$order_handler->method( 'get_latest_charge_from_intent' )->with( $intent )->willReturn( $charge );
		$order_handler->expects( $this->once() )
			->method( 'process_response' )
			->with( $charge, $this->callback( fn( $passed ) => $passed->get_id() === $order->get_id() ) );

		$this->assertFalse( $order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		$notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 5,
			]
		);
		$this->assertNotEmpty(
			array_filter(
				$notes,
				function ( $note ) {
					return false !== strpos( $note->content, 'instead of being auto-cancelled' );
				}
			),
			'A note should explain why the cancellation was blocked.'
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_paid_intent_statuses() {
		return [
			'captured payment'         => [ WC_Stripe_Intent_Status::SUCCEEDED ],
			'uncaptured authorization' => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE ],
		];
	}

	/**
	 * When another process holds the payment lock, block the cancellation but settle nothing here.
	 */
	public function test_blocks_cancellation_without_settling_when_payment_locked() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( 'pending' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$intent = (object) [
			'id'     => 'pi_mock_locked',
			'object' => 'payment_intent',
			'status' => WC_Stripe_Intent_Status::SUCCEEDED,
		];

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);
		$order_helper->method( 'lock_order_payment' )->willReturn( true );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$order_handler = $this->createPartialMock(
			WC_Stripe_Order_Handler::class,
			[ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ]
		);
		$order_handler->method( 'get_intent_from_order' )->willReturn( $intent );
		$order_handler->expects( $this->never() )->method( 'process_response' );

		try {
			$this->assertFalse( $order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
		} finally {
			WC_Stripe_Order_Helper::set_instance( null );
		}
	}

	/**
	 * An unpaid intent must not block the existing cancellation flow.
	 */
	public function test_cancels_order_whose_intent_is_not_paid() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( 'pending' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$intent = (object) [
			'id'     => 'pi_mock_unpaid',
			'object' => 'payment_intent',
			'status' => WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD,
		];

		$order_handler = $this->createPartialMock(
			WC_Stripe_Order_Handler::class,
			[ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ]
		);
		$order_handler->method( 'get_intent_from_order' )->willReturn( $intent );
		$order_handler->expects( $this->never() )->method( 'process_response' );

		$this->assertTrue( $order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
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
