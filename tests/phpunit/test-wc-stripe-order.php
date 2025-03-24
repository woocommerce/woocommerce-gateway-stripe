<?php
/**
 * Class WC_Stripe_Order
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Order
 */

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Class WC_Stripe_Order tests.
 */
class WC_Stripe_Order_Test extends WP_UnitTestCase {
	/**
	 * Test for `create` method.
	 *
	 * @return void
	 */
	public function test_create() {
		$order = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);
		$this->assertInstanceOf( WC_Stripe_Order::class, $order );
	}

	/**
	 * Tests for `get_by_id`, `query`, `get_by_source_id`, `get_by_charge_id`, `get_by_refund_id`,
	 * `get_by_intent_id`, and `get_by_setup_intent_id`.
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

		$order = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);

		$order->set_source_id( $source_id );
		$order->set_transaction_id( $charge_id );
		$order->set_refund_id( $refund_id );
		$order->set_intent_id( $intent_id );
		$order->set_setup_intent( $setup_intent_id );
		$order->save_meta_data();
		$order->save();

		// get_by_id
		$order_id = $order->get_id();
		$this->assertEquals( $order, WC_Stripe_Order::get_by_id( $order_id ) );

		// query
		$orders = WC_Stripe_Order::query( [ 'status' => OrderStatus::PENDING ] );
		$this->assertEquals( $order, $orders[0] );

		// get_by_source_id
		$this->assertEquals( $order, WC_Stripe_Order::get_by_source_id( $source_id ) );

		// get_by_charge_id
		$this->assertEquals( $order, WC_Stripe_Order::get_by_charge_id( $charge_id ) );

		// get_by_refund_id
		$this->assertEquals( $order, WC_Stripe_Order::get_by_refund_id( $refund_id ) );

		// get_by_intent_id
		$this->assertEquals( $order, WC_Stripe_Order::get_by_intent_id( $intent_id ) );

		// get_by_setup_intent_id
		$this->assertEquals( $order, WC_Stripe_Order::get_by_setup_intent_id( $setup_intent_id ) );
	}

	/**
	 * Tests for getters and setters.
	 *
	 * @return void
	 */
	public function test_properties() {
		$order = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);

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
		$order->set_charge_captured( 'yes' );
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
		$order->set_payment_awaiting_action( true );
		$this->assertTrue( $order->is_payment_awaiting_action() );

		$order->remove_payment_awaiting_action( true );
		$this->assertFalse( $order->is_payment_awaiting_action() );

		$this->assertEquals( 100, $order->get_fee() );
		$this->assertEquals( 100, $order->get_net() );

		$order->delete_fee();
		$order->delete_net();
		$order->save_meta_data();

		$this->assertEmpty( $order->get_fee() );
		$this->assertEmpty( $order->get_net() );
	}

	/**
	 * Tests for `lock_refund`, `get_lock_refund`, `unlock_refund`, `lock_payment`, `get_lock_payment`, and `unlock_payment`.
	 *
	 * @return void
	 */
	public function test_lockers() {
		// setup
		$order = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);

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
		$order    = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);
		$order_id = $order->get_id();

		// add_payment_intent_to_order
		$intent_id = 'pi_123';
		$order->add_payment_intent_to_order( $intent_id );
		$this->assertEquals( $intent_id, $order->get_intent_id() );

		$note = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$this->assertStringContainsString( 'Stripe payment intent created (Payment Intent ID: pi_123)', $note->content );
	}

	/**
	 * Test for `validate_minimum_amount`.
	 *
	 * @return void
	 */
	public function test_validate_minimum_amount() {
		$order = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);
		$order->set_total( 0.01 );
		$order->save();

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Did not meet minimum amount' );

		$order->validate_minimum_amount();
	}

	/**
	 * Tests for `get_owner_details`.
	 *
	 * @return void
	 */
	public function test_get_owner_details() {
		$order = WC_Stripe_Order::create(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);
		$order->set_billing_phone( '+1 123 1234' );
		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_email( 'test@example.com' );
		$order->save_meta_data();

		$owner_details = $order->get_owner_details();

		$this->assertEquals( '+1 123 1234', $owner_details->phone );
		$this->assertEquals( 'John Doe', $owner_details->name );
		$this->assertEquals( 'test@example.com', $owner_details->email );
	}

	/**
	 * Test for `to_instance`.
	 *
	 * @return void
	 */
	public function test_to_instance() {
		$order = wc_create_order(
			[
				'status'        => OrderStatus::PENDING,
				'customer_id'   => 123,
				'customer_note' => '',
				'total'         => '',
			]
		);

		$this->assertInstanceOf( WC_Stripe_Order::class, WC_Stripe_Order::to_instance( $order ) );
	}
}
