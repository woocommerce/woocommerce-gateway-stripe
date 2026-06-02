<?php
/**
 * Tests for WC_Stripe_Agentic_Line_Item
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Line_Item_Test
 *
 * Tests the typed wrapper around a Stripe checkout session line item.
 *
 * @covers WC_Stripe_Agentic_Line_Item
 */
class WC_Stripe_Agentic_Line_Item_Test extends WP_UnitTestCase {

	/**
	 * Test simple scalar getters with present and missing values.
	 *
	 * @param array  $raw_props  Properties to pass to `WC_Stripe_Agentic_Line_Item`.
	 * @param string $getter     The getter method to call.
	 * @param mixed  $expected   The expected return value.
	 * @return void
	 * @dataProvider provide_test_scalar_getters
	 */
	public function test_scalar_getters( array $raw_props, string $getter, $expected ) {
		$item = new WC_Stripe_Agentic_Line_Item( (object) $raw_props );
		$this->assertSame( $expected, $item->$getter() );
	}

	/**
	 * Data provider for `test_scalar_getters`.
	 *
	 * @return array
	 */
	public function provide_test_scalar_getters(): array {
		return [
			'get_id with value'              => [ [ 'id' => 'li_test_123' ], 'get_id', 'li_test_123' ],
			'get_id when missing'            => [ [], 'get_id', '' ],
			'get_description with value'     => [ [ 'description' => 'Test Widget' ], 'get_description', 'Test Widget' ],
			'get_description when missing'   => [ [], 'get_description', '' ],
			'get_quantity with value'        => [ [ 'quantity' => 3 ], 'get_quantity', 3 ],
			'get_quantity defaults to 1'     => [ [], 'get_quantity', 1 ],
			'get_amount_total with value'    => [ [ 'amount_total' => 2500 ], 'get_amount_total', 2500 ],
			'get_amount_total defaults to 0' => [ [], 'get_amount_total', 0 ],
			'get_amount_tax with value'      => [ [ 'amount_tax' => 300 ], 'get_amount_tax', 300 ],
			'get_amount_tax defaults to 0'   => [ [], 'get_amount_tax', 0 ],
		];
	}

	/**
	 * Test that get_product_id resolves the price's external_reference SKU to a real product.
	 */
	public function test_get_product_id_resolves_sku_to_product() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'sku' => 'LINE-ITEM-SKU-' . uniqid(),
			]
		);

		$item = new WC_Stripe_Agentic_Line_Item(
			(object) [
				'price' => (object) [ 'external_reference' => $product->get_sku() ],
			]
		);

		$this->assertSame( $product->get_id(), $item->get_product_id() );

		$product->delete( true );
	}

	/**
	 * Catalogs synced under the legacy contract carry the WooCommerce product ID
	 * in `external_reference`. SKU lookup misses (no product has that ID as a SKU),
	 * but the numeric fallback must still resolve so those checkouts complete.
	 */
	public function test_get_product_id_falls_back_to_numeric_product_id() {
		$product = WC_Helper_Product::create_simple_product( true );

		$item = new WC_Stripe_Agentic_Line_Item(
			(object) [
				'price' => (object) [ 'external_reference' => (string) $product->get_id() ],
			]
		);

		$this->assertSame( $product->get_id(), $item->get_product_id() );

		$product->delete( true );
	}

	/**
	 * A numeric `external_reference` that doesn't match any real product must
	 * still return 0 — the fallback is a lookup, not a coercion.
	 */
	public function test_get_product_id_returns_zero_for_unknown_numeric_reference() {
		$item = new WC_Stripe_Agentic_Line_Item(
			(object) [ 'price' => (object) [ 'external_reference' => '99999999' ] ]
		);

		$this->assertSame( 0, $item->get_product_id() );
	}

	/**
	 * @dataProvider provide_unresolvable_product_id_cases
	 */
	public function test_get_product_id_returns_zero_when_unresolvable( object $raw ) {
		$item = new WC_Stripe_Agentic_Line_Item( $raw );
		$this->assertSame( 0, $item->get_product_id() );
	}

	/**
	 * @return array
	 */
	public function provide_unresolvable_product_id_cases(): array {
		return [
			'unknown SKU string'         => [
				(object) [ 'price' => (object) [ 'external_reference' => 'SKU-DOES-NOT-EXIST-' . uniqid() ] ],
			],
			'empty external_reference'   => [
				(object) [ 'price' => (object) [ 'external_reference' => '' ] ],
			],
			'null external_reference'    => [
				(object) [ 'price' => (object) [ 'external_reference' => null ] ],
			],
			'missing external_reference' => [
				(object) [ 'price' => (object) [] ],
			],
			'null price'                 => [
				(object) [ 'price' => null ],
			],
			'missing price'              => [
				(object) [],
			],
			'price is not an object'     => [
				(object) [ 'price' => 'not_an_object' ],
			],
		];
	}

	/**
	 * Test that has_product_id is true when the SKU resolves to a product.
	 */
	public function test_has_product_id_true_when_sku_resolves() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'sku' => 'LINE-ITEM-HAS-' . uniqid(),
			]
		);

		$item = new WC_Stripe_Agentic_Line_Item(
			(object) [
				'price' => (object) [ 'external_reference' => $product->get_sku() ],
			]
		);

		$this->assertTrue( $item->has_product_id() );

		$product->delete( true );
	}

	/**
	 * Test that has_product_id is false when the SKU does not resolve.
	 */
	public function test_has_product_id_false_when_sku_unknown() {
		$item = new WC_Stripe_Agentic_Line_Item(
			(object) [
				'price' => (object) [ 'external_reference' => 'UNKNOWN-SKU-' . uniqid() ],
			]
		);

		$this->assertFalse( $item->has_product_id() );
	}
}
