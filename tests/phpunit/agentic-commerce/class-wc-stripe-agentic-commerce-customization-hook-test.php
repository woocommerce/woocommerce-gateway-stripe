<?php
/**
 * Tests for WC_Stripe_Webhook_Handler::process_agentic_customization_hook
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

require_once __DIR__ . '/trait-agentic-commerce-test-helpers.php';

use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Stripe_Webhook_Handler;
use WC_Tax;
use ReflectionMethod;

/**
 * Class WC_Stripe_Agentic_Commerce_Customization_Hook_Test
 *
 * Tests the process_agentic_customization_hook method via reflection.
 */
class WC_Stripe_Agentic_Commerce_Customization_Hook_Test extends WP_UnitTestCase {

	use Trait_Agentic_Commerce_Test_Helpers;

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
	 * @var \WC_Shipping_Zone|null
	 */
	private $shipping_zone;

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
				'sku'           => 'HOOK-TEST-MAIN',
			]
		);

		$this->save_wc_options(
			'woocommerce_calc_taxes',
			'woocommerce_tax_based_on',
			'woocommerce_prices_include_tax',
			'woocommerce_ship_to_countries'
		);

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		$this->tax_rate_id = $this->create_tax_rate( 'US', 'CA', '8.2500', 'CA Sales Tax' );
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

		$this->restore_wc_options();
		$this->reset_shipping_cache();

		parent::tearDown();
	}

	/**
	 * Test that the hook returns both line_items (with tax rates) and shipping_options when both apply.
	 */
	public function test_returns_merged_tax_and_shipping_response() {
		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', 5.00 );

		$event    = $this->build_raw_event_from_products( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		$this->assertArrayHasKey( 'line_items', $response );
		$this->assertArrayHasKey( 'shipping_options', $response );

		// Also verify the line item carries the expected tax rate (covers test_line_items_contain_tax_rates).
		$this->assertCount( 1, $response['line_items'] );
		$line_item = $response['line_items'][0];
		$this->assertNotEmpty( $line_item['tax_rates'] );
		$this->assertEquals( 8.25, $line_item['tax_rates'][0]['rate_data']['percentage'] );
		$this->assertEquals( 'CA Sales Tax', $line_item['tax_rates'][0]['rate_data']['display_name'] );
	}

	/**
	 * Test that the response is a valid array with line_items.
	 */
	public function test_returns_valid_array() {
		$event    = $this->build_raw_event_from_products( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'line_items', $response );
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

		try {
			$event    = $this->build_raw_event_from_products( [ $this->product, $product2 ] );
			$response = $this->invoke_hook( $event );

			$this->assertCount( 2, $response['line_items'] );
		} finally {
			$product2->delete( true );
		}
	}

	/**
	 * Data provider for disabled-feature response tests.
	 *
	 * @return array
	 */
	public function disabled_feature_provider(): array {
		return [
			'tax disabled returns empty rates'         => [
				'option'        => 'woocommerce_calc_taxes',
				'value'         => 'no',
				'assert_key'    => 'line_items',
				'assert_method' => 'assertEmpty_tax_rates',
			],
			'shipping disabled omits shipping_options' => [
				'option'        => 'woocommerce_ship_to_countries',
				'value'         => 'disabled',
				'assert_key'    => 'shipping_options',
				'assert_method' => 'assertArrayNotHasKey',
			],
		];
	}

	/**
	 * Test that disabling tax or shipping produces the expected omission in the response.
	 *
	 * @dataProvider disabled_feature_provider
	 */
	public function test_disabled_feature_response( string $option, string $value, string $assert_key, string $assert_method ): void {
		update_option( $option, $value );

		$event    = $this->build_raw_event_from_products( [ $this->product ] );
		$response = $this->invoke_hook( $event );

		if ( 'assertEmpty_tax_rates' === $assert_method ) {
			$this->assertArrayHasKey( 'line_items', $response );
			$this->assertEquals( [], $response['line_items'][0]['tax_rates'] );
		} else {
			$this->assertArrayNotHasKey( $assert_key, $response );
		}
	}

	/**
	 * Test that automatic_tax enabled returns empty tax rates even when WC taxes are on.
	 */
	public function test_automatic_tax_returns_empty_rates(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );

		$event    = $this->build_raw_event_from_products( [ $this->product ], [], true );
		$response = $this->invoke_hook( $event );

		$this->assertArrayHasKey( 'line_items', $response );
		$this->assertEquals( [], $response['line_items'][0]['tax_rates'] );
	}

	/**
	 * Test that an invalid product ID throws an exception.
	 */
	public function test_throws_on_invalid_product() {
		$event = $this->build_raw_event(
			[
				(object) [
					'id'     => 'li_test_0',
					'sku_id' => '999999999',
				],
			]
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Product not found' );

		$this->method->invoke( $this->handler, $event );
	}

	/**
	 * Test that a missing billing address throws an exception.
	 */
	public function test_throws_on_missing_shipping_details() {
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
						'sku_id' => (string) $this->product->get_sku(),
					],
				],
			],
		];

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'no billing address' );

		$this->method->invoke( $this->handler, $event );
	}

	/**
	 * Builds a raw customize_checkout event stdClass from WC_Product objects.
	 *
	 * Converts each product into a line item and delegates to the trait's build_raw_event().
	 *
	 * @param \WC_Product[] $products      Products to include as line items.
	 * @param array         $address       Address overrides.
	 * @param bool          $automatic_tax Whether Stripe automatic tax is enabled.
	 * @return \stdClass
	 */
	private function build_raw_event_from_products( array $products, array $address = [], bool $automatic_tax = false ): \stdClass {
		$items = [];
		foreach ( $products as $index => $product ) {
			$items[] = (object) [
				'id'     => 'li_test_' . $index,
				'sku_id' => (string) $product->get_sku(),
			];
		}

		return $this->build_raw_event( $items, $address, $automatic_tax );
	}

	/**
	 * Invokes the private hook method and returns decoded JSON response.
	 *
	 * @param \stdClass $event The raw event.
	 * @return array The response array.
	 */
	private function invoke_hook( \stdClass $event ): array {
		return $this->method->invoke( $this->handler, $event );
	}
}
