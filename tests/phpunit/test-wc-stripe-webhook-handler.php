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
}
