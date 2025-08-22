<?php

namespace WooCommerce\Stripe\Tests;

use Automattic\WooCommerce\Enums\OrderStatus;
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
	 * Tests for `query`, `get_by_order_source_id`, `get_by_order_charge_id`, `get_by_order_refund_id`,
	 * `get_by_order_intent_id`, and `get_by_order_setup_intent_id`.
	 *
	 * @return void
	 */
	public function test_retrieve() {
		// setup
		$source_id       = 'src_123';
		$charge_id       = 'ch_123';
		$refund_id       = 're_123';
		$intent_id       = 'pi_123';
		$setup_intent_id = 'seti_123';

		$order = WC_Helper_Order::create_order();

		$order->set_source_id( $source_id );
		$order->set_transaction_id( $charge_id );
		$order->set_refund_id( $refund_id );
		$order->set_intent_id( $intent_id );
		$order->set_setup_intent( $setup_intent_id );
		$order->save_meta_data();
		$order->save();

		// query
		$orders = WC_Stripe_Order::query( [ 'status' => OrderStatus::PENDING ] );
		$this->assertEquals( $order, $orders[0] );

		// get_order_by_source_id
		$this->assertEquals( $order, WC_Stripe_Order_Helper::get_order_by_source_id( $source_id ) );

		// get_order_by_charge_id
		$this->assertEquals( $order, WC_Stripe_Order_Helper::get_order_by_charge_id( $charge_id ) );

		// get_order_by_refund_id
		$this->assertEquals( $order, WC_Stripe_Order_Helper::get_order_by_refund_id( $refund_id ) );

		// get_order_by_intent_id
		$this->assertEquals( $order, WC_Stripe_Order_Helper::get_order_by_intent_id( $intent_id ) );

		// get_order_by_setup_intent_id
		$this->assertEquals( $order, WC_Stripe_Order_Helper::get_order_by_setup_intent_id( $setup_intent_id ) );
	}

	/**
	 * Tests for getters and setters.
	 *
	 * @return void
	 */
	public function test_properties() {
		$order = WC_Helper_Order::create_order();

		$order->set_source_id( 'src_123' );
		$order->set_transaction_id( 'ch_123' );
		$order->set_refund_id( 're_123' );
		$order->set_intent_id( 'pi_123' );
		$order->set_setup_intent( 'seti_123' );
		$order->set_stripe_currency( 'usd' );
		$order->set_card_brand( 'visa' );
		$order->set_status_before_hold( OrderStatus::PENDING );
		$order->set_mandate_id( 'mandate_123' );
		$order->set_upe_payment_type( 'card' );
		$order->set_stripe_customer_id( 'cus_123' );
		$order->set_multibanco_data(
			[
				'entity'    => '123',
				'reference' => '123',
				'amount'    => 100,
			]
		);
		$order->set_upe_redirect_processed( true );
		$order->set_upe_waiting_for_redirect( true );
		$order->set_charge_captured( true );
		$order->set_status_final( true );

		$order->set_fee( 100 );
		$order->set_net( 100 );

		$order->save_meta_data();
		$order->save();

		$this->assertEquals( 'src_123', $order->get_source_id() );
		$this->assertEquals( 'ch_123', $order->get_transaction_id() );
		$this->assertEquals( 're_123', $order->get_refund_id() );
		$this->assertEquals( 'pi_123', $order->get_intent_id() );
		$this->assertEquals( 'seti_123', $order->get_setup_intent() );
		$this->assertEquals( 'usd', $order->get_stripe_currency() );
		$this->assertEquals( 'visa', $order->get_card_brand() );
		$this->assertEquals( OrderStatus::PENDING, $order->get_status_before_hold() );
		$this->assertEquals( 'mandate_123', $order->get_mandate_id() );
		$this->assertEquals( 'card', $order->get_upe_payment_type() );
		$this->assertEquals( 'cus_123', $order->get_stripe_customer_id() );
		$this->assertEquals(
			[
				'entity'    => '123',
				'reference' => '123',
				'amount'    => 100,
			],
			$order->get_multibanco_data()
		);
		$this->assertTrue( $order->is_upe_redirect_processed() );
		$this->assertTrue( $order->is_upe_waiting_for_redirect() );
		$this->assertTrue( $order->is_charge_captured() );
		$this->assertTrue( $order->is_status_final() );

		// Tests for `get_payment_awaiting_action`, `set_payment_awaiting_action`, and `remove_payment_awaiting_action`.
		WC_Stripe_Order_Helper::set_payment_awaiting_action( $order );
		$this->assertTrue( $order->is_payment_awaiting_action() );

		WC_Stripe_Order_Helper::remove_payment_awaiting_action( $order );
		$this->assertFalse( $order->is_payment_awaiting_action() );

		$this->assertEquals( 100, WC_Stripe_Order_Helper::get_stripe_fee( $order ) );
		$this->assertEquals( 100, WC_Stripe_Order_Helper::get_stripe_net( $order ) );

		WC_Stripe_Order_Helper::delete_stripe_fee();
		WC_Stripe_Order_Helper::delete_stripe_net();
		$order->save_meta_data();

		$this->assertEmpty( WC_Stripe_Order_Helper::get_stripe_fee( $order ) );
		$this->assertEmpty( WC_Stripe_Order_Helper::get_stripe_net( $order ) );
	}

	/**
	 * Tests for `lock_refund`, `get_lock_refund`, `unlock_refund`, `lock_payment`, `get_lock_payment`, and `unlock_payment`.
	 *
	 * @return void
	 */
	public function test_lockers() {
		// setup
		$order = WC_Helper_Order::create_order();

		// refund
		$order->lock_refund();
		$this->assertTrue( $order->get_lock_refund() > 0 );
		$order->unlock_refund();
		$this->assertEmpty( $order->get_lock_refund() );

		// payment
		$order->lock_payment();
		$this->assertTrue( $order->get_lock_payment() > 0 );
		$order->unlock_payment();
		$this->assertEmpty( $order->get_lock_payment() );
	}

	/**
	 * Tests for `add_payment_intent_to_order`.
	 *
	 * @return void
	 */
	public function test_add_payment_intent_to_order() {
		// setup
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// add_payment_intent_to_order
		$intent_id = 'pi_123';
		WC_Stripe_Order_Helper::add_payment_intent_to_order( $intent_id, $order );
		$this->assertEquals( $intent_id, WC_Stripe_Order_Helper::get_intent_id_from_order( $order ) );

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
	 */
	public function test_validate_minimum_order_amount() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 0.01 );
		$order->save();

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Did not meet minimum amount' );

		WC_Stripe_Order_Helper::validate_minimum_order_amount( $order );
	}

	/**
	 * Tests for `get_owner_details`.
	 *
	 * @return void
	 */
	public function test_get_owner_details() {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_phone( '+1 123 1234' );
		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_email( 'test@example.com' );
		$order->save_meta_data();

		$owner_details = WC_Stripe_Order_Helper::get_owner_details( $order );

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
	public function test_is_stripe_gateway_order() {
		// Test with a Stripe order (Klarna).
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe_klarna' );
		$this->assertTrue( WC_Stripe_Order_Helper::is_stripe_gateway_order( $order ) );

		// Test with a non-Stripe order.
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'cod' );
		$this->assertFalse( WC_Stripe_Order_Helper::is_stripe_gateway_order( $order ) );

		// Test with an empty order.
		$order = new WC_Order();
		$this->assertFalse( WC_Stripe_Order_Helper::is_stripe_gateway_order( $order ) );
	}
}
