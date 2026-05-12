<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Tax_Calculator
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

require_once __DIR__ . '/trait-agentic-commerce-test-helpers.php';

use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Stripe_Agentic_Commerce_Tax_Calculator;
use WC_Tax;
use Exception;

/**
 * Class WC_Stripe_Agentic_Commerce_Tax_Calculator_Test
 */
class WC_Stripe_Agentic_Commerce_Tax_Calculator_Test extends WP_UnitTestCase {

	use Trait_Agentic_Commerce_Test_Helpers;

	/**
	 * @var WC_Stripe_Agentic_Commerce_Tax_Calculator
	 */
	private $calculator;

	/**
	 * @var \WC_Product
	 */
	private $product;

	/**
	 * @var int
	 */
	private $tax_rate_id;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Tax_Calculator' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Tax_Calculator class not loaded' );
		}

		$this->calculator = new WC_Stripe_Agentic_Commerce_Tax_Calculator();

		$this->product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '25.00',
				'price'         => '25.00',
				'sku'           => 'TAX-CALC-MAIN',
			]
		);

		$this->save_wc_options(
			'woocommerce_calc_taxes',
			'woocommerce_tax_based_on',
			'woocommerce_prices_include_tax'
		);

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		$this->tax_rate_id = $this->create_tax_rate( 'US', 'CA', '10.0000', 'US-CA Tax' );
	}

	public function tearDown(): void {
		if ( $this->product ) {
			$this->product->delete( true );
		}

		if ( $this->tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $this->tax_rate_id );
		}

		$this->restore_wc_options();

		parent::tearDown();
	}

	/**
	 * Test tax calculation for a single product with matching tax rate.
	 */
	public function test_calculate_returns_tax_rates_for_matching_address() {
		$event      = $this->build_event_from_products( [ $this->product ] );
		$line_items = $this->calculator->extract_line_items_from_customization_hook( $event );
		$result     = $this->calculator->calculate( $event, $line_items );

		$this->assertArrayHasKey( 'line_items', $result );
		$this->assertCount( 1, $result['line_items'] );

		$item = $result['line_items'][0];
		$this->assertNotEmpty( $item['tax_rates'] );
		$this->assertEquals( 10.0, $item['tax_rates'][0]['rate_data']['percentage'] );
		$this->assertEquals( 'US-CA Tax', $item['tax_rates'][0]['rate_data']['display_name'] );
		$this->assertFalse( $item['tax_rates'][0]['rate_data']['inclusive'] );
	}

	/**
	 * @dataProvider empty_rates_provider
	 *
	 * Tests that various conditions produce empty tax rates for each line item.
	 *
	 * @param callable  $setup          Optional setup callback run before building the event.
	 * @param array     $address        Address overrides for the event.
	 * @param bool      $automatic_tax  Whether Stripe automatic tax is enabled.
	 */
	public function test_calculate_returns_empty_rates( callable $setup, array $address, bool $automatic_tax ) {
		$setup();

		$event      = $this->build_event_from_products( [ $this->product ], $address, $automatic_tax );
		$line_items = $this->calculator->extract_line_items_from_customization_hook( $event );
		$result     = $this->calculator->calculate( $event, $line_items );

		$this->assertArrayHasKey( 'line_items', $result );
		$this->assertCount( 1, $result['line_items'] );
		$this->assertEmpty( $result['line_items'][0]['tax_rates'] );
	}

	/**
	 * Data provider for test_calculate_returns_empty_rates.
	 *
	 * @return array[]
	 */
	public function empty_rates_provider(): array {
		return [
			'tax disabled'              => [
				'setup'         => function () {
					update_option( 'woocommerce_calc_taxes', 'no' );
				},
				'address'       => [],
				'automatic_tax' => false,
			],
			'automatic tax enabled'     => [
				'setup'         => function () {},
				'address'       => [],
				'automatic_tax' => true,
			],
			'non-matching address (DE)' => [
				'setup'         => function () {},
				'address'       => [
					'country'     => 'DE',
					'state'       => '',
					'postal_code' => '10115',
					'city'        => 'Berlin',
				],
				'automatic_tax' => false,
			],
		];
	}

	/**
	 * Test that multiple products each get their own tax rates.
	 */
	public function test_calculate_with_multiple_products() {
		$product2 = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '15.00',
				'price'         => '15.00',
				'sku'           => 'TAX-CALC-SECOND',
			]
		);

		try {
			$event      = $this->build_event_from_products( [ $this->product, $product2 ] );
			$line_items = $this->calculator->extract_line_items_from_customization_hook( $event );
			$result     = $this->calculator->calculate( $event, $line_items );

			$this->assertCount( 2, $result['line_items'] );
			$this->assertNotEmpty( $result['line_items'][0]['tax_rates'] );
			$this->assertNotEmpty( $result['line_items'][1]['tax_rates'] );
		} finally {
			$product2->delete( true );
		}
	}

	/**
	 * Test that an unknown SKU throws an exception during extraction.
	 */
	public function test_extract_throws_for_unknown_sku() {
		$event = $this->build_event_from_raw_items(
			[
				[
					'id'     => 'li_missing',
					'sku_id' => 'DOES-NOT-EXIST',
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Product not found for line item li_missing with sku_id "DOES-NOT-EXIST" (no SKU or legacy product-ID match).' );

		$this->calculator->extract_line_items_from_customization_hook( $event );
	}

	/**
	 * Test that an empty sku_id throws an exception during extraction.
	 */
	public function test_extract_throws_for_empty_sku_id() {
		$event = $this->build_event_from_raw_items(
			[
				[
					'id'     => 'li_empty_sku',
					'sku_id' => '',
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Line item li_empty_sku has no sku_id' );

		$this->calculator->extract_line_items_from_customization_hook( $event );
	}

	/**
	 * @dataProvider tax_based_on_provider
	 *
	 * The customize_checkout event uses shipping_details.address for both
	 * get_billing_address() and get_shipping_address(), so the tax_based_on
	 * setting doesn't change which address is used in practice. This test
	 * verifies that both settings produce valid results without errors.
	 */
	public function test_calculate_works_with_both_tax_based_on_settings( string $tax_based_on ) {
		update_option( 'woocommerce_tax_based_on', $tax_based_on );

		$event      = $this->build_event_from_products( [ $this->product ] );
		$line_items = $this->calculator->extract_line_items_from_customization_hook( $event );
		$result     = $this->calculator->calculate( $event, $line_items );

		$this->assertArrayHasKey( 'line_items', $result );
		$this->assertCount( 1, $result['line_items'] );
		// CA address matches our tax rate regardless of the setting.
		$this->assertNotEmpty( $result['line_items'][0]['tax_rates'] );
	}

	/**
	 * Data provider for tax_based_on tests.
	 *
	 * @return array[]
	 */
	public function tax_based_on_provider(): array {
		return [
			'shipping' => [ 'shipping' ],
			'billing'  => [ 'billing' ],
		];
	}

	/**
	 * Test inclusive tax flag when prices include tax.
	 */
	public function test_inclusive_flag_when_prices_include_tax() {
		update_option( 'woocommerce_prices_include_tax', 'yes' );

		$event      = $this->build_event_from_products( [ $this->product ] );
		$line_items = $this->calculator->extract_line_items_from_customization_hook( $event );
		$result     = $this->calculator->calculate( $event, $line_items );

		$this->assertTrue( $result['line_items'][0]['tax_rates'][0]['rate_data']['inclusive'] );
	}

	/**
	 * Test that different tax classes produce different rates.
	 */
	public function test_different_tax_classes() {
		$reduced_rate_id = $this->create_tax_rate( 'US', 'CA', '5.0000', 'Reduced Rate', 'reduced-rate' );

		$reduced_product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '20.00',
				'price'         => '20.00',
				'tax_class'     => 'reduced-rate',
				'sku'           => 'TAX-CALC-REDUCED',
			]
		);

		try {
			$event      = $this->build_event_from_products( [ $this->product, $reduced_product ] );
			$line_items = $this->calculator->extract_line_items_from_customization_hook( $event );
			$result     = $this->calculator->calculate( $event, $line_items );

			$this->assertEquals( 10.0, $result['line_items'][0]['tax_rates'][0]['rate_data']['percentage'] );
			$this->assertEquals( 5.0, $result['line_items'][1]['tax_rates'][0]['rate_data']['percentage'] );
		} finally {
			$reduced_product->delete( true );
			WC_Tax::_delete_tax_rate( $reduced_rate_id );
		}
	}

	/**
	 * Test extract_line_items_from_customization_hook returns correct ID => product ID mapping.
	 */
	public function test_extract_line_items_returns_id_to_product_id_map() {
		$event  = $this->build_event_from_products( [ $this->product ] );
		$result = $this->calculator->extract_line_items_from_customization_hook( $event );

		$this->assertCount( 1, $result );
		$keys   = array_keys( $result );
		$values = array_values( $result );

		$this->assertStringStartsWith( 'li_', $keys[0] );
		$this->assertSame( $this->product->get_id(), $values[0] );
	}
}
