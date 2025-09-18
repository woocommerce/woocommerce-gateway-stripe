<?php

namespace WooCommerce\Stripe\Tests;

use WC_Order;
use WC_Stripe_Exception;
use WP_UnitTestCase;
use WC_Data_Exception;
use WC_Stripe_Order_Helper;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;

/**
 * Class WC_Stripe_Order_Helper
 *
 * @package WooCommerce/Stripe/WC_Stripe_Order_Helper
 *
 * Class WC_Stripe_Order_Helper tests.
 */
class WC_Stripe_Order_Helper_Test extends WP_UnitTestCase {
	/**
	 * Tests for getters and setters.
	 *
	 * @return void
	 */
	public function test_properties(): void {
		$order = WC_Helper_Order::create_order();

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Tests for `is_payment_awaiting_action`, `set_payment_awaiting_action`, and `remove_payment_awaiting_action`.
		$order_helper->set_payment_awaiting_action( $order );
		$this->assertTrue( $order_helper->is_payment_awaiting_action( $order ) );

		$order_helper->remove_payment_awaiting_action( $order );
		$this->assertFalse( $order_helper->is_payment_awaiting_action( $order ) );

		$order_helper->update_stripe_fee( $order, 100 );
		$order_helper->update_stripe_net( $order, 100 );

		$this->assertEquals( 100, $order_helper->get_stripe_fee( $order ) );
		$this->assertEquals( 100, $order_helper->get_stripe_net( $order ) );

		$order_helper->delete_stripe_fee( $order );
		$order_helper->delete_stripe_net( $order );
		$order->save_meta_data();

		$this->assertEmpty( $order_helper->get_stripe_fee( $order ) );
		$this->assertEmpty( $order_helper->get_stripe_net( $order ) );
	}

	/**
	 * Tests for `lock_refund`, `get_lock_refund`, `unlock_refund`, `lock_payment`, `get_lock_payment`, and `unlock_payment`.
	 *
	 * @return void
	 */
	public function test_lockers(): void {
		// setup
		$order = WC_Helper_Order::create_order();

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// refund
		$order_helper->lock_order_refund( $order );
		$this->assertTrue( $order_helper->get_order_existing_refund_lock( $order ) > 0 );
		$order_helper->unlock_order_refund( $order );
		$this->assertEmpty( $order_helper->get_order_existing_refund_lock( $order ) );

		// payment
		$order_helper->lock_order_payment( $order );
		$this->assertTrue( $order_helper->get_order_existing_payment_lock( $order ) > 0 );
		$order_helper->unlock_order_payment( $order );
		$this->assertEmpty( $order_helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Tests for `add_payment_intent_to_order`.
	 *
	 * @return void
	 */
	public function test_add_payment_intent_to_order(): void {
		// setup
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// add_payment_intent_to_order
		$intent_id = 'pi_123';
		$order_helper->add_payment_intent_to_order( $intent_id, $order );
		$this->assertEquals( $intent_id, $order_helper->get_intent_id_from_order( $order ) );

		$note = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$this->assertStringContainsString( 'Stripe payment intent created (Payment Intent ID: pi_123)', $note->content );
	}

	/**
	 * Test for `validate_minimum_order_amount`.
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function test_validate_minimum_order_amount(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 0.01 );
		$order->save();

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Did not meet minimum amount' );

		WC_Stripe_Order_Helper::get_instance()->validate_minimum_order_amount( $order );
	}

	/**
	 * Tests for `get_owner_details`.
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function test_get_owner_details(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_phone( '+1 123 1234' );
		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_email( 'test@example.com' );
		$order->save_meta_data();

		$owner_details = WC_Stripe_Order_Helper::get_instance()->get_owner_details( $order );

		$this->assertEquals( '+1 123 1234', $owner_details->phone );
		$this->assertEquals( 'John Doe', $owner_details->name );
		$this->assertEquals( 'test@example.com', $owner_details->email );
	}

	/**
	 * Tests for `is_stripe_gateway_order`.
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function test_is_stripe_gateway_order(): void {
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Test with a Stripe order (Klarna).
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe_klarna' );
		$this->assertTrue( $order_helper->is_stripe_gateway_order( $order ) );

		// Test with a non-Stripe order.
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'cod' );
		$this->assertFalse( $order_helper->is_stripe_gateway_order( $order ) );

		// Test with an empty order.
		$order = new WC_Order();
		$this->assertFalse( $order_helper->is_stripe_gateway_order( $order ) );
	}
}
