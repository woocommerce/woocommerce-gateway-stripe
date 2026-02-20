<?php
/**
 * Tests for WC_Stripe_Agentic_Customize_Checkout_Event
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Stripe_Agentic_Customize_Checkout_Event;
use WC_Stripe_Agentic_Customize_Checkout_Line_Item;

/**
 * Class WC_Stripe_Agentic_Customize_Checkout_Event_Test
 *
 * Tests the typed wrapper around the customize_checkout webhook event.
 *
 * @covers WC_Stripe_Agentic_Customize_Checkout_Event
 */
class WC_Stripe_Agentic_Customize_Checkout_Event_Test extends WP_UnitTestCase {

	/**
	 * Test get_id returns the event ID.
	 */
	public function test_get_id() {
		$event = $this->build_event( [ 'id' => 'evt_test_123' ] );
		$this->assertSame( 'evt_test_123', $event->get_id() );
	}

	/**
	 * Test get_id returns empty string when missing.
	 */
	public function test_get_id_returns_empty_when_missing() {
		$event = $this->build_event( [ 'id' => null ] );
		$this->assertSame( '', $event->get_id() );
	}

	/**
	 * Test get_type returns the event type.
	 */
	public function test_get_type() {
		$event = $this->build_event();
		$this->assertSame( 'v1.delegated_checkout.customize_checkout', $event->get_type() );
	}

	/**
	 * Test is_livemode returns false for test events.
	 */
	public function test_is_livemode_returns_false_for_test() {
		$event = $this->build_event( [ 'livemode' => false ] );
		$this->assertFalse( $event->is_livemode() );
	}

	/**
	 * Test is_livemode returns true for live events.
	 */
	public function test_is_livemode_returns_true_for_live() {
		$event = $this->build_event( [ 'livemode' => true ] );
		$this->assertTrue( $event->is_livemode() );
	}

	/**
	 * Test get_currency returns lowercase.
	 */
	public function test_get_currency_returns_lowercase() {
		$event = $this->build_event( [], [ 'currency' => 'USD' ] );
		$this->assertSame( 'usd', $event->get_currency() );
	}

	/**
	 * Test get_currency returns empty string when missing.
	 */
	public function test_get_currency_returns_empty_when_missing() {
		$event = $this->build_event( [], [ 'currency' => null ] );
		$this->assertSame( '', $event->get_currency() );
	}

	/**
	 * Test is_automatic_tax_enabled returns true when enabled.
	 */
	public function test_is_automatic_tax_enabled_returns_true() {
		$event = $this->build_event(
			[],
			[ 'automatic_tax' => (object) [ 'enabled' => true ] ]
		);
		$this->assertTrue( $event->is_automatic_tax_enabled() );
	}

	/**
	 * Test is_automatic_tax_enabled returns false when disabled.
	 */
	public function test_is_automatic_tax_enabled_returns_false() {
		$event = $this->build_event(
			[],
			[ 'automatic_tax' => (object) [ 'enabled' => false ] ]
		);
		$this->assertFalse( $event->is_automatic_tax_enabled() );
	}

	/**
	 * Test is_automatic_tax_enabled defaults to false when missing.
	 */
	public function test_is_automatic_tax_enabled_defaults_to_false() {
		$event = $this->build_event( [], [ 'automatic_tax' => null ] );
		$this->assertFalse( $event->is_automatic_tax_enabled() );
	}

	/**
	 * Test get_line_items returns typed wrappers.
	 */
	public function test_get_line_items_returns_typed_wrappers() {
		$event = $this->build_event();
		$items = $event->get_line_items();

		$this->assertCount( 1, $items );
		$this->assertInstanceOf( WC_Stripe_Agentic_Customize_Checkout_Line_Item::class, $items[0] );
		$this->assertSame( 'li_test_0', $items[0]->get_id() );
		$this->assertSame( 'SKU-123', $items[0]->get_sku_id() );
	}

	/**
	 * Test get_line_items returns empty array when missing.
	 */
	public function test_get_line_items_returns_empty_when_missing() {
		$event = $this->build_event( [], [ 'line_item_details' => null ] );
		$this->assertSame( [], $event->get_line_items() );
	}

	/**
	 * Test get_line_items handles multiple items.
	 */
	public function test_get_line_items_handles_multiple_items() {
		$event = $this->build_event(
			[],
			[
				'line_item_details' => [
					(object) [
						'id'     => 'li_1',
						'sku_id' => 'SKU-A',
					],
					(object) [
						'id'     => 'li_2',
						'sku_id' => 'SKU-B',
					],
					(object) [
						'id'     => 'li_3',
						'sku_id' => 'SKU-C',
					],
				],
			]
		);

		$items = $event->get_line_items();
		$this->assertCount( 3, $items );
		$this->assertSame( 'SKU-A', $items[0]->get_sku_id() );
		$this->assertSame( 'SKU-B', $items[1]->get_sku_id() );
		$this->assertSame( 'SKU-C', $items[2]->get_sku_id() );
	}

	/**
	 * Test get_shipping_address returns address object.
	 */
	public function test_get_shipping_address() {
		$event   = $this->build_event();
		$address = $event->get_shipping_address();

		$this->assertIsObject( $address );
		$this->assertSame( 'US', $address->country );
		$this->assertSame( 'CA', $address->state );
		$this->assertSame( '90210', $address->postal_code );
	}

	/**
	 * Test get_shipping_address returns null when missing.
	 */
	public function test_get_shipping_address_returns_null_when_missing() {
		$event = $this->build_event( [], [ 'shipping_details' => null ] );
		$this->assertNull( $event->get_shipping_address() );
	}

	/**
	 * Test get_billing_address returns address object.
	 */
	public function test_get_billing_address() {
		$event   = $this->build_event();
		$address = $event->get_billing_address();

		$this->assertIsObject( $address );
		$this->assertSame( 'US', $address->country );
	}

	/**
	 * Test get_billing_address returns null when missing.
	 */
	public function test_get_billing_address_returns_null_when_missing() {
		$event = $this->build_event( [], [ 'billing_details' => null ] );
		$this->assertNull( $event->get_billing_address() );
	}

	/**
	 * Test get_tax_address prefers shipping address.
	 */
	public function test_get_tax_address_prefers_shipping() {
		$event = $this->build_event(
			[],
			[
				'shipping_details' => (object) [
					'address' => (object) [
						'country' => 'US',
						'state'   => 'CA',
					],
				],
				'billing_details'  => (object) [
					'address' => (object) [
						'country' => 'US',
						'state'   => 'NY',
					],
				],
			]
		);

		$this->assertSame( 'CA', $event->get_tax_address()->state );
	}

	/**
	 * Test get_tax_address falls back to billing when shipping is missing.
	 */
	public function test_get_tax_address_falls_back_to_billing() {
		$event = $this->build_event(
			[],
			[
				'shipping_details' => null,
				'billing_details'  => (object) [
					'address' => (object) [
						'country' => 'US',
						'state'   => 'NY',
					],
				],
			]
		);

		$this->assertSame( 'NY', $event->get_tax_address()->state );
	}

	/**
	 * Test get_tax_address returns null when both addresses are missing.
	 */
	public function test_get_tax_address_returns_null_when_both_missing() {
		$event = $this->build_event(
			[],
			[
				'shipping_details' => null,
				'billing_details'  => null,
			]
		);

		$this->assertNull( $event->get_tax_address() );
	}

	/**
	 * Builds a customize_checkout event for testing.
	 *
	 * @param array $top_overrides   Top-level fields to override.
	 * @param array $data_overrides  Fields inside data to override.
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	private function build_event( array $top_overrides = [], array $data_overrides = [] ): WC_Stripe_Agentic_Customize_Checkout_Event {
		$data_defaults = [
			'automatic_tax'     => (object) [ 'enabled' => false ],
			'currency'          => 'usd',
			'line_item_details' => [
				(object) [
					'id'              => 'li_test_0',
					'sku_id'          => 'SKU-123',
					'unit_amount'     => 2500,
					'quantity'        => 1,
					'name'            => 'Test Product',
					'amount_discount' => 0,
					'amount_subtotal' => 2500,
					'amount_tax'      => 0,
					'amount_total'    => 2500,
					'tax_rates'       => [],
				],
			],
			'shipping_details'  => (object) [
				'address' => (object) [
					'country'     => 'US',
					'state'       => 'CA',
					'postal_code' => '90210',
					'city'        => 'Beverly Hills',
				],
			],
			'billing_details'   => (object) [
				'address' => (object) [
					'country'     => 'US',
					'state'       => 'CA',
					'postal_code' => '90210',
					'city'        => 'Beverly Hills',
				],
			],
		];

		$data = (object) array_merge( $data_defaults, $data_overrides );

		$top_defaults = [
			'type'     => 'v1.delegated_checkout.customize_checkout',
			'id'       => 'evt_test_456',
			'livemode' => false,
			'data'     => $data,
		];

		$merged = array_merge( $top_defaults, $top_overrides );

		return new WC_Stripe_Agentic_Customize_Checkout_Event( (object) $merged );
	}
}
