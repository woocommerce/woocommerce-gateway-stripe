<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Tests for WC_Stripe_Checkout_Customer_Note.
 */
class WC_Stripe_Checkout_Customer_Note_Test extends WP_UnitTestCase {

	/**
	 * Note stored on the order awaiting payment.
	 */
	private const NOTE = 'Leave the parcel at the back door.';

	/**
	 * Instance under test.
	 *
	 * @var WC_Stripe_Checkout_Customer_Note
	 */
	private $customer_note;

	public function set_up(): void {
		parent::set_up();

		$this->customer_note = new WC_Stripe_Checkout_Customer_Note();

		WC()->session->init();
		WC()->session->set( 'order_awaiting_payment', null );
	}

	public function tear_down(): void {
		// In-memory session data survives the per-test DB rollback.
		WC()->session->set( 'order_awaiting_payment', null );
		unset( $_POST['order_comments'] );

		parent::tear_down();
	}

	/**
	 * Creates an order and marks it as the one awaiting payment in the session.
	 *
	 * @param array $props Order properties passed to the order helper.
	 * @return WC_Order
	 */
	private function create_awaiting_order( array $props = [] ): WC_Order {
		$order = WC_Helper_Order::create_order(
			1,
			null,
			array_merge(
				[
					'status'         => OrderStatus::FAILED,
					'payment_method' => WC_Stripe_UPE_Payment_Gateway::ID,
					'customer_note'  => self::NOTE,
				],
				$props
			)
		);

		WC()->session->set( 'order_awaiting_payment', $order->get_id() );

		return $order;
	}

	/**
	 * @dataProvider provide_resumable_statuses
	 */
	public function test_restores_note_from_awaiting_stripe_order( string $status ) {
		$this->create_awaiting_order( [ 'status' => $status ] );

		$this->assertSame( self::NOTE, $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	public function provide_resumable_statuses(): array {
		return [
			'failed order'  => [ OrderStatus::FAILED ],
			'pending order' => [ OrderStatus::PENDING ],
		];
	}

	/**
	 * @dataProvider provide_stripe_payment_methods
	 */
	public function test_restores_note_for_every_stripe_gateway_id( string $payment_method ) {
		$this->create_awaiting_order( [ 'payment_method' => $payment_method ] );

		$this->assertSame( self::NOTE, $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	public function provide_stripe_payment_methods(): array {
		return [
			'main gateway'    => [ WC_Stripe_UPE_Payment_Gateway::ID ],
			'UPE card method' => [ WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_Payment_Methods::CARD ],
		];
	}

	public function test_keeps_existing_default_value() {
		$this->create_awaiting_order();

		$this->assertSame( 'Existing default', $this->customer_note->restore_note_from_awaiting_order( 'Existing default' ) );
	}

	public function test_returns_value_when_no_order_is_awaiting_payment() {
		$this->assertNull( $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	public function test_returns_value_when_awaiting_order_no_longer_exists() {
		$order = $this->create_awaiting_order();
		WC_Helper_Order::delete_order( $order->get_id() );

		$this->assertNull( $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	/**
	 * @dataProvider provide_non_resumable_statuses
	 */
	public function test_ignores_orders_that_are_no_longer_resumable( string $status ) {
		$this->create_awaiting_order( [ 'status' => $status ] );

		$this->assertNull( $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	public function provide_non_resumable_statuses(): array {
		return [
			'processing order' => [ OrderStatus::PROCESSING ],
			'completed order'  => [ OrderStatus::COMPLETED ],
			'cancelled order'  => [ OrderStatus::CANCELLED ],
		];
	}

	public function test_ignores_orders_paid_with_another_gateway() {
		$this->create_awaiting_order( [ 'payment_method' => 'bacs' ] );

		$this->assertNull( $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	public function test_ignores_orders_without_a_customer_note() {
		$this->create_awaiting_order( [ 'customer_note' => '' ] );

		$this->assertNull( $this->customer_note->restore_note_from_awaiting_order( null ) );
	}

	/**
	 * The plugin bootstrap registers the hook, so the checkout field default itself must carry the note.
	 */
	public function test_checkout_field_default_is_prefilled_through_the_registered_hook() {
		$this->assertNotFalse( has_filter( 'default_checkout_order_comments' ) );

		$this->create_awaiting_order();
		unset( $_POST['order_comments'] );

		$this->assertSame( self::NOTE, WC()->checkout()->get_value( 'order_comments' ) );
	}

	public function test_checkout_field_default_stays_empty_without_an_awaiting_order() {
		unset( $_POST['order_comments'] );

		$this->assertNull( WC()->checkout()->get_value( 'order_comments' ) );
	}

	public function test_posted_value_wins_over_restored_note() {
		$this->create_awaiting_order();
		$_POST['order_comments'] = 'Typed again';

		$this->assertSame( 'Typed again', WC()->checkout()->get_value( 'order_comments' ) );
	}
}
