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
	 * Test that a quantity-based flat rate cost expression ('2 * [qty]') sees the
	 * package contents quantities, proving contents are populated from the event.
	 */
	public function test_qty_cost_expression_uses_package_contents() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
				'sku'           => 'SHIPCALC-QTY-' . uniqid(),
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '2 * [qty]' );

		$event = $this->build_event_from_raw_items(
			[
				[
					'id'          => 'li_qty',
					'sku_id'      => (string) $product->get_sku(),
					'quantity'    => 3,
					'unit_amount' => 1000,
				],
			]
		);

		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertSame( 600, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		$product->delete( true );
	}

	/**
	 * Test that a '[cost]' expression resolves to the package contents_cost
	 * derived from the event's unit_amount × quantity.
	 */
	public function test_cost_expression_uses_contents_cost_from_unit_amount() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '99.00',
				'price'         => '99.00',
				'sku'           => 'SHIPCALC-COST-' . uniqid(),
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '[cost]' );

		// 12.34 × 2 = 24.68 contents_cost; catalog price (99.00) must be ignored.
		$event = $this->build_event_from_raw_items(
			[
				[
					'id'          => 'li_cost',
					'sku_id'      => (string) $product->get_sku(),
					'quantity'    => 2,
					'unit_amount' => 1234,
				],
			]
		);

		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertSame( 2468, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		$product->delete( true );
	}

	/**
	 * Test that line items without unit_amount fall back to the catalog price
	 * when computing contents_cost.
	 */
	public function test_contents_cost_falls_back_to_catalog_price_without_unit_amount() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
				'sku'           => 'SHIPCALC-FALLBACK-' . uniqid(),
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '[cost]' );

		$event = $this->build_event_from_raw_items(
			[
				[
					'id'       => 'li_fallback',
					'sku_id'   => (string) $product->get_sku(),
					'quantity' => 2,
				],
			]
		);

		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertSame( 2000, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		$product->delete( true );
	}

	/**
	 * Test that virtual products are excluded from the package contents, so
	 * quantity-based expressions only count shippable items.
	 */
	public function test_virtual_products_excluded_from_package_contents() {
		$shippable = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
				'sku'           => 'SHIPCALC-PHYS-' . uniqid(),
			]
		);
		$virtual   = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '5.00',
				'price'         => '5.00',
				'sku'           => 'SHIPCALC-VIRT-' . uniqid(),
				'virtual'       => true,
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '[qty]' );

		$event = $this->build_event_from_raw_items(
			[
				[
					'id'          => 'li_phys',
					'sku_id'      => (string) $shippable->get_sku(),
					'quantity'    => 2,
					'unit_amount' => 1000,
				],
				[
					'id'          => 'li_virt',
					'sku_id'      => (string) $virtual->get_sku(),
					'quantity'    => 5,
					'unit_amount' => 500,
				],
			]
		);

		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertSame( 200, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		$shippable->delete( true );
		$virtual->delete( true );
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
	 * Test that the full, realistic customize_checkout webhook payload published in
	 * the PR description parses end-to-end: its single qty-1 line item resolves to a
	 * shippable product and a content-dependent '2 * [qty]' flat rate prices the
	 * populated package (0.00 against an empty package before this change).
	 */
	public function test_realistic_customize_checkout_payload_prices_package_contents() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '24.99',
				'price'         => '24.99',
				'sku'           => 'SKU0029',
			]
		);

		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', '2 * [qty]' );

		$raw    = $this->realistic_customize_checkout_event();
		$event  = new WC_Stripe_Agentic_Customize_Checkout_Event( $raw );
		$result = $this->calculator->calculate( $event, $raw->data->currency );

		$this->assertSame( 200, $result['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] );

		$product->delete( true );
	}

	/**
	 * Returns the sanitized customize_checkout event used by the realistic payload
	 * test. This is the same JSON shared in the PR description so a reviewer can
	 * replay it against a store; identifiers and the address are placeholders.
	 *
	 * @return \stdClass The decoded webhook event.
	 */
	private function realistic_customize_checkout_event(): \stdClass {
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
