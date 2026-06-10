<?php
/**
 * Tests for WC_Stripe_Agentic_Shipping_Package_Builder
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Stripe_API_Address;
use WC_Stripe_Agentic_Shipping_Package_Builder;

/**
 * Class WC_Stripe_Agentic_Shipping_Package_Builder_Test
 */
class WC_Stripe_Agentic_Shipping_Package_Builder_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Shipping_Package_Builder' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Shipping_Package_Builder class not loaded' );
		}
	}

	/**
	 * Builds the default US/CA destination address wrapper.
	 *
	 * @return WC_Stripe_API_Address
	 */
	private function build_address(): WC_Stripe_API_Address {
		return new WC_Stripe_API_Address(
			(object) [
				'country'     => 'US',
				'state'       => 'CA',
				'postal_code' => '90210',
				'city'        => 'Beverly Hills',
			]
		);
	}

	/**
	 * Test that build_package derives contents_cost from the entries' line
	 * totals and maps the destination address into WC's package format.
	 */
	public function test_build_package_sums_contents_cost_and_maps_destination() {
		$contents = [
			'a' => [ 'line_total' => 10.50 ],
			'b' => [ 'line_total' => 4.25 ],
		];

		$package = WC_Stripe_Agentic_Shipping_Package_Builder::build_package( $contents, $this->build_address(), 7 );

		$this->assertSame( $contents, $package['contents'] );
		$this->assertSame( 14.75, $package['contents_cost'] );
		$this->assertSame( 14.75, $package['cart_subtotal'] );
		$this->assertSame( [ 'ID' => 7 ], $package['user'] );
		$this->assertSame( 'US', $package['destination']['country'] );
		$this->assertSame( 'CA', $package['destination']['state'] );
		$this->assertSame( '90210', $package['destination']['postcode'] );
		$this->assertSame( 'Beverly Hills', $package['destination']['city'] );
	}

	/**
	 * Test that build_contents_from_order keeps only shippable products and
	 * builds cart-item-format entries with product data, quantity, and totals.
	 */
	public function test_build_contents_from_order_filters_non_shippable_items() {
		$shippable = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
				'sku'           => 'PKG-PHYS-' . uniqid(),
			]
		);
		$virtual   = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '5.00',
				'price'         => '5.00',
				'sku'           => 'PKG-VIRT-' . uniqid(),
				'virtual'       => true,
			]
		);

		$order = wc_create_order();
		$order->add_product( $shippable, 3 );
		$order->add_product( $virtual, 2 );
		$order->save();

		$contents = WC_Stripe_Agentic_Shipping_Package_Builder::build_contents_from_order( $order );

		$this->assertCount( 1, $contents );

		$entry = reset( $contents );
		$this->assertSame( $shippable->get_id(), $entry['product_id'] );
		$this->assertSame( 0, $entry['variation_id'] );
		$this->assertSame( 3, $entry['quantity'] );
		$this->assertSame( $shippable->get_id(), $entry['data']->get_id() );
		$this->assertSame( 30.0, $entry['line_total'] );
		$this->assertSame( 30.0, $entry['line_subtotal'] );

		$order->delete( true );
		$shippable->delete( true );
		$virtual->delete( true );
	}

	/**
	 * Test that variation entries split parent/variation IDs like WC core cart
	 * items (parent ID in product_id, variation ID in variation_id).
	 */
	public function test_build_contents_from_order_splits_variation_ids() {
		$variable  = WC_Helper_Product::create_variation_product();
		$variation = wc_get_product( $variable->get_children()[0] );

		$order = wc_create_order();
		$order->add_product( $variation, 2 );
		$order->save();

		$contents = WC_Stripe_Agentic_Shipping_Package_Builder::build_contents_from_order( $order );

		$this->assertCount( 1, $contents );

		$entry = reset( $contents );
		$this->assertSame( $variable->get_id(), $entry['product_id'] );
		$this->assertSame( $variation->get_id(), $entry['variation_id'] );
		$this->assertSame( 2, $entry['quantity'] );

		$order->delete( true );
		$variable->delete( true );
	}
}
