<?php
/**
 * These tests make assertions against class WC_Stripe_Webhook_State.
 *
 * @package WooCommerce_Stripe/Tests/Webhook_State
 */

/**
 * WC_Stripe_Webhook_State_Test class.
 */
class WC_Stripe_Webhook_Handler_Test extends WP_UnitTestCase {

	/**
	 * The webhook handler instance for testing.
	 *
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $mock_webhook_handler;

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
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->mock_webhook_handler();
	}

	/**
	 * Mock the webhook handler.
	 */
	private function mock_webhook_handler( $exclude_methods = [] ) {
		$methods = [
			'handle_deferred_payment_intent_succeeded',
			'get_intent_from_order',
			'get_latest_charge_from_intent',
			'process_response',
		];

		$methods = array_diff( $methods, $exclude_methods );

		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( $methods )
			->getMock();
	}

	/**
	 * Test process_deferred_webhook with unsupported webhook type.
	 */
	public function test_process_deferred_webhook_invalid_type() {
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'handle_deferred_payment_intent_succeeded' );

		$this->expectExceptionMessage( 'Unsupported webhook type: event-id' );
		$this->mock_webhook_handler->process_deferred_webhook( 'event-id', [] );
	}

	/**
	 * Test process_deferred_webhook with invalid args.
	 */
	public function test_process_deferred_webhook_invalid_args() {
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'handle_deferred_payment_intent_succeeded' );

		// No data.
		$data = [];

		$this->expectExceptionMessage( "Missing required data. 'order_id' is invalid or not found for the deferred 'payment_intent.succeeded' event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );

		// Invalid order_id.
		$data = [
			'order_id' => 9999,
		];

		$this->expectExceptionMessage( "Missing required data. 'order_id' is invalid or not found for the deferred 'payment_intent.succeeded' event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );

		// No payment intent.
		$order            = WC_Helper_Order::create_order();
		$data['order_id'] = $order->get_id();

		$this->expectExceptionMessage( "Missing required data. 'intent_id' is missing for the deferred 'payment_intent.succeeded' event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}

	/**
	 * Test process_deferred_webhook with valid args.
	 */
	public function test_process_deferred_webhook() {
		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_mock_1234';
		$data      = [
			'order_id'  => $order->get_id(),
			'intent_id' => $intent_id,
		];

		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'handle_deferred_payment_intent_succeeded' )
			->with(
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $passed_order instanceof WC_Order && $order->get_id() === $passed_order->get_id();
					}
				),
				$this->equalTo( $intent_id ),
			);

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}

	/**
	 * Test deferred webhook where the intent is no longer stored on the order.
	 */
	public function test_mismatch_intent_id_process_deferred_webhook() {
		$order = WC_Helper_Order::create_order();
		$data  = [
			'order_id'  => $order->get_id(),
			'intent_id' => 'pi_wrong_id',
		];

		$this->mock_webhook_handler( [ 'handle_deferred_payment_intent_succeeded' ] );

		// Mock the get intent from order to return the mock intent.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->with(
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $passed_order instanceof WC_Order && $order->get_id() === $passed_order->get_id();
					}
				)
			)->willReturn( (object) self::MOCK_PAYMENT_INTENT );

		// Expect the get latest charge from intent to be called.
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'get_latest_charge_from_intent' );

		// Expect the process response to be called with the charge and order.
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'process_response' );

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}

	/**
	 * Test successful deferred webhook.
	 */
	public function test_process_of_successful_payment_intent_deferred_webhook() {
		$order = WC_Helper_Order::create_order();
		$data  = [
			'order_id'  => $order->get_id(),
			'intent_id' => self::MOCK_PAYMENT_INTENT['id'],
		];

		$this->mock_webhook_handler( [ 'handle_deferred_payment_intent_succeeded' ] );

		// Mock the get intent from order to return the mock intent.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT );

		// Expect the get latest charge from intent to be called.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		// Expect the process response to be called with the charge and order.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'process_response' )
			->with(
				self::MOCK_PAYMENT_INTENT['charges']['data'][0],
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $passed_order instanceof WC_Order && $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}

	/**
	 * Test for `process_webhook_charge_failed`.
	 *
	 * @param WC_Order|bool $order           The order.
	 * @param array         $event           The event type.
	 * @param string        $expected_status The expected order status.
	 * @param string        $expected_note   The expected order note.
	 * @return void
	 * @dataProvider provide_test_process_webhook_charge_failed
	 */
	public function test_process_webhook_charge_failed( $order, $event, $expected_status, $expected_note ) {
		$notification = (object) [
			'type' => $event,
			'data' => (object) [
				'object' => (object) [
					'id' => 1234,
				],
			],
		];
		if ( $order instanceof WC_Order ) {
			$charge_id                      = $order->get_transaction_id();
			$notification->data->object->id = $charge_id;
		}

		$this->mock_webhook_handler->process_webhook_charge_failed( $notification );

		if ( $order instanceof WC_Order ) {
			$order = wc_get_order( $order->get_id() );
			$note  = wc_get_order_notes(
				[
					'order_id' => $order->get_id(),
					'limit'    => 1,
				]
			)[0];

			$this->assertEquals( $expected_status, $order->get_status() );
			$this->assertSame( $expected_note, $note->content );
		} else {
			$this->assertFalse( $order );
		}
	}

	/**
	 * Provider for `test_process_webhook_charge_failed`.
	 *
	 * @return array
	 * @throws WC_Data_Exception If the transaction ID cannot be set.
	 */
	public function provide_test_process_webhook_charge_failed() {
		$order_on_hold_charge_id = 1;
		$order_on_hold           = WC_Helper_Order::create_order();
		$order_on_hold->set_status( 'on-hold' );
		$order_on_hold->set_transaction_id( $order_on_hold_charge_id );
		$order_on_hold->save();

		$order_on_hold_status_final_charge_id = 2;
		$order_on_hold_status_final           = WC_Helper_Order::create_order();
		$order_on_hold_status_final->set_status( 'on-hold' );
		$order_on_hold_status_final->set_transaction_id( $order_on_hold_status_final_charge_id );
		$order_on_hold_status_final->update_meta_data( '_stripe_status_final', true );
		$order_on_hold_status_final->save();

		$order_failed_charge_id = 3;
		$order_failed           = WC_Helper_Order::create_order();
		$order_failed->set_status( 'failed' );
		$order_failed->set_transaction_id( $order_failed_charge_id );
		$order_failed->save();

		return [
			'order not found'      => [
				'order'           => false,
				'event'           => 'charge.failed',
				'expected status' => 'on-hold',
				'expected note'   => '',
			],
			'order already failed' => [
				'order'           => $order_failed,
				'event'           => 'charge.failed',
				'expected status' => 'failed',
				'expected note'   => 'Order status changed from Pending payment to Failed.',
			],
			'charge failed event, order already with the final status' => [
				'order'           => $order_on_hold_status_final,
				'event'           => 'charge.failed',
				'expected status' => 'on-hold',
				'expected note'   => 'This payment failed to clear.',
			],
			'charge failed event'  => [
				'order'           => $order_on_hold,
				'event'           => 'charge.failed',
				'expected status' => 'failed',
				'expected note'   => 'This payment failed to clear. Order status changed from On hold to Failed.',
			],
			'charge expired event' => [
				'order'           => $order_on_hold,
				'event'           => 'charge.expired',
				'expected status' => 'failed',
				'expected note'   => 'This payment has expired. Order status changed from On hold to Failed.',
			],
		];
	}
}
