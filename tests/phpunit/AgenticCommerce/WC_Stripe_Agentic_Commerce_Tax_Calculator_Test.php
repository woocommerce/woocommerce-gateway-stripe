<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Tax_Calculator
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Product;
use WC_Stripe_Agentic_Commerce_Tax_Calculator;
use WC_Stripe_Agentic_Customize_Checkout_Event;
use WC_Tax;

/**
 * Class WC_Stripe_Agentic_Commerce_Tax_Calculator_Test
 *
 * Tests the tax calculator for agentic commerce customize_checkout events.
 *
 * @covers WC_Stripe_Agentic_Commerce_Tax_Calculator
 */
class WC_Stripe_Agentic_Commerce_Tax_Calculator_Test extends WP_UnitTestCase {

	/**
	 * The calculator instance under test.
	 *
	 * @var WC_Stripe_Agentic_Commerce_Tax_Calculator
	 */
	private $calculator;

	/**
	 * Tax rate IDs created during tests (for cleanup).
	 *
	 * @var int[]
	 */
	private $tax_rate_ids = [];

	/**
	 * Products created during tests (for cleanup).
	 *
	 * @var \WC_Product[]
	 */
	private $products = [];

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Tax_Calculator' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Tax_Calculator class not loaded' );
		}

		$this->calculator = new WC_Stripe_Agentic_Commerce_Tax_Calculator();

		// Enable taxes in WooCommerce.
		update_option( 'woocommerce_calc_taxes', 'yes' );
	}

	/**
	 * Cleanup after each test.
	 */
	public function tearDown(): void {
		foreach ( $this->tax_rate_ids as $rate_id ) {
			WC_Tax::_delete_tax_rate( $rate_id );
		}

		foreach ( $this->products as $product ) {
			$product->delete( true );
		}

		$this->tax_rate_ids = [];
		$this->products     = [];

		parent::tearDown();
	}

	/**
	 * Test tax calculation for a single product.
	 */
	public function test_single_product_tax_calculation() {
		$rate_id = $this->create_tax_rate(
			[
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'CA',
				'tax_rate'          => '7.2500',
				'tax_rate_name'     => 'CA State Tax',
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 1,
			]
		);

		$product = $this->create_product( 'SKU-SINGLE' );

		$event    = $this->build_event(
			[
				$this->build_line_item( 'li_1', 'SKU-SINGLE' ),
			]
		);
		$response = $this->calculator->calculate( $event );

		$this->assertArrayHasKey( 'line_items', $response );
		$this->assertCount( 1, $response['line_items'] );
		$this->assertSame( 'li_1', $response['line_items'][0]['id'] );
		$this->assertCount( 1, $response['line_items'][0]['tax_rates'] );

		$rate_data = $response['line_items'][0]['tax_rates'][0]['rate_data'];
		$this->assertSame( 'CA State Tax', $rate_data['display_name'] );
		$this->assertSame( 7.25, $rate_data['percentage'] );
		$this->assertIsBool( $rate_data['inclusive'] );
	}

	/**
	 * Test tax calculation for multiple products with different tax classes.
	 */
	public function test_multiple_products_different_tax_classes() {
		$this->create_tax_rate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '7.2500',
				'tax_rate_name'    => 'CA State Tax',
				'tax_rate_class'   => '',
			]
		);

		$this->create_tax_rate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '0.0000',
				'tax_rate_name'    => 'Reduced Rate',
				'tax_rate_class'   => 'reduced-rate',
			]
		);

		$product1 = $this->create_product( 'SKU-STANDARD' );
		$product2 = $this->create_product( 'SKU-REDUCED', 'reduced-rate' );

		$event    = $this->build_event(
			[
				$this->build_line_item( 'li_1', 'SKU-STANDARD' ),
				$this->build_line_item( 'li_2', 'SKU-REDUCED' ),
			]
		);
		$response = $this->calculator->calculate( $event );

		$this->assertCount( 2, $response['line_items'] );
		$this->assertSame( 'li_1', $response['line_items'][0]['id'] );
		$this->assertSame( 'li_2', $response['line_items'][1]['id'] );

		// Standard product gets 7.25%.
		$this->assertSame( 7.25, $response['line_items'][0]['tax_rates'][0]['rate_data']['percentage'] );

		// Reduced rate product gets 0%.
		$this->assertSame( 0.0, $response['line_items'][1]['tax_rates'][0]['rate_data']['percentage'] );
	}

	/**
	 * Test that missing product (SKU not found) is skipped.
	 */
	public function test_missing_product_is_skipped() {
		$this->create_tax_rate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '7.2500',
				'tax_rate_name'    => 'CA State Tax',
			]
		);

		$product = $this->create_product( 'SKU-EXISTS' );

		$event    = $this->build_event(
			[
				$this->build_line_item( 'li_1', 'SKU-EXISTS' ),
				$this->build_line_item( 'li_2', 'SKU-MISSING' ),
			]
		);
		$response = $this->calculator->calculate( $event );

		// Only the found product is included.
		$this->assertCount( 1, $response['line_items'] );
		$this->assertSame( 'li_1', $response['line_items'][0]['id'] );
	}

	/**
	 * Test that empty sku_id is skipped.
	 */
	public function test_empty_sku_is_skipped() {
		$event    = $this->build_event(
			[
				$this->build_line_item( 'li_1', '' ),
			]
		);
		$response = $this->calculator->calculate( $event );

		$this->assertCount( 0, $response['line_items'] );
	}

	/**
	 * Test that missing address returns empty response.
	 */
	public function test_missing_address_returns_empty() {
		$event    = $this->build_event(
			[ $this->build_line_item( 'li_1', 'SKU-123' ) ],
			null,
			null
		);
		$response = $this->calculator->calculate( $event );

		$this->assertSame( [], $response );
	}

	/**
	 * Test shipping address fallback to billing.
	 */
	public function test_billing_address_fallback() {
		$this->create_tax_rate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'NY',
				'tax_rate'         => '8.0000',
				'tax_rate_name'    => 'NY State Tax',
			]
		);

		$product = $this->create_product( 'SKU-BILLING' );

		$event    = $this->build_event(
			[ $this->build_line_item( 'li_1', 'SKU-BILLING' ) ],
			null,
			(object) [
				'address' => (object) [
					'country'     => 'US',
					'state'       => 'NY',
					'postal_code' => '10001',
					'city'        => 'New York',
				],
			]
		);
		$response = $this->calculator->calculate( $event );

		$this->assertCount( 1, $response['line_items'] );
		$this->assertSame( 8.0, $response['line_items'][0]['tax_rates'][0]['rate_data']['percentage'] );
		$this->assertSame( 'NY State Tax', $response['line_items'][0]['tax_rates'][0]['rate_data']['display_name'] );
	}

	/**
	 * Test no applicable tax rates returns empty tax_rates array.
	 */
	public function test_no_applicable_tax_rates() {
		// No tax rates configured for GB.
		$product = $this->create_product( 'SKU-NOTAX' );

		$event    = $this->build_event(
			[ $this->build_line_item( 'li_1', 'SKU-NOTAX' ) ],
			(object) [
				'address' => (object) [
					'country'     => 'GB',
					'state'       => '',
					'postal_code' => 'SW1A 1AA',
					'city'        => 'London',
				],
			]
		);
		$response = $this->calculator->calculate( $event );

		$this->assertCount( 1, $response['line_items'] );
		$this->assertSame( [], $response['line_items'][0]['tax_rates'] );
	}

	/**
	 * Test inclusive flag reflects wc_prices_include_tax setting.
	 *
	 * @dataProvider data_provider_inclusive_flag
	 *
	 * @param string $tax_display_shop The WooCommerce tax display setting.
	 * @param bool   $expected_inclusive The expected inclusive flag value.
	 */
	public function test_inclusive_flag( string $tax_display_shop, bool $expected_inclusive ) {
		update_option( 'woocommerce_prices_include_tax', $tax_display_shop );

		$this->create_tax_rate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '7.2500',
				'tax_rate_name'    => 'Tax',
			]
		);

		$product  = $this->create_product( 'SKU-INCL' );
		$event    = $this->build_event( [ $this->build_line_item( 'li_1', 'SKU-INCL' ) ] );
		$response = $this->calculator->calculate( $event );

		$this->assertSame(
			$expected_inclusive,
			$response['line_items'][0]['tax_rates'][0]['rate_data']['inclusive']
		);
	}

	/**
	 * Data provider for inclusive flag tests.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function data_provider_inclusive_flag(): array {
		return [
			'prices include tax' => [ 'yes', true ],
			'prices exclude tax' => [ 'no', false ],
		];
	}

	/**
	 * Test multiple tax rates for same location (e.g. US state + local).
	 */
	public function test_multiple_tax_rates_for_same_location() {
		$this->create_tax_rate(
			[
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'CA',
				'tax_rate'          => '6.0000',
				'tax_rate_name'     => 'State Tax',
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 1,
			]
		);

		$this->create_tax_rate(
			[
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'CA',
				'tax_rate'          => '1.2500',
				'tax_rate_name'     => 'County Tax',
				'tax_rate_priority' => 2,
				'tax_rate_order'    => 2,
			]
		);

		$product  = $this->create_product( 'SKU-MULTI' );
		$event    = $this->build_event( [ $this->build_line_item( 'li_1', 'SKU-MULTI' ) ] );
		$response = $this->calculator->calculate( $event );

		$this->assertCount( 2, $response['line_items'][0]['tax_rates'] );

		$rates = $response['line_items'][0]['tax_rates'];
		$this->assertSame( 'State Tax', $rates[0]['rate_data']['display_name'] );
		$this->assertSame( 6.0, $rates[0]['rate_data']['percentage'] );
		$this->assertSame( 'County Tax', $rates[1]['rate_data']['display_name'] );
		$this->assertSame( 1.25, $rates[1]['rate_data']['percentage'] );
	}

	/**
	 * Test response format matches Stripe spec.
	 */
	public function test_response_format_matches_stripe_spec() {
		$this->create_tax_rate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '7.2500',
				'tax_rate_name'    => 'CA Tax',
			]
		);

		$product  = $this->create_product( 'SKU-FORMAT' );
		$event    = $this->build_event( [ $this->build_line_item( 'li_1', 'SKU-FORMAT' ) ] );
		$response = $this->calculator->calculate( $event );

		// Top level has line_items key.
		$this->assertArrayHasKey( 'line_items', $response );

		// Each line item has id and tax_rates.
		$item = $response['line_items'][0];
		$this->assertArrayHasKey( 'id', $item );
		$this->assertArrayHasKey( 'tax_rates', $item );

		// Each tax rate has rate_data with required fields.
		$rate = $item['tax_rates'][0];
		$this->assertArrayHasKey( 'rate_data', $rate );
		$this->assertArrayHasKey( 'display_name', $rate['rate_data'] );
		$this->assertArrayHasKey( 'inclusive', $rate['rate_data'] );
		$this->assertArrayHasKey( 'percentage', $rate['rate_data'] );

		// Types are correct.
		$this->assertIsString( $rate['rate_data']['display_name'] );
		$this->assertIsBool( $rate['rate_data']['inclusive'] );
		$this->assertIsFloat( $rate['rate_data']['percentage'] );
	}

	/**
	 * Creates a WooCommerce tax rate and tracks it for cleanup.
	 *
	 * @param array $args Tax rate arguments.
	 * @return int The created tax rate ID.
	 */
	private function create_tax_rate( array $args ): int {
		$defaults = [
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => '0',
			'tax_rate_name'     => 'Tax',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => '',
		];

		$rate_id              = WC_Tax::_insert_tax_rate( array_merge( $defaults, $args ) );
		$this->tax_rate_ids[] = $rate_id;

		return $rate_id;
	}

	/**
	 * Creates a simple WooCommerce product with a SKU.
	 *
	 * @param string $sku       The product SKU.
	 * @param string $tax_class The tax class (empty string for standard).
	 * @return \WC_Product
	 */
	private function create_product( string $sku, string $tax_class = '' ): \WC_Product {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '25.00',
				'price'         => '25.00',
				'sku'           => $sku,
				'tax_class'     => $tax_class,
			]
		);

		$this->products[] = $product;

		return $product;
	}

	/**
	 * Builds a line item object for event construction.
	 *
	 * @param string $id  The line item ID.
	 * @param string $sku The SKU ID.
	 * @return object
	 */
	private function build_line_item( string $id, string $sku ): object {
		return (object) [
			'id'              => $id,
			'sku_id'          => $sku,
			'unit_amount'     => 2500,
			'quantity'        => 1,
			'name'            => 'Test Product',
			'amount_discount' => 0,
			'amount_subtotal' => 2500,
			'amount_tax'      => 0,
			'amount_total'    => 2500,
			'tax_rates'       => [],
		];
	}

	/**
	 * Builds a customize_checkout event for testing.
	 *
	 * @param array       $line_items       Line item objects.
	 * @param object|null $shipping_details Shipping details (null to omit).
	 * @param object|null $billing_details  Billing details (null to omit).
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	private function build_event(
		array $line_items = [],
		?object $shipping_details = null,
		?object $billing_details = null
	): WC_Stripe_Agentic_Customize_Checkout_Event {
		// Use sentinel to distinguish "not passed" from "explicitly null".
		$use_default_shipping = ( 0 === func_num_args() || func_num_args() < 2 );
		$use_default_billing  = ( func_num_args() < 3 );

		$data = (object) [
			'automatic_tax'     => (object) [ 'enabled' => false ],
			'currency'          => 'usd',
			'line_item_details' => $line_items,
			'shipping_details'  => $use_default_shipping
				? (object) [
					'address' => (object) [
						'country'     => 'US',
						'state'       => 'CA',
						'postal_code' => '90210',
						'city'        => 'Beverly Hills',
					],
				]
				: $shipping_details,
			'billing_details'   => $use_default_billing
				? (object) [
					'address' => (object) [
						'country'     => 'US',
						'state'       => 'CA',
						'postal_code' => '90210',
						'city'        => 'Beverly Hills',
					],
				]
				: $billing_details,
		];

		return new WC_Stripe_Agentic_Customize_Checkout_Event(
			(object) [
				'type'     => 'v1.delegated_checkout.customize_checkout',
				'id'       => 'evt_test_tax',
				'livemode' => false,
				'data'     => $data,
			]
		);
	}
}
