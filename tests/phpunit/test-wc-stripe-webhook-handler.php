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
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods(
				[
					'handle_deferred_payment_intent_succeeded',
				]
			)
			->getMock();
	}

	/**
	 * Test process_deferred_webhook with unsupported webhook type.
	 */
	public function test_process_deferred_webhook_invalid_type() {
		$this->expectExceptionMessage( 'Unsupported webhook type: event-id' );
		$this->mock_webhook_handler->process_deferred_webhook( 'event-id', [] );
	}

	/**
	 * Test process_deferred_webhook with invalid args.
	 */
	public function test_process_deferred_webhook_invalid_args() {
		// No data
		$data = []; // No data.

		$this->expectExceptionMessage( "Missing required data: 'order_id' is invalid or not found for the deferred payment_intent.succeeded event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );

		// Invalid order_id
		$data = [
			'order_id' => 9999,
		];

		$this->expectExceptionMessage( "Missing required data: 'order_id' is invalid or not found for the deferred payment_intent.succeeded event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );

		// No payment intent
		$order = WC_Helper_Order::create_order();
		$data['order_id'] = $order->get_id();

		$this->expectExceptionMessage( "Missing required data: 'intent_id' is missing for the deferred payment_intent.succeeded event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}

	/**
	 * Test process_deferred_webhook with valid args.
	 */
	public function test_test_process_deferred_webhook() {
		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_mock_1234';
		$data      = [
			'order_id' => $order->get_id(),
			'intent_id' => $intent_id,
		];

		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'handle_deferred_payment_intent_succeeded' )
			->with( $order, $intent_id );

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}
}
