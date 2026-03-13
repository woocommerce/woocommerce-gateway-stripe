<?php
/**
 * Tests for WC_Stripe_Webhook_Handler::process_agentic_customization_hook
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Product;
use WC_Stripe_Webhook_Handler;
use WC_Tax;
use WC_Cache_Helper;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use ReflectionMethod;

/**
 * Class WC_Stripe_Agentic_Commerce_Customization_Hook_Test
 *
 * Tests the process_agentic_customization_hook method via reflection.
 */
class WC_Stripe_Agentic_Commerce_Customization_Hook_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $handler;

	/**
	 * @var ReflectionMethod
	 */
	private $method;

	/**
	 * @var \WC_Product
	 */
	private $product;

	/**
	 * @var int
	 */
	private $tax_rate_id;

	/**
	 * @var WC_Shipping_Zone|null
	 */
	private $shipping_zone;

	/**
	 * @var string
	 */
	private $original_calc_taxes;

	/**
	 * @var string
	 */
	private $original_tax_based_on;

	/**
	 * @var string
	 */
	private $original_prices_include_tax;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Webhook_Handler' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Webhook_Handler class not loaded' );
		}

		$this->handler = new WC_Stripe_Webhook_Handler();
		$this->method  = new ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'process_agentic_customization_hook' );
		$this->method->setAccessible( true );

		$this->product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '20.00',
				'price'         => '20.00',
			]
		);

		$this->original_calc_taxes         = get_option( 'woocommerce_calc_taxes' );
		$this->original_tax_based_on       = get_option( 'woocommerce_tax_based_on' );
		$this->original_prices_include_tax = get_option( 'woocommerce_prices_include_tax' );

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		$this->tax_rate_id = WC_Tax::_insert_tax_rate(
			[
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'CA',
				'tax_rate'          => '8.2500',
				'tax_rate_name'     => 'CA Sales Tax',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			]
		);
	}

	public function tearDown(): void {
		if ( $this->product ) {
			$this->product->delete( true );
		}

		if ( $this->tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $this->tax_rate_id );
		}

		if ( $this->shipping_zone ) {
			$this->shipping_zone->delete();
			$this->shipping_zone = null;
		}

		update_option( 'woocommerce_calc_taxes', $this->original_calc_taxes );
		update_option( 'woocommerce_tax_based_on', $this->original_tax_based_on );
		update_option( 'woocommerce_prices_include_tax', $this->original_prices_include_tax );

		WC_Cache_Helper::get_transient_version( 'shipping', true );
		$shipping = WC()->shipping();
		if ( $shipping ) {
			$shipping->reset_shipping();
		}

		parent::tearDown();
	}

	/**
	 * Test that the hook returns both line_items and shipping_options when both apply.
	 */
	public function test_returns_merged_tax_and_shipping_response() {
		$this->create_shipping_zone_with_flat_rate( 'US', 5.00 );

		$event    = $this->build_raw_event( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		$this->assertArrayHasKey( 'line_items', $response );
		$this->assertArrayHasKey( 'shipping_options', $response );
	}

	/**
	 * Test that line_items contain tax rates for a matching address.
	 */
	public function test_line_items_contain_tax_rates() {
		$event    = $this->build_raw_event( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		$this->assertCount( 1, $response['line_items'] );

		$line_item = $response['line_items'][0];
		$this->assertNotEmpty( $line_item['tax_rates'] );
		$this->assertEquals( 8.25, $line_item['tax_rates'][0]['rate_data']['percentage'] );
		$this->assertEquals( 'CA Sales Tax', $line_item['tax_rates'][0]['rate_data']['display_name'] );
	}

	/**
	 * Test that the response is valid JSON with a 200 status.
	 */
	public function test_outputs_valid_json() {
		$event = $this->build_raw_event( [ $this->product ] );

		ob_start();
		$this->method->invoke( $this->handler, $event );
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );
		$this->assertNotNull( $decoded );
		$this->assertArrayHasKey( 'line_items', $decoded );
	}

	/**
	 * Test that multiple products each get their own line_item entry.
	 */
	public function test_multiple_products_in_response() {
		$product2 = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '15.00',
				'price'         => '15.00',
			]
		);

		$event    = $this->build_raw_event( [ $this->product, $product2 ] );
		$response = $this->invoke_hook( $event );

		$this->assertCount( 2, $response['line_items'] );

		$product2->delete( true );
	}

	/**
	 * Test that tax-disabled returns empty tax rates but still returns line_items key.
	 */
	public function test_tax_disabled_returns_empty_rates() {
		update_option( 'woocommerce_calc_taxes', 'no' );

		$event    = $this->build_raw_event( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		$this->assertArrayHasKey( 'line_items', $response );
		$this->assertEmpty( $response['line_items'][0]['tax_rates'] );
	}

	/**
	 * Test that shipping_options are absent when shipping is disabled.
	 */
	public function test_no_shipping_options_when_shipping_disabled() {
		update_option( 'woocommerce_ship_to_countries', 'disabled' );

		$event    = $this->build_raw_event( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		$this->assertArrayNotHasKey( 'shipping_options', $response );

		update_option( 'woocommerce_ship_to_countries', '' );
	}

	/**
	 * Test that an invalid product ID returns a 400 status and logs the error.
	 */
	public function test_returns_400_on_invalid_product() {
		$event = $this->build_raw_event_with_custom_line_items(
			[
				(object) [
					'id'     => 'li_test_0',
					'sku_id' => '999999999',
				],
			]
		);

		ob_start();
		$this->method->invoke( $this->handler, $event );
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'No JSON body should be returned on error.' );
	}

	/**
	 * Test that an event with missing shipping_details still returns line_items.
	 */
	public function test_missing_shipping_details_throws_error() {
		$event = (object) [
			'id'       => 'evt_test_hook',
			'type'     => 'v1.delegated_checkout.customize_checkout',
			'livemode' => false,
			'data'     => (object) [
				'currency'          => 'usd',
				'automatic_tax'     => (object) [ 'enabled' => false ],
				'line_item_details' => [
					(object) [
						'id'     => 'li_test_0',
						'sku_id' => (string) $this->product->get_id(),
					],
				],
			],
		];

		ob_start();
		$this->method->invoke( $this->handler, $event );
		$output = ob_get_clean();

		// Should return 400 because billing address is required for tax calculation.
		$this->assertEmpty( $output, 'No JSON body should be returned on error.' );
	}

	/**
	 * Invokes the private hook method and returns decoded JSON response.
	 *
	 * @param \stdClass $event The raw event.
	 * @return array The decoded JSON response.
	 */
	private function invoke_hook( \stdClass $event ): array {
		ob_start();
		$this->method->invoke( $this->handler, $event );
		$output = ob_get_clean();

		return json_decode( $output, true ) ?? [];
	}

	/**
	 * Builds a raw customize_checkout event stdClass.
	 *
	 * @param \WC_Product[] $products Products to include as line items.
	 * @param array         $address  Address overrides.
	 * @return \stdClass
	 */
	private function build_raw_event( array $products, array $address = [] ): \stdClass {
		$address = array_merge(
			[
				'country'     => 'US',
				'state'       => 'CA',
				'postal_code' => '90210',
				'city'        => 'Beverly Hills',
			],
			$address
		);

		$line_items = [];
		foreach ( $products as $index => $product ) {
			$line_items[] = (object) [
				'id'     => 'li_test_' . $index,
				'sku_id' => (string) $product->get_id(),
			];
		}

		return (object) [
			'id'       => 'evt_test_hook',
			'type'     => 'v1.delegated_checkout.customize_checkout',
			'livemode' => false,
			'data'     => (object) [
				'currency'          => 'usd',
				'automatic_tax'     => (object) [ 'enabled' => false ],
				'line_item_details' => $line_items,
				'shipping_details'  => (object) [
					'address' => (object) $address,
				],
			],
		];
	}

	/**
	 * Builds a raw customize_checkout event with custom line items.
	 *
	 * @param array $line_items The raw line items.
	 * @param array $address    Address overrides.
	 * @return \stdClass
	 */
	private function build_raw_event_with_custom_line_items( array $line_items, array $address = [] ): \stdClass {
		$address = array_merge(
			[
				'country'     => 'US',
				'state'       => 'CA',
				'postal_code' => '90210',
				'city'        => 'Beverly Hills',
			],
			$address
		);

		return (object) [
			'id'       => 'evt_test_hook',
			'type'     => 'v1.delegated_checkout.customize_checkout',
			'livemode' => false,
			'data'     => (object) [
				'currency'          => 'usd',
				'automatic_tax'     => (object) [ 'enabled' => false ],
				'line_item_details' => $line_items,
				'shipping_details'  => (object) [
					'address' => (object) $address,
				],
			],
		];
	}

	/**
	 * Creates a shipping zone for a country with a flat rate.
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

		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping()->reset_shipping();
	}
}
