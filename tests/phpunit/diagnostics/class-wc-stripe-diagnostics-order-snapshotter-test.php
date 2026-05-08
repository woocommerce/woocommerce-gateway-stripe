<?php

require_once WC_STRIPE_PLUGIN_PATH . '/includes/diagnostics/class-wc-stripe-diagnostics-order-snapshotter.php';

/**
 * Tests for WC_Stripe_Diagnostics_Order_Snapshotter.
 *
 * Each test drives through the real `wc_stripe_diagnostics_trace_finalized`
 * action so the Trace_Store -> action -> snapshotter contract is
 * exercised end-to-end without a separate test for the action wiring.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Order_Snapshotter_Test extends WP_UnitTestCase {

	/** @var WC_Stripe_Diagnostics_Trace_Store */
	private $store;

	/** @var WC_Stripe_Diagnostics_Redactor */
	private $redactor;

	/** @var WC_Stripe_Diagnostics_Order_Snapshotter */
	private $snapshotter;

	public function set_up() {
		parent::set_up();
		$this->store       = new WC_Stripe_Diagnostics_Trace_Store();
		$this->redactor    = new WC_Stripe_Diagnostics_Redactor();
		$this->snapshotter = new WC_Stripe_Diagnostics_Order_Snapshotter( $this->store, $this->redactor );
		$this->snapshotter->register();
		$this->store->delete_all();
	}

	public function tear_down() {
		$this->snapshotter->unregister();
		$this->store->delete_all();
		parent::tear_down();
	}

	/**
	 * One fixture order exercises happy-path field shape, the line-item
	 * and order-note caps, content truncation, PII scrubbing of note
	 * bodies, and the customer-note exclusion.
	 */
	public function test_snapshot_built_from_fixture_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_payment_method_title( 'Credit Card (Stripe)' );
		$order->set_billing_country( 'US' );
		$order->set_shipping_country( 'US' );

		// 30 line items so the cap (20) is exercised. The fixture starts
		// with one item; add 29 more.
		for ( $i = 0; $i < 29; $i++ ) {
			$item = new WC_Order_Item_Product();
			$item->set_props(
				[
					'product'  => WC_Helper_Product::create_simple_product(),
					'quantity' => 1,
					'subtotal' => 1,
					'total'    => 1,
				]
			);
			$item->save();
			$order->add_item( $item );
		}
		$order->save();

		// 25 admin (system) notes, newest-last in insertion order. The
		// `is_customer_note = 0` argument keeps them admin-only.
		// One note exceeds 500 chars and one contains scrubbable PII.
		for ( $i = 0; $i < 23; $i++ ) {
			$order->add_order_note( "system note {$i}", 0 );
		}
		$order->add_order_note( str_repeat( 'A', 2000 ), 0 );
		// Email is scrubbed by the redactor's `scrub_string` patterns;
		// phone-number scrubbing is not in the redactor's pattern set
		// today, so this fixture exercises email-only.
		$order->add_order_note( 'Contact me at foo@bar.com', 0 );

		// One customer note, which must NOT appear in the snapshot.
		$order->add_order_note( 'leave at front door', 1 );

		// Pin the order id to the trace's meta the way the recorder will
		// in production (via Trace_Store::set_order_id() the first time it
		// sees an order id in a request, response, or webhook).
		$this->store->create( 'sess-happy', [ 'started_at' => time() ] );
		$this->store->set_order_id( 'sess-happy', $order->get_id() );

		$this->store->set_status( 'sess-happy', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED );

		$trace    = $this->store->get( 'sess-happy' );
		$events   = $trace['events'];
		$snapshot = null;
		foreach ( $events as $event ) {
			if ( isset( $event['kind'] ) && 'order_snapshot' === $event['kind'] ) {
				$snapshot = $event;
				break;
			}
		}

		$this->assertNotNull( $snapshot, 'Expected an order_snapshot event after finalization.' );
		$this->assertSame( $order->get_id(), $snapshot['order_id'] );
		$this->assertSame( 'failed', $snapshot['status'] );
		$this->assertSame( 'stripe', $snapshot['payment_method'] );
		$this->assertSame( 'Credit Card (Stripe)', $snapshot['payment_method_title'] );
		$this->assertSame( 'US', $snapshot['billing_country'] );
		$this->assertSame( 'US', $snapshot['shipping_country'] );

		// Caps.
		$this->assertSame( 30, $snapshot['item_count'] );
		$this->assertCount( 20, $snapshot['line_items'] );
		$this->assertCount( 20, $snapshot['order_notes'] );

		// Note bodies are newest-first, so the PII note is at index 0
		// and the long note is at index 1. Long note is truncated.
		$this->assertLessThanOrEqual( 500, strlen( $snapshot['order_notes'][1]['content'] ) );

		// PII scrubbing — email replaced by the redactor's [email]
		// placeholder.
		$pii_content = $snapshot['order_notes'][0]['content'];
		$this->assertStringNotContainsString( 'foo@bar.com', $pii_content );
		$this->assertStringContainsString( '[email]', $pii_content );

		// Customer note excluded.
		foreach ( $snapshot['order_notes'] as $note ) {
			$this->assertNotSame( 'leave at front door', $note['content'] );
		}
	}
}
