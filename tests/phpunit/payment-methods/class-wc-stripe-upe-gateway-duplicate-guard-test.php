<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Tests the duplicate-charge guard glue on WC_Stripe_UPE_Payment_Gateway: when the guard key
 * applies, and how a superseded order is cancelled.
 *
 * @package WooCommerce/Stripe/WC_Stripe_UPE_Payment_Gateway
 */
class WC_Stripe_UPE_Gateway_Duplicate_Guard_Test extends WP_UnitTestCase {

	/**
	 * The gateway under test.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Sets up a real gateway and a clean cart.
	 */
	public function set_up() {
		parent::set_up();

		if ( is_null( WC()->cart ) ) {
			wc_load_cart();
		}
		WC()->cart->empty_cart();

		$this->gateway = new WC_Stripe_UPE_Payment_Gateway();
	}

	/**
	 * Empties the cart after each test.
	 */
	public function tear_down() {
		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}
		parent::tear_down();
	}

	/**
	 * Invokes a private gateway method.
	 *
	 * @param string $name The method name.
	 * @param array  $args The arguments.
	 * @return mixed
	 */
	private function invoke( string $name, array $args ) {
		$method = new ReflectionMethod( WC_Stripe_UPE_Payment_Gateway::class, $name );
		$method->setAccessible( true );

		return $method->invokeArgs( $this->gateway, $args );
	}

	/**
	 * Builds an order carrying a cart hash and billing email.
	 *
	 * @param string $cart_hash The cart hash to store.
	 * @param string $email     The billing email to store.
	 * @return WC_Order
	 */
	private function make_order( string $cart_hash, string $email ): WC_Order {
		$order = new WC_Order();
		$order->set_created_via( 'checkout' );
		$order->set_cart_hash( $cart_hash );
		$order->set_billing_email( $email );
		$order->save();

		return $order;
	}

	/**
	 * The guard applies (returns a key) only when the order represents the live cart, so a resubmit
	 * of an interrupted checkout is covered while an empty or mismatched cart is not.
	 */
	public function test_guard_key_requires_the_live_cart(): void {
		$order = $this->make_order( 'some-hash', 'shopper@example.com' );

		// Empty cart: nothing to protect.
		$this->assertSame( '', $this->invoke( 'get_duplicate_charge_guard_key', [ $order ] ) );

		// Populate the cart and align the order with it, as a real checkout order would be.
		$product = WC_Helper_Product::create_simple_product();
		WC()->cart->add_to_cart( $product->get_id() );
		$live_hash = WC()->cart->get_cart_hash();

		$matching = $this->make_order( $live_hash, 'shopper@example.com' );
		$this->assertNotSame( '', $this->invoke( 'get_duplicate_charge_guard_key', [ $matching ] ) );

		// An order for a different cart than the one in the session is not guarded.
		$mismatched = $this->make_order( 'different-hash', 'shopper@example.com' );
		$this->assertSame( '', $this->invoke( 'get_duplicate_charge_guard_key', [ $mismatched ] ) );
	}

	/**
	 * A pending order superseded by an already-paid cart is cancelled with a note that points to the
	 * paid order, and the cart is emptied.
	 */
	public function test_cancel_superseded_order_cancels_and_empties_cart(): void {
		$product = WC_Helper_Product::create_simple_product();
		WC()->cart->add_to_cart( $product->get_id() );
		$this->assertFalse( WC()->cart->is_empty() );

		$paid      = $this->make_order( 'hash', 'shopper@example.com' );
		$redundant = $this->make_order( 'hash', 'shopper@example.com' );
		$redundant->set_status( OrderStatus::PENDING );
		$redundant->save();

		$this->invoke( 'cancel_order_superseded_by_paid_cart', [ $redundant, $paid ] );

		$redundant = wc_get_order( $redundant->get_id() );
		$this->assertTrue( $redundant->has_status( OrderStatus::CANCELLED ) );
		$this->assertTrue( WC()->cart->is_empty() );

		$notes     = wc_get_order_notes( [ 'order_id' => $redundant->get_id() ] );
		$note_text = implode( ' ', wp_list_pluck( $notes, 'content' ) );
		$this->assertStringContainsString( 'duplicate charge', $note_text );
		$this->assertStringContainsString( (string) $paid->get_id(), $note_text );
	}

	/**
	 * A superseded order that is not pending or failed (for example, one already paid) is left
	 * untouched, so the guard never cancels a real order.
	 */
	public function test_cancel_superseded_order_leaves_a_paid_order_alone(): void {
		$paid      = $this->make_order( 'hash', 'shopper@example.com' );
		$processed = $this->make_order( 'hash', 'shopper@example.com' );
		$processed->set_status( OrderStatus::PROCESSING );
		$processed->save();

		$this->invoke( 'cancel_order_superseded_by_paid_cart', [ $processed, $paid ] );

		$processed = wc_get_order( $processed->get_id() );
		$this->assertTrue( $processed->has_status( OrderStatus::PROCESSING ) );
	}
}
