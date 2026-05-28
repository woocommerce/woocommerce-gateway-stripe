<?php
/**
 * Shared helpers for Agentic Commerce tests.
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WC_Cache_Helper;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Stripe_Agentic_Customize_Checkout_Event;
use WC_Tax;

/**
 * Trait Trait_Agentic_Commerce_Test_Helpers
 *
 * Provides reusable helpers for building customize_checkout events,
 * managing WC tax/shipping options, and creating shipping zones.
 */
trait Trait_Agentic_Commerce_Test_Helpers {

	/**
	 * Saved WooCommerce options, keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved_wc_options = [];

	/**
	 * Default US/CA address used across tests.
	 *
	 * @return array
	 */
	protected function default_address(): array {
		return [
			'country'     => 'US',
			'state'       => 'CA',
			'postal_code' => '90210',
			'city'        => 'Beverly Hills',
		];
	}

	/**
	 * Builds a raw stdClass customize_checkout event.
	 *
	 * @param array $line_items    Raw line item objects or arrays.
	 * @param array $address       Address overrides (merged with default_address()).
	 * @param bool  $automatic_tax Whether Stripe automatic tax is enabled.
	 * @return \stdClass
	 */
	protected function build_raw_event( array $line_items = [], array $address = [], bool $automatic_tax = false ): \stdClass {
		$address = array_merge( $this->default_address(), $address );

		return (object) [
			'id'       => 'evt_test_hook',
			'type'     => 'v1.delegated_checkout.customize_checkout',
			'livemode' => false,
			'data'     => (object) [
				'currency'          => 'usd',
				'automatic_tax'     => (object) [ 'enabled' => $automatic_tax ],
				'line_item_details' => $line_items,
				'shipping_details'  => (object) [
					'address' => (object) $address,
				],
			],
		];
	}

	/**
	 * Builds a typed WC_Stripe_Agentic_Customize_Checkout_Event from products.
	 *
	 * @param \WC_Product[] $products      Products to include as line items.
	 * @param array         $address       Address overrides.
	 * @param bool          $automatic_tax Whether Stripe automatic tax is enabled.
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	protected function build_event_from_products( array $products, array $address = [], bool $automatic_tax = false ): WC_Stripe_Agentic_Customize_Checkout_Event {
		$items = [];
		foreach ( $products as $index => $product ) {
			$items[] = (object) [
				'id'     => 'li_test_' . $index,
				'sku_id' => (string) $product->get_sku(),
			];
		}

		return new WC_Stripe_Agentic_Customize_Checkout_Event(
			$this->build_raw_event( $items, $address, $automatic_tax )
		);
	}

	/**
	 * Builds a typed event from raw line item arrays.
	 *
	 * @param array $raw_items     Arrays with 'id' and 'sku_id' keys.
	 * @param array $address       Address overrides.
	 * @param bool  $automatic_tax Whether Stripe automatic tax is enabled.
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	protected function build_event_from_raw_items( array $raw_items, array $address = [], bool $automatic_tax = false ): WC_Stripe_Agentic_Customize_Checkout_Event {
		$items = array_map( fn( $item ) => (object) $item, $raw_items );

		return new WC_Stripe_Agentic_Customize_Checkout_Event(
			$this->build_raw_event( $items, $address, $automatic_tax )
		);
	}

	/**
	 * Saves the current values of the given WooCommerce options for later restoration.
	 *
	 * @param string ...$option_names Option names to save.
	 */
	protected function save_wc_options( string ...$option_names ): void {
		foreach ( $option_names as $name ) {
			$this->saved_wc_options[ $name ] = get_option( $name );
		}
	}

	/**
	 * Restores all previously saved WooCommerce options.
	 */
	protected function restore_wc_options(): void {
		foreach ( $this->saved_wc_options as $name => $value ) {
			update_option( $name, $value );
		}
		$this->saved_wc_options = [];
	}

	/**
	 * Creates a shipping zone for a country with a flat rate method.
	 *
	 * @param string $country Country code.
	 * @param float  $cost    Flat rate cost.
	 * @return WC_Shipping_Zone The created zone (caller is responsible for cleanup).
	 */
	protected function create_shipping_zone_with_flat_rate( string $country, float $cost ): WC_Shipping_Zone {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( $country . ' Shipping' );
		$zone->set_zone_order( 1 );
		$zone->save();

		$zone->add_location( $country, 'country' );
		$zone->save();

		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$method      = WC_Shipping_Zones::get_shipping_method( $instance_id );

		update_option(
			$method->get_instance_option_key(),
			[
				'title' => $country . ' Flat Rate',
				'cost'  => (string) $cost,
			]
		);

		$this->reset_shipping_cache();

		return $zone;
	}

	/**
	 * Resets WC shipping caches so zones and methods are re-read from the DB.
	 */
	protected function reset_shipping_cache(): void {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		$shipping = WC()->shipping();
		if ( $shipping ) {
			$shipping->reset_shipping();
		}
	}

	/**
	 * Creates a WC tax rate and returns its ID.
	 *
	 * @param string $country   Country code.
	 * @param string $state     State code.
	 * @param string $rate      Tax rate percentage (e.g. '10.0000').
	 * @param string $name      Tax rate label.
	 * @param string $tax_class Tax class (empty string for standard).
	 * @return int The tax rate ID.
	 */
	protected function create_tax_rate(
		string $country = 'US',
		string $state = 'CA',
		string $rate = '10.0000',
		string $name = 'Tax',
		string $tax_class = ''
	): int {
		return WC_Tax::_insert_tax_rate(
			[
				'tax_rate_country'  => $country,
				'tax_rate_state'    => $state,
				'tax_rate'          => $rate,
				'tax_rate_name'     => $name,
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => $tax_class,
			]
		);
	}
}
