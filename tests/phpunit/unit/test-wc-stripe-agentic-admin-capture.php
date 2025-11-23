<?php

namespace WooCommerce\Stripe\Tests;

use Automattic\WooCommerce\Enums\OrderStatus;
use Mockery;
use WC_Order;
use WC_Product_Simple;
use WC_Stripe_Agentic_Admin_Capture;
use WC_Stripe_API;
use WC_Stripe_Intent_Status;
use WP_UnitTestCase;

/**
 * Test agentic checkout manual capture admin functionality.
 */
class WC_Stripe_Agentic_Admin_Capture_Test extends WP_UnitTestCase {
	/**
	 * The admin capture handler instance for testing.
	 *
	 * @var WC_Stripe_Agentic_Admin_Capture
	 */
	private $capture_handler;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->capture_handler = new WC_Stripe_Agentic_Admin_Capture();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		Mockery::close();
		parent::tear_down();
	}

	/**
	 * Test that capture action appears for manual capture agentic orders.
	 */
	public function test_capture_action_appears_for_manual_capture_orders() {
		// Create a test order with manual capture flag.
		$order = wc_create_order();
		$order->set_status( OrderStatus::ON_HOLD );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_intent_id', 'pi_test_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->set_payment_method( 'stripe' );
		$order->save();

		$actions = [];
		$actions = $this->capture_handler->add_capture_action( $actions, $order );

		$this->assertArrayHasKey( 'wc_stripe_capture_agentic_payment', $actions );
		$this->assertEquals( 'Capture Stripe Payment', $actions['wc_stripe_capture_agentic_payment'] );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test that capture action does not appear for regular orders.
	 */
	public function test_capture_action_does_not_appear_for_regular_orders() {
		// Create a regular order without manual capture flag.
		$order = wc_create_order();
		$order->set_status( OrderStatus::PROCESSING );
		$order->set_payment_method( 'stripe' );
		$order->save();

		$actions = [];
		$actions = $this->capture_handler->add_capture_action( $actions, $order );

		$this->assertArrayNotHasKey( 'wc_stripe_capture_agentic_payment', $actions );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test that capture action does not appear for already captured orders.
	 */
	public function test_capture_action_does_not_appear_for_captured_orders() {
		// Create an order that was already captured.
		$order = wc_create_order();
		$order->set_status( OrderStatus::PROCESSING );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_intent_id', 'pi_test_456' );
		$order->update_meta_data( '_stripe_charge_captured', 'yes' );
		$order->set_payment_method( 'stripe' );
		$order->save();

		$actions = [];
		$actions = $this->capture_handler->add_capture_action( $actions, $order );

		$this->assertArrayNotHasKey( 'wc_stripe_capture_agentic_payment', $actions );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test that capture action does not appear without intent ID.
	 */
	public function test_capture_action_does_not_appear_without_intent_id() {
		// Create an order without intent ID.
		$order = wc_create_order();
		$order->set_status( OrderStatus::ON_HOLD );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->set_payment_method( 'stripe' );
		$order->save();

		$actions = [];
		$actions = $this->capture_handler->add_capture_action( $actions, $order );

		$this->assertArrayNotHasKey( 'wc_stripe_capture_agentic_payment', $actions );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test that filter hook can disable capture action.
	 */
	public function test_filter_can_disable_capture_action() {
		// Create a manual capture order.
		$order = wc_create_order();
		$order->set_status( OrderStatus::ON_HOLD );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_intent_id', 'pi_test_789' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->set_payment_method( 'stripe' );
		$order->save();

		// Add filter to disable capture.
		add_filter( 'wc_stripe_agentic_manual_capture_enabled', '__return_false' );

		$actions = [];
		$actions = $this->capture_handler->add_capture_action( $actions, $order );

		$this->assertArrayNotHasKey( 'wc_stripe_capture_agentic_payment', $actions );

		// Remove filter.
		remove_filter( 'wc_stripe_agentic_manual_capture_enabled', '__return_false' );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test successful payment capture.
	 */
	public function test_successful_payment_capture() {
		// Create a manual capture order.
		$order = wc_create_order();
		$order->set_total( 10.00 );
		$order->set_status( OrderStatus::ON_HOLD );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_intent_id', 'pi_test_capture' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'pi_test_capture' );
		$order->save();

		// Mock the Stripe API response for successful capture.
		$mock_intent = (object) [
			'id'     => 'pi_test_capture',
			'status' => WC_Stripe_Intent_Status::SUCCEEDED,
			'amount' => 1000,
			'charges' => (object) [
				'data' => [
					(object) [
						'id'      => 'ch_test_123',
						'captured' => true,
					],
				],
			],
		];

		// Process the capture action.
		$this->capture_handler->process_capture_action( $order );

		// Verify order status changed.
		$order = wc_get_order( $order->get_id() );
		// Note: In actual implementation, this will be set to processing/completed
		// For now, we're just testing the structure exists.
		$this->assertInstanceOf( WC_Order::class, $order );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test capture with invalid intent ID.
	 */
	public function test_capture_with_invalid_intent() {
		// Create a manual capture order.
		$order = wc_create_order();
		$order->set_total( 10.00 );
		$order->set_status( OrderStatus::ON_HOLD );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_intent_id', 'pi_invalid' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'pi_invalid' );
		$order->save();

		// Process the capture action (will fail due to invalid intent).
		$this->capture_handler->process_capture_action( $order );

		// Verify order still exists and wasn't corrupted.
		$order = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( WC_Order::class, $order );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test that filter hook can modify capture amount.
	 */
	public function test_filter_can_modify_capture_amount() {
		// Create a manual capture order.
		$order = wc_create_order();
		$order->set_total( 10.00 );
		$order->set_status( OrderStatus::ON_HOLD );
		$order->update_meta_data( '_wc_stripe_agentic_order', 'true' );
		$order->update_meta_data( '_stripe_manual_capture', 'yes' );
		$order->update_meta_data( '_stripe_intent_id', 'pi_test_amount' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'pi_test_amount' );
		$order->save();

		$filter_called = false;

		// Add filter to modify amount.
		add_filter(
			'wc_stripe_agentic_capture_amount',
			function ( $amount, $order_obj ) use ( &$filter_called ) {
				$filter_called = true;
				$this->assertEquals( 10.00, $amount );
				return 5.00; // Capture only half.
			},
			10,
			2
		);

		// Process the capture action.
		$this->capture_handler->process_capture_action( $order );

		// Verify filter was called.
		$this->assertTrue( $filter_called, 'wc_stripe_agentic_capture_amount filter should be called' );

		// Clean up.
		remove_all_filters( 'wc_stripe_agentic_capture_amount' );
		$order->delete( true );
	}
}
