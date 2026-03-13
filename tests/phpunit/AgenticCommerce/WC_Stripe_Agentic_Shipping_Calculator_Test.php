<?php
/**
 * Tests for WC_Stripe_Agentic_Shipping_Calculator
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Cache_Helper;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Stripe_Agentic_Shipping_Calculator;
use WC_Stripe_Agentic_Customize_Checkout_Event;

/**
 * Class WC_Stripe_Agentic_Shipping_Calculator_Test
 */
class WC_Stripe_Agentic_Shipping_Calculator_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Agentic_Shipping_Calculator
	 */
	private $calculator;

	/**
	 * @var string Original woocommerce_ship_to_countries option.
	 */
	private $original_ship_to_countries;

	/**
	 * @var string Original woocommerce_calc_taxes option.
	 */
	private $original_calc_taxes;

	/**
	 * @var WC_Shipping_Zone|null
	 */
	private $shipping_zone;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Shipping_Calculator' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Shipping_Calculator class not loaded' );
		}

		$this->calculator                 = new WC_Stripe_Agentic_Shipping_Calculator();
		$this->original_ship_to_countries = get_option( 'woocommerce_ship_to_countries' );
		$this->original_calc_taxes        = get_option( 'woocommerce_calc_taxes' );

		$this->reset_shipping_cache();
	}

	public function tearDown(): void {
		if ( $this->shipping_zone ) {
			$this->shipping_zone->delete();
			$this->shipping_zone = null;
		}

		update_option( 'woocommerce_ship_to_countries', $this->original_ship_to_countries );
		update_option( 'woocommerce_calc_taxes', $this->original_calc_taxes );

		$this->reset_shipping_cache();

		parent::tearDown();
	}

	/**
	 * Test that shipping disabled returns empty array.
	 */
	public function test_returns_empty_when_shipping_disabled() {
		update_option( 'woocommerce_ship_to_countries', 'disabled' );

		$event  = $this->build_event();
		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertEmpty( $result );
	}

	/**
	 * Test that a matching zone with flat rate returns correctly structured shipping options.
	 */
	public function test_returns_shipping_options_for_matching_zone() {
		$this->create_shipping_zone_with_flat_rate( 'US', 5.00 );

		$event  = $this->build_event();
		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertArrayHasKey( 'shipping_options', $result );
		$this->assertNotEmpty( $result['shipping_options'] );

		$option = $result['shipping_options'][0];
		$this->assertArrayHasKey( 'shipping_rate_data', $option );
		$this->assertEquals( 'inclusive', $option['shipping_rate_data']['tax_behavior'] );
		$this->assertEquals( 'usd', $option['shipping_rate_data']['fixed_amount']['currency'] );
		$this->assertArrayHasKey( 'wc_rate_id', $option['shipping_rate_data']['metadata'] );
	}

	/**
	 * Test that fixed_amount is an integer (Stripe cents format) and currency is lowercase.
	 */
	public function test_amount_is_in_stripe_format() {
		$this->create_shipping_zone_with_flat_rate( 'US', 5.00 );

		$event  = $this->build_event();
		$result = $this->calculator->calculate( $event, 'USD' );

		$this->assertArrayHasKey( 'shipping_options', $result );

		$fixed_amount = $result['shipping_options'][0]['shipping_rate_data']['fixed_amount'];
		$this->assertIsInt( $fixed_amount['amount'] );
		$this->assertGreaterThan( 0, $fixed_amount['amount'] );
		$this->assertEquals( 'usd', $fixed_amount['currency'] );
	}

	/**
	 * Test that a non-matching country returns empty or does not include the wrong zone.
	 */
	public function test_returns_empty_for_non_matching_zone() {
		$this->create_shipping_zone_with_flat_rate( 'DE', 5.00 );

		$event  = $this->build_event(); // US address.
		$result = $this->calculator->calculate( $event, 'usd' );

		// The rest-of-world zone (zone 0) may or may not have methods,
		// but it must not include the DE-specific rate.
		if ( ! empty( $result ) && isset( $result['shipping_options'] ) ) {
			foreach ( $result['shipping_options'] as $option ) {
				$this->assertNotEquals( 'DE Flat Rate', $option['shipping_rate_data']['display_name'] );
			}
		} else {
			$this->assertEmpty( $result );
		}
	}

	/**
	 * Test that the display_name comes from the shipping method label.
	 */
	public function test_display_name_from_method_label() {
		$this->create_shipping_zone_with_flat_rate( 'US', 7.50 );

		$event  = $this->build_event();
		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertArrayHasKey( 'shipping_options', $result );
		$this->assertNotEmpty( $result['shipping_options'][0]['shipping_rate_data']['display_name'] );
	}

	/**
	 * Resets WC shipping caches so zones and methods are re-read from the DB.
	 */
	private function reset_shipping_cache(): void {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		$shipping = WC()->shipping();
		if ( $shipping ) {
			$shipping->reset_shipping();
		}
	}

	/**
	 * Creates a shipping zone for a country with a flat rate method.
	 *
	 * @param string $country Country code.
	 * @param float  $cost    Flat rate cost.
	 */
	private function create_shipping_zone_with_flat_rate( string $country, float $cost ): void {
		$this->shipping_zone = new WC_Shipping_Zone();
		$this->shipping_zone->set_zone_name( $country . ' Shipping' );
		$this->shipping_zone->set_zone_order( 1 );
		$this->shipping_zone->save();

		$this->shipping_zone->add_location( $country, 'country' );

		$instance_id = $this->shipping_zone->add_shipping_method( 'flat_rate' );
		$method      = WC_Shipping_Zones::get_shipping_method( $instance_id );
		$option_key  = $method->get_instance_option_key();

		update_option(
			$option_key,
			[
				'title' => $country . ' Flat Rate',
				'cost'  => (string) $cost,
			]
		);

		$this->reset_shipping_cache();
	}

	/**
	 * Builds a customize_checkout event with a US address.
	 *
	 * @param array $address_overrides Address field overrides.
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	private function build_event( array $address_overrides = [] ): WC_Stripe_Agentic_Customize_Checkout_Event {
		$address = array_merge(
			[
				'country'     => 'US',
				'state'       => 'CA',
				'postal_code' => '90210',
				'city'        => 'Beverly Hills',
			],
			$address_overrides
		);

		return new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [
				'id'       => 'evt_test_shipping',
				'type'     => 'v1.delegated_checkout.customize_checkout',
				'livemode' => false,
				'data'     => (object) [
					'currency'          => 'usd',
					'automatic_tax'     => (object) [ 'enabled' => false ],
					'line_item_details' => [],
					'shipping_details'  => (object) [
						'address' => (object) $address,
					],
				],
			]
		);
	}
}
