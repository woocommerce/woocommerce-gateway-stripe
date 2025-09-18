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
	 * Order helper instance.
	 *
	 * @var WC_Stripe_Order_Helper
	 */
	protected $helper;

	/**
	 * @inheritDoc
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure the helper is reset before each test.
		$this->helper = new WC_Stripe_Order_Helper();
	}

	/**
	 * Tests for getters and setters.
	 *
	 * @return void
	 */
	public function test_properties(): void {
		$order = WC_Helper_Order::create_order();

		// Tests for `is_payment_awaiting_action`, `set_payment_awaiting_action`, and `remove_payment_awaiting_action`.
		$this->helper->set_payment_awaiting_action( $order );
		$this->assertTrue( $this->helper->is_payment_awaiting_action( $order ) );

		$this->helper->remove_payment_awaiting_action( $order );
		$this->assertFalse( $this->helper->is_payment_awaiting_action( $order ) );

		// Tests for `update_stripe_fee`, `get_stripe_fee`, `delete_stripe_fee`,
		// `update_stripe_net`, `get_stripe_net`, and `delete_stripe_net`.
		$this->helper->update_stripe_fee( $order, 100 );
		$this->helper->update_stripe_net( $order, 100 );

		$this->assertEquals( 100, $this->helper->get_stripe_fee( $order ) );
		$this->assertEquals( 100, $this->helper->get_stripe_net( $order ) );

		$this->helper->delete_stripe_fee( $order );
		$this->helper->delete_stripe_net( $order );
		$order->save_meta_data();

		$this->assertEmpty( $this->helper->get_stripe_fee( $order ) );
		$this->assertEmpty( $this->helper->get_stripe_net( $order ) );
	}

	/**
	 * Tests for `lock_order_refund`, `get_order_existing_refund_lock`, `unlock_order_refund`,
	 * `lock_order_payment`, `get_order_existing_payment_lock`, and `unlock_order_payment`.
	 *
	 * @return void
	 */
	public function test_lockers(): void {
		// setup
		$order = WC_Helper_Order::create_order();

		// refund
		$this->helper->lock_order_refund( $order );
		$this->assertTrue( $this->helper->get_order_existing_refund_lock( $order ) > 0 );
		$this->helper->unlock_order_refund( $order );
		$this->assertEmpty( $this->helper->get_order_existing_refund_lock( $order ) );

		// payment
		$this->helper->lock_order_payment( $order );
		$this->assertTrue( $this->helper->get_order_existing_payment_lock( $order ) > 0 );
		$this->helper->unlock_order_payment( $order );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
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

		// add_payment_intent_to_order
		$intent_id = 'pi_123';
		$this->helper->add_payment_intent_to_order( $intent_id, $order );
		$this->assertEquals( $intent_id, $this->helper->get_intent_id_from_order( $order ) );

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

		$this->helper->validate_minimum_order_amount( $order );
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

		$owner_details = $this->helper->get_owner_details( $order );

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
		$this->helper = WC_Stripe_Order_Helper::get_instance();

		// Test with a Stripe order (Klarna).
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe_klarna' );
		$this->assertTrue( $this->helper->is_stripe_gateway_order( $order ) );

		// Test with a non-Stripe order.
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'cod' );
		$this->assertFalse( $this->helper->is_stripe_gateway_order( $order ) );

		// Test with an empty order.
		$order = new WC_Order();
		$this->assertFalse( $this->helper->is_stripe_gateway_order( $order ) );
	}
}
