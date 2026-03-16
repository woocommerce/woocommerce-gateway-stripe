<?php
/**
 * Tests for WC_Stripe_Agentic_Customize_Checkout_Event
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Stripe_Agentic_Customize_Checkout_Event;
use WC_Stripe_API_Address;
use WC_Stripe_Agentic_Customize_Checkout_Line_Item;
use Exception;

/**
 * Class WC_Stripe_Agentic_Customize_Checkout_Event_Test
 */
class WC_Stripe_Agentic_Customize_Checkout_Event_Test extends WP_UnitTestCase {

	/**
	 * Test basic accessors return expected values from a complete event.
	 */
	public function test_accessors_return_values_from_complete_event() {
		$event = $this->build_event();

		$this->assertSame( 'evt_test_123', $event->get_id() );
		$this->assertSame( 'v1.delegated_checkout.customize_checkout', $event->get_type() );
		$this->assertFalse( $event->is_livemode() );
		$this->assertSame( 'usd', $event->get_currency() );
	}

	/**
	 * Test accessors return defaults for an empty event.
	 */
	public function test_accessors_return_defaults_for_empty_event() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event( (object) [] );

		$this->assertSame( '', $event->get_id() );
		$this->assertSame( '', $event->get_type() );
		$this->assertFalse( $event->is_livemode() );
		$this->assertSame( '', $event->get_currency() );
	}

	/**
	 * @dataProvider automatic_tax_provider
	 */
	public function test_is_automatic_tax_enabled( ?object $automatic_tax, bool $expected ) {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [
				'data' => (object) [
					'automatic_tax' => $automatic_tax,
				],
			]
		);

		$this->assertSame( $expected, $event->is_automatic_tax_enabled() );
	}

	/**
	 * @return array[]
	 */
	public function automatic_tax_provider(): array {
		return [
			'enabled true'  => [ (object) [ 'enabled' => true ], true ],
			'enabled false' => [ (object) [ 'enabled' => false ], false ],
			'null object'   => [ null, false ],
		];
	}

	/**
	 * Test get_line_items returns typed line item wrappers.
	 */
	public function test_get_line_items_returns_typed_wrappers() {
		$event = $this->build_event();
		$items = $event->get_line_items();

		$this->assertCount( 1, $items );
		$this->assertInstanceOf( WC_Stripe_Agentic_Customize_Checkout_Line_Item::class, $items[0] );
		$this->assertSame( 'li_test_0', $items[0]->get_id() );
		$this->assertSame( '42', $items[0]->get_sku_id() );
	}

	/**
	 * Test get_line_items returns empty array when missing.
	 */
	public function test_get_line_items_empty_when_missing() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event( (object) [] );
		$this->assertSame( [], $event->get_line_items() );
	}

	/**
	 * Test get_line_items handles non-array data gracefully.
	 */
	public function test_get_line_items_handles_non_array_data() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [
				'data' => (object) [
					'line_item_details' => 'not_an_array',
				],
			]
		);

		$this->assertSame( [], $event->get_line_items() );
	}

	/**
	 * Test get_shipping_address returns a WC_Stripe_API_Address.
	 */
	public function test_get_shipping_address_returns_address() {
		$event   = $this->build_event();
		$address = $event->get_shipping_address();

		$this->assertInstanceOf( WC_Stripe_API_Address::class, $address );
		$this->assertSame( 'US', $address->get_country() );
		$this->assertSame( 'CA', $address->get_state() );
	}

	/**
	 * Test get_shipping_address returns null when no shipping_details.
	 */
	public function test_get_shipping_address_null_when_missing() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event( (object) [] );
		$this->assertNull( $event->get_shipping_address() );
	}

	/**
	 * Test get_billing_address returns a WC_Stripe_API_Address from shipping_details.
	 */
	public function test_get_billing_address_returns_address() {
		$event   = $this->build_event();
		$address = $event->get_billing_address();

		$this->assertInstanceOf( WC_Stripe_API_Address::class, $address );
		$this->assertSame( 'US', $address->get_country() );
	}

	/**
	 * Test get_billing_address throws when shipping_details.address is missing.
	 */
	public function test_get_billing_address_throws_when_missing() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event( (object) [] );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'no billing address' );

		$event->get_billing_address();
	}

	/**
	 * Test is_livemode returns true when set.
	 */
	public function test_livemode_true() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [ 'livemode' => true ]
		);

		$this->assertTrue( $event->is_livemode() );
	}

	/**
	 * Test currency is lowercased.
	 */
	public function test_currency_is_lowercased() {
		$event = new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [
				'data' => (object) [ 'currency' => 'EUR' ],
			]
		);

		$this->assertSame( 'eur', $event->get_currency() );
	}

	/**
	 * Builds a standard customize_checkout event.
	 *
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	private function build_event(): WC_Stripe_Agentic_Customize_Checkout_Event {
		return new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [
				'id'       => 'evt_test_123',
				'type'     => 'v1.delegated_checkout.customize_checkout',
				'livemode' => false,
				'data'     => (object) [
					'currency'          => 'usd',
					'automatic_tax'     => (object) [ 'enabled' => false ],
					'line_item_details' => [
						(object) [
							'id'     => 'li_test_0',
							'sku_id' => '42',
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
				],
			]
		);
	}
}
