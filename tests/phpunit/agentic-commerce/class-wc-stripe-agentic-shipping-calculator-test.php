<?php
/**
 * Tests for WC_Stripe_Agentic_Shipping_Calculator
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

require_once __DIR__ . '/trait-agentic-commerce-test-helpers.php';

use WP_UnitTestCase;
use WC_Shipping_Zone;
use WC_Stripe_Agentic_Shipping_Calculator;
use WC_Stripe_Agentic_Customize_Checkout_Event;

/**
 * Class WC_Stripe_Agentic_Shipping_Calculator_Test
 */
class WC_Stripe_Agentic_Shipping_Calculator_Test extends WP_UnitTestCase {

	use Trait_Agentic_Commerce_Test_Helpers;

	/**
	 * @var WC_Stripe_Agentic_Shipping_Calculator
	 */
	private $calculator;

	/**
	 * @var WC_Shipping_Zone|null
	 */
	private $shipping_zone;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Shipping_Calculator' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Shipping_Calculator class not loaded' );
		}

		$this->calculator = new WC_Stripe_Agentic_Shipping_Calculator();

		$this->save_wc_options( 'woocommerce_ship_to_countries', 'woocommerce_calc_taxes' );

		// WC_Helper_Order (used elsewhere in the suite) enables WC's legacy
		// non-zone flat rate, which only produces a rate for packages with
		// contents. Remove it so single-rate assertions stay deterministic.
		\WC_Helper_Shipping::delete_simple_flat_rate();

		$this->reset_shipping_cache();
	}

	public function tearDown(): void {
		if ( $this->shipping_zone ) {
			$this->shipping_zone->delete();
			$this->shipping_zone = null;
		}

		$this->restore_wc_options();

		$this->reset_shipping_cache();

		parent::tearDown();
	}

	/**
	 * Test that shipping disabled returns empty array.
	 */
	public function test_returns_empty_when_shipping_disabled() {
		update_option( 'woocommerce_ship_to_countries', 'disabled' );

		$event  = $this->build_event_from_products( [] );
		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertEmpty( $result );
	}

	/**
	 * Data provider for tests that assert on a matching US shipping zone.
	 *
	 * @return array<string, array{cost: float, currency: string}>
	 */
	public function matching_zone_provider(): array {
		return [
			'standard cost, lowercase currency' => [
				'cost'     => 5.00,
				'currency' => 'usd',
			],
			'decimal cost, uppercase currency'  => [
				'cost'     => 7.50,
				'currency' => 'USD',
			],
		];
	}

	/**
	 * Test that a matching zone returns correctly structured shipping options,
	 * amounts in Stripe cents format, lowercase currency, and a non-empty display_name.
	 *
	 * @dataProvider matching_zone_provider
	 */
	public function test_matching_zone_returns_valid_shipping_options( float $cost, string $currency ) {
		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', $cost );

		$event  = $this->build_event_from_products( [] );
		$result = $this->calculator->calculate( $event, $currency );

		$this->assertArrayHasKey( 'shipping_options', $result );
		$this->assertNotEmpty( $result['shipping_options'] );

		$option       = $result['shipping_options'][0];
		$rate_data    = $option['shipping_rate_data'];
		$fixed_amount = $rate_data['fixed_amount'];

		// Structure assertions.
		$this->assertArrayHasKey( 'shipping_rate_data', $option );
		$this->assertArrayHasKey( 'wc_rate_id', $rate_data['metadata'] );
		$this->assertNotEmpty( $rate_data['display_name'] );

		// Stripe format: tax_behavior, integer amount, lowercase currency.
		$this->assertEquals( 'inclusive', $rate_data['tax_behavior'] );
		$this->assertIsInt( $fixed_amount['amount'] );
		$this->assertGreaterThan( 0, $fixed_amount['amount'] );
		$this->assertEquals( 'usd', $fixed_amount['currency'] );
	}

	/**
	 * Data provider for content-dependent cost expression scenarios.
	 *
	 * Product specs create simple products; line item specs reference them by
	 * index. Omitting unit_amount exercises the catalog-price fallback.
	 *
	 * @return array<string, array{cost_expression: string, product_specs: array, line_item_specs: array, expected_amount: int}>
	 */
	public function cost_expression_provider(): array {
		return [
			'qty expression sees contents quantities'              => [
				'cost_expression' => '2 * [qty]',
				'product_specs'   => [ [ 'price' => '10.00' ] ],
				'line_item_specs' => [
					[
						'product'     => 0,
						'quantity'    => 3,
						'unit_amount' => 1000,
					],
				],
				'expected_amount' => 600,
			],
			'cost expression uses unit_amount, not catalog price'  => [
				'cost_expression' => '[cost]',
				'product_specs'   => [ [ 'price' => '99.00' ] ],
				'line_item_specs' => [
					[
						'product'     => 0,
						'quantity'    => 2,
						'unit_amount' => 1234,
					],
				],
				'expected_amount' => 2468,
			],
			'cost falls back to catalog price without unit_amount' => [
				'cost_expression' => '[cost]',
				'product_specs'   => [ [ 'price' => '10.00' ] ],
				'line_item_specs' => [
					[
						'product'  => 0,
						'quantity' => 2,
					],
				],
				'expected_amount' => 2000,
			],
			'virtual products excluded from qty'                   => [
				'cost_expression' => '[qty]',
				'product_specs'   => [
					[ 'price' => '10.00' ],
					[
						'price'   => '5.00',
						'virtual' => true,
					],
				],
				'line_item_specs' => [
					[
						'product'     => 0,
						'quantity'    => 2,
						'unit_amount' => 1000,
					],
					[
						'product'     => 1,
						'quantity'    => 5,
						'unit_amount' => 500,
					],
				],
				'expected_amount' => 200,
			],
			'multiple shippable products sum quantities'           => [
				'cost_expression' => '[qty]',
				'product_specs'   => [
					[ 'price' => '10.00' ],
					[ 'price' => '8.00' ],
				],
				'line_item_specs' => [
					[
						'product'     => 0,
						'quantity'    => 2,
						'unit_amount' => 1000,
					],
					[
						'product'     => 1,
						'quantity'    => 3,
						'unit_amount' => 800,
					],
				],
				'expected_amount' => 500,
			],
			'multiple shippable products sum contents cost'        => [
				'cost_expression' => '[cost]',
				'product_specs'   => [
					[ 'price' => '10.00' ],
					[ 'price' => '7.50' ],
				],
				'line_item_specs' => [
					[
						'product'     => 0,
						'quantity'    => 2,
						'unit_amount' => 1000,
					],
					[
						'product'     => 1,
						'quantity'    => 2,
						'unit_amount' => 750,
					],
				],
				'expected_amount' => 3500,
			],
		];
	}

	/**
	 * Test that content-dependent flat rate cost expressions ('[qty]', '[cost]')
	 * price the package contents populated from the event's line items.
	 *
	 * @dataProvider cost_expression_provider
	 */
	public function test_cost_expression_prices_package_contents( string $cost_expression, array $product_specs, array $line_item_specs, int $expected_amount ) {
		$products = [];
		foreach ( $product_specs as $i => $spec ) {
			$products[] = \WC_Helper_Product::create_simple_product(
				true,
				[
					'regular_price' => $spec['price'],
					'price'         => $spec['price'],
					'sku'           => 'SHIPCALC-' . $i . '-' . uniqid(),
					'virtual'       => $spec['virtual'] ?? false,
				]
			);
		}

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', $cost_expression );

		$raw_items = [];
		foreach ( $line_item_specs as $i => $spec ) {
			$raw_item = [
				'id'       => 'li_' . $i,
				'sku_id'   => (string) $products[ $spec['product'] ]->get_sku(),
				'quantity' => $spec['quantity'],
			];
			if ( isset( $spec['unit_amount'] ) ) {
				$raw_item['unit_amount'] = $spec['unit_amount'];
			}
			$raw_items[] = $raw_item;
		}

		$result = $this->calculator->calculate( $this->build_event_from_raw_items( $raw_items ), 'usd' );

		$this->assertSame( $expected_amount, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		foreach ( $products as $product ) {
			$product->delete( true );
		}
	}

	/**
	 * Test that an unresolvable sku_id makes the calculator throw instead of
	 * silently computing rates from an incomplete package.
	 */
	public function test_throws_when_line_item_product_cannot_be_resolved() {
		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', 5.00 );

		$event = $this->build_event_from_raw_items(
			[
				[
					'id'       => 'li_missing',
					'sku_id'   => 'NON-EXISTENT-SKU-' . uniqid(),
					'quantity' => 1,
				],
			]
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'product not found' );

		$this->calculator->calculate( $event, 'usd' );
	}

	/**
	 * Data provider for out-of-bounds line item quantity/unit_amount values.
	 *
	 * @return array<string, array{quantity: int, unit_amount: int}>
	 */
	public function invalid_line_item_bounds_provider(): array {
		return [
			'zero quantity'        => [
				'quantity'    => 0,
				'unit_amount' => 1000,
			],
			'negative quantity'    => [
				'quantity'    => -2,
				'unit_amount' => 1000,
			],
			'negative unit_amount' => [
				'quantity'    => 1,
				'unit_amount' => -500,
			],
		];
	}

	/**
	 * Test that a non-positive quantity or negative unit_amount makes the
	 * calculator throw instead of computing rates from corrupted line totals.
	 *
	 * @dataProvider invalid_line_item_bounds_provider
	 */
	public function test_throws_on_out_of_bounds_line_item_values( int $quantity, int $unit_amount ) {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
				'sku'           => 'SHIPCALC-BOUNDS-' . uniqid(),
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '[cost]' );

		$event = $this->build_event_from_raw_items(
			[
				[
					'id'          => 'li_bounds',
					'sku_id'      => (string) $product->get_sku(),
					'quantity'    => $quantity,
					'unit_amount' => $unit_amount,
				],
			]
		);

		try {
			$this->expectException( \Exception::class );
			$this->expectExceptionMessage( 'invalid quantity or unit_amount' );

			$this->calculator->calculate( $event, 'usd' );
		} finally {
			$product->delete( true );
		}
	}

	/**
	 * Test that a line item without unit_amount whose product has no catalog
	 * price makes the calculator throw instead of quoting a 0.00 package.
	 */
	public function test_throws_when_catalog_price_fallback_is_unavailable() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '',
				'price'         => '',
				'sku'           => 'SHIPCALC-NOPRICE-' . uniqid(),
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '[cost]' );

		$event = $this->build_event_from_raw_items(
			[
				[
					'id'       => 'li_no_price',
					'sku_id'   => (string) $product->get_sku(),
					'quantity' => 1,
				],
			]
		);

		try {
			$this->expectException( \Exception::class );
			$this->expectExceptionMessage( 'no catalog price' );

			$this->calculator->calculate( $event, 'usd' );
		} finally {
			$product->delete( true );
		}
	}

	/**
	 * Test that a non-matching country returns empty or does not include the wrong zone.
	 */
	public function test_returns_empty_for_non_matching_zone() {
		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'DE', 5.00 );

		$event  = $this->build_event_from_products( [] ); // US address.
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
	 * Test that a full customize_checkout webhook payload parses end-to-end: its
	 * single qty-1 line item resolves to a shippable product and a
	 * content-dependent '2 * [qty]' flat rate prices the populated package.
	 */
	public function test_full_customize_checkout_payload_prices_package_contents() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '24.99',
				'price'         => '24.99',
				'sku'           => 'SKU0029',
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '2 * [qty]' );

		$raw    = $this->get_mock_customize_checkout_event();
		$event  = new WC_Stripe_Agentic_Customize_Checkout_Event( $raw );
		$result = $this->calculator->calculate( $event, $raw->data->currency );

		$this->assertSame( 200, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		$product->delete( true );
	}

	/**
	 * Returns a mock customize_checkout webhook event mirroring the full payload
	 * Stripe sends; identifiers and the address are sanitized placeholders.
	 *
	 * @return \stdClass The decoded webhook event.
	 */
	private function get_mock_customize_checkout_event(): \stdClass {
		$json = <<<'JSON'
{
  "id": "evt_test_customize_checkout",
  "type": "v1.delegated_checkout.customize_checkout",
  "livemode": false,
  "data": {
    "amount_subtotal": 2499,
    "amount_total": 3498,
    "automatic_tax": { "enabled": false },
    "checkout_session": "cs_test_a1ExampleCheckoutSessionIdForDocsOnly000000000000000000",
    "currency": "usd",
    "discounts": [],
    "line_item_details": [
      {
        "id": "li_ExampleLineItem001",
        "amount_discount": 0,
        "amount_subtotal": 2499,
        "amount_tax": 0,
        "amount_total": 2499,
        "name": "Sample Product",
        "quantity": 1,
        "sku_id": "SKU0029",
        "tax_rates": [],
        "unit_amount": 2499
      }
    ],
    "metadata": {},
    "shipping_details": {
      "address": {
        "city": "San Francisco",
        "country": "US",
        "line1": "123 Example Street",
        "line2": "",
        "postal_code": "94016",
        "state": "CA"
      },
      "discount_details": [],
      "shipping_rate": {
        "id": "shr_ExampleExpedited001",
        "delivery_estimate": {
          "maximum": { "unit": "day", "value": 3 },
          "minimum": { "unit": "day", "value": 2 }
        },
        "display_name": "Expedited",
        "fixed_amount": {
          "amount": 999,
          "currency": "usd",
          "currency_options": { "usd": { "amount": 999, "tax_behavior": "exclusive" } }
        },
        "metadata": {},
        "tax_behavior": "exclusive",
        "tax_code": "txcd_92010001"
      },
      "shipping_rates": [
        {
          "id": "shr_ExampleExpedited001",
          "delivery_estimate": {
            "maximum": { "unit": "day", "value": 3 },
            "minimum": { "unit": "day", "value": 2 }
          },
          "display_name": "Expedited",
          "fixed_amount": {
            "amount": 999,
            "currency": "usd",
            "currency_options": { "usd": { "amount": 999, "tax_behavior": "exclusive" } }
          },
          "metadata": {},
          "tax_behavior": "exclusive",
          "tax_code": "txcd_92010001"
        },
        {
          "id": "shr_ExampleExpress001",
          "delivery_estimate": {
            "maximum": { "unit": "day", "value": 2 },
            "minimum": { "unit": "day", "value": 1 }
          },
          "display_name": "Express",
          "fixed_amount": {
            "amount": 1499,
            "currency": "usd",
            "currency_options": { "usd": { "amount": 1499, "tax_behavior": "exclusive" } }
          },
          "metadata": {},
          "tax_behavior": "exclusive",
          "tax_code": "txcd_92010001"
        }
      ]
    },
    "total_details": {
      "amount_discount": 0,
      "amount_shipping": 999,
      "amount_tax": 0
    }
  }
}
JSON;

		return json_decode( $json, false );
	}
}
