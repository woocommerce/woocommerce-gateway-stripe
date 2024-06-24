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
	private $webhook_handler;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->webhook_handler = new WC_Stripe_Webhook_Handler();
	}

	/**
	 * Test process_deferred_webhook with unsupported webhook type.
	 */
	public function test_process_deferred_webhook_invalid_type() {
		$this->expectExceptionMessage( 'Unsupported webhook type: event-id' );
		$this->webhook_handler->process_deferred_webhook( 'event-id', [] );
	}

	/**
	 * Test process_deferred_webhook with invalid args.
	 */
	public function test_process_deferred_webhook_invalid_args() {
		// No data
		$data = []; // No data.

		$this->expectExceptionMessage( "Missing required data: 'order_id' is invalid or not found for the deferred payment_intent.succeeded event." );
		$this->webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );

		// Invalid order_id
		$data = [
			'order_id' => 9999,
		];

		$this->expectExceptionMessage( "Missing required data: 'order_id' is invalid or not found for the deferred payment_intent.succeeded event." );
		$this->webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );

		// No payment intent
		$order = WC_Helper_Order::create_order();
		$data['order_id'] = $order->get_id();

		$this->expectExceptionMessage( "Missing required data: 'intent_id' is missing for the deferred payment_intent.succeeded event." );
		$this->webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data );
	}
}
