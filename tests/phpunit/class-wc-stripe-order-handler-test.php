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
	 * Tests that cancel_payment leaves orders with a missing captured flag alone.
	 * The cancelled/refunded status hooks fire on transitions that must not touch the
	 * gateway (e.g. "Refund manually"); resolving a missing flag from Stripe here could
	 * turn a captured charge into an unrequested full refund.
	 */
	public function test_cancel_payment_skips_orders_with_missing_captured_flag() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_transaction_id( 'ch_123' );
		$order->save();

		$requested_urls = [];

		$callback = function ( $preempt, $request_args, $url ) use ( &$requested_urls ) {
			$requested_urls[] = $url;
			return $preempt;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		$this->order_handler->cancel_payment( $order->get_id() );

		remove_filter( 'pre_http_request', $callback );

		// No captured-state lookup, no refund: Stripe must not be contacted at all.
		$this->assertSame( [], $requested_urls );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_stripe_charge_captured' ) );
	}

	/**
	 * Tests that cancel_payment still voids a charge explicitly recorded as uncaptured.
	 *
	 * @dataProvider provide_test_cancel_payment_voids_explicit_preauth
	 */
	public function test_cancel_payment_voids_explicit_preauth( $captured_meta, $expects_void ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', $captured_meta );
		$order->save();

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'pi_123',
					'object' => 'payment_intent',
					'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
				]
			);

		$cancel_requested = false;

		$callback = function ( $preempt, $request_args, $url ) use ( &$cancel_requested ) {
			if ( strpos( $url, 'payment_intents/pi_123/cancel' ) !== false ) {
				$cancel_requested = true;

				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'            => 'pi_123',
							'object'        => 'payment_intent',
							'status'        => 'canceled',
							'latest_charge' => 'ch_123',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
			if ( strpos( $url, 'charges/ch_123' ) !== false ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'       => 'ch_123',
							'object'   => 'charge',
							'captured' => false,
							'refunds'  => [
								'data' => [
									[
										'id'     => 're_123',
										'object' => 'refund',
										'amount' => 5000,
										'status' => 'succeeded',
									],
								],
							],
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		$this->order_handler->cancel_payment( $order->get_id() );

		remove_filter( 'pre_http_request', $callback );

		$this->assertSame( $expects_void, $cancel_requested );
	}

	/**
	 * Provider for `test_cancel_payment_voids_explicit_preauth`.
	 *
	 * @return array[]
	 */
	public function provide_test_cancel_payment_voids_explicit_preauth(): array {
		return [
			'explicit no voids the intent'   => [ 'no', true ],
			'captured charge is not touched' => [ 'yes', false ],
		];
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
