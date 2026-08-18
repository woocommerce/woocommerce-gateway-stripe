<?php

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
	 * Tests for `get_stripe_refund_id_for_refund`, `update_stripe_refund_id_for_refund`,
	 * and `delete_stripe_refund_id_for_refund`.
	 *
	 * @param bool $pass_null Whether to exercise the null-argument contract instead of a real refund.
	 * @return void
	 * @dataProvider provide_test_stripe_refund_id_for_refund
	 */
	public function test_stripe_refund_id_for_refund( bool $pass_null ): void {
		if ( $pass_null ) {
			$this->assertFalse( $this->helper->get_stripe_refund_id_for_refund( null ) );
			$this->assertFalse( $this->helper->update_stripe_refund_id_for_refund( null, 're_null' ) );
			$this->assertFalse( $this->helper->delete_stripe_refund_id_for_refund( null ) );
			return;
		}

		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 5.00,
			]
		);

		$this->assertEmpty( $this->helper->get_stripe_refund_id_for_refund( $refund ) );

		// The update does not save, mirroring the order-level methods' contract.
		$this->helper->update_stripe_refund_id_for_refund( $refund, 're_123' );
		$this->assertSame( 're_123', $this->helper->get_stripe_refund_id_for_refund( $refund ) );

		$refund->save_meta_data();
		$reloaded = wc_get_order( $refund->get_id() );
		$this->assertSame( 're_123', $this->helper->get_stripe_refund_id_for_refund( $reloaded ) );

		// The parent order's meta is not touched by the per-refund methods.
		$this->assertEmpty( $this->helper->get_stripe_refund_id( wc_get_order( $order->get_id() ) ) );

		$this->helper->delete_stripe_refund_id_for_refund( $reloaded );
		$reloaded->save_meta_data();
		$this->assertEmpty( $this->helper->get_stripe_refund_id_for_refund( wc_get_order( $refund->get_id() ) ) );
	}

	/**
	 * Data provider for `test_stripe_refund_id_for_refund`.
	 *
	 * @return array
	 */
	public function provide_test_stripe_refund_id_for_refund(): array {
		return [
			'real refund record' => [ 'pass_null' => false ],
			'null refund'        => [ 'pass_null' => true ],
		];
	}

	/**
	 * Tests for `get_refunds_with_stripe_refund_ids` and `delete_stripe_refund_ids_from_refunds`.
	 *
	 * @return void
	 */
	public function test_refunds_with_stripe_refund_ids(): void {
		$order = WC_Helper_Order::create_order();

		// No refunds at all.
		$this->assertSame( [], $this->helper->get_refunds_with_stripe_refund_ids( $order ) );

		$tagged_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 5.00,
			]
		);
		$this->helper->update_stripe_refund_id_for_refund( $tagged_refund, 're_1' );
		$tagged_refund->save_meta_data();

		$untagged_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 7.00,
			]
		);

		$order = wc_get_order( $order->get_id() );

		// Only the tagged record is returned.
		$found = $this->helper->get_refunds_with_stripe_refund_ids( $order );
		$this->assertCount( 1, $found );
		$this->assertSame( $tagged_refund->get_id(), current( $found )->get_id() );

		// Bulk deletion erases and persists the tagged record's ID, leaving the other record alone.
		$this->helper->delete_stripe_refund_ids_from_refunds( $order );

		$this->assertEmpty( $this->helper->get_stripe_refund_id_for_refund( wc_get_order( $tagged_refund->get_id() ) ) );
		$this->assertSame( [], $this->helper->get_refunds_with_stripe_refund_ids( wc_get_order( $order->get_id() ) ) );
		$this->assertInstanceOf( WC_Order_Refund::class, wc_get_order( $untagged_refund->get_id() ) );
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
	 * A malformed structured payment lock must fail closed instead of throwing or being replaced.
	 *
	 * @dataProvider provide_malformed_payment_locks
	 * @param mixed $malformed_lock Malformed payment lock metadata.
	 * @return void
	 */
	public function test_lock_order_payment_treats_malformed_structured_metadata_as_locked( $malformed_lock ): void {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_stripe_lock_payment', $malformed_lock );
		$order->save_meta_data();

		$this->assertTrue( $this->helper->lock_order_payment( $order ) );
		$this->assertEquals( $malformed_lock, $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Data provider for malformed structured payment lock metadata.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function provide_malformed_payment_locks(): array {
		return [
			'empty array'     => [ [] ],
			'non-empty array' => [ [ time() + 5 * MINUTE_IN_SECONDS ] ],
			'object'          => [ (object) [ 'expires_at' => time() + 5 * MINUTE_IN_SECONDS ] ],
		];
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
