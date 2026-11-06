<?php
/**
 * Tests for WC_Stripe_Agentic_Shipping_Package_Builder
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Shipping_Rate;
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

	public function tearDown(): void {
		remove_all_filters( 'wc_stripe_agentic_shipping_packages' );
		parent::tearDown();
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

		try {
			$this->assertCount( 1, $contents );

			$entry = reset( $contents );
			$this->assertSame( $shippable->get_id(), $entry['product_id'] );
			$this->assertSame( 0, $entry['variation_id'] );
			$this->assertSame( 3, $entry['quantity'] );
			$this->assertSame( $shippable->get_id(), $entry['data']->get_id() );
			$this->assertSame( 30.0, $entry['line_total'] );
			$this->assertSame( 30.0, $entry['line_subtotal'] );
		} finally {
			$order->delete( true );
			$shippable->delete( true );
			$virtual->delete( true );
		}
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

		try {
			$this->assertCount( 1, $contents );

			$entry = reset( $contents );
			$this->assertSame( $variable->get_id(), $entry['product_id'] );
			$this->assertSame( $variation->get_id(), $entry['variation_id'] );
			$this->assertSame( 2, $entry['quantity'] );
		} finally {
			$order->delete( true );
			$variation->delete( true );
			$variable->delete( true );
		}
	}

	/**
	 * Test that get_filtered_packages wraps the package unchanged when no
	 * callback is attached, and drops non-array entries returned by callbacks.
	 */
	public function test_get_filtered_packages_wraps_package_and_drops_invalid_entries() {
		$package = WC_Stripe_Agentic_Shipping_Package_Builder::build_package( [], $this->build_address(), 0 );

		$this->assertSame( [ $package ], WC_Stripe_Agentic_Shipping_Package_Builder::get_filtered_packages( $package ) );

		add_filter(
			'wc_stripe_agentic_shipping_packages',
			function ( $packages ) {
				return [ $packages[0], 'not-a-package', null, $packages[0] ];
			}
		);

		$filtered = WC_Stripe_Agentic_Shipping_Package_Builder::get_filtered_packages( $package );

		$this->assertSame( [ $package, $package ], $filtered );
	}

	/**
	 * Test that combine_package_rates returns a single package's rates as-is.
	 */
	public function test_combine_package_rates_passes_through_single_package() {
		$rate  = new WC_Shipping_Rate( 'flat_rate:1', 'Flat rate', 5.00, [], 'flat_rate', 1 );
		$rates = [ 'flat_rate:1' => $rate ];

		$this->assertSame( [], WC_Stripe_Agentic_Shipping_Package_Builder::combine_package_rates( [] ) );
		$this->assertSame( $rates, WC_Stripe_Agentic_Shipping_Package_Builder::combine_package_rates( [ [ 'rates' => $rates ] ] ) );
	}

	/**
	 * Test that combine_package_rates keeps only rates available for every
	 * package, sums their costs across packages, and leaves the original rate
	 * objects unmodified.
	 */
	public function test_combine_package_rates_intersects_by_rate_id_and_sums_costs() {
		$flat_a   = new WC_Shipping_Rate( 'flat_rate:1', 'Flat rate', 5.00, [], 'flat_rate', 1 );
		$pickup_a = new WC_Shipping_Rate( 'local_pickup:2', 'Local pickup', 0.00, [], 'local_pickup', 2 );
		$flat_b   = new WC_Shipping_Rate( 'flat_rate:1', 'Flat rate', 7.50, [], 'flat_rate', 1 );

		$combined = WC_Stripe_Agentic_Shipping_Package_Builder::combine_package_rates(
			[
				[
					'rates' => [
						'flat_rate:1'    => $flat_a,
						'local_pickup:2' => $pickup_a,
					],
				],
				[ 'rates' => [ 'flat_rate:1' => $flat_b ] ],
			]
		);

		$this->assertSame( [ 'flat_rate:1' ], array_keys( $combined ) );
		$this->assertSame( 12.5, (float) $combined['flat_rate:1']->get_cost() );
		$this->assertSame( 'Flat rate', $combined['flat_rate:1']->get_label() );

		// Originals must not be mutated: the combined rate is a clone.
		$this->assertSame( 5.0, (float) $flat_a->get_cost() );
		$this->assertSame( 7.5, (float) $flat_b->get_cost() );
	}
}
