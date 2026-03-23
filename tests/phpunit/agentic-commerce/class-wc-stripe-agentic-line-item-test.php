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
			'get_id with value'                  => [ [ 'id' => 'li_test_123' ], 'get_id', 'li_test_123' ],
			'get_id when missing'                => [ [], 'get_id', '' ],
			'get_description with value'         => [ [ 'description' => 'Test Widget' ], 'get_description', 'Test Widget' ],
			'get_description when missing'       => [ [], 'get_description', '' ],
			'get_quantity with value'            => [ [ 'quantity' => 3 ], 'get_quantity', 3 ],
			'get_quantity defaults to 1'         => [ [], 'get_quantity', 1 ],
			'get_amount_total with value'        => [ [ 'amount_total' => 2500 ], 'get_amount_total', 2500 ],
			'get_amount_total defaults to 0'     => [ [], 'get_amount_total', 0 ],
			'get_amount_tax with value'          => [ [ 'amount_tax' => 300 ], 'get_amount_tax', 300 ],
			'get_amount_tax defaults to 0'       => [ [], 'get_amount_tax', 0 ],
		];
	}

	/**
	 * @dataProvider provide_product_id_cases
	 */
	public function test_get_product_id( object $raw, int $expected ) {
		$item = new WC_Stripe_Agentic_Line_Item( $raw );
		$this->assertSame( $expected, $item->get_product_id() );
	}

	/**
	 * @return array
	 */
	public function provide_product_id_cases(): array {
		return [
			'valid integer string'         => [
				(object) [ 'price' => (object) [ 'external_reference' => '42' ] ],
				42,
			],
			'zero string'                  => [
				(object) [ 'price' => (object) [ 'external_reference' => '0' ] ],
				0,
			],
			'non-numeric string'           => [
				(object) [ 'price' => (object) [ 'external_reference' => 'abc' ] ],
				0,
			],
			'null external_reference'      => [
				(object) [ 'price' => (object) [ 'external_reference' => null ] ],
				0,
			],
			'missing external_reference'   => [
				(object) [ 'price' => (object) [] ],
				0,
			],
			'null price'                   => [
				(object) [ 'price' => null ],
				0,
			],
			'missing price'                => [
				(object) [],
				0,
			],
			'price is not an object'       => [
				(object) [ 'price' => 'not_an_object' ],
				0,
			],
		];
	}

	/**
	 * @dataProvider provide_has_product_id_cases
	 */
	public function test_has_product_id( object $raw, bool $expected ) {
		$item = new WC_Stripe_Agentic_Line_Item( $raw );
		$this->assertSame( $expected, $item->has_product_id() );
	}

	/**
	 * @return array
	 */
	public function provide_has_product_id_cases(): array {
		return [
			'has valid product ID'    => [
				(object) [ 'price' => (object) [ 'external_reference' => '99' ] ],
				true,
			],
			'zero product ID'         => [
				(object) [ 'price' => (object) [ 'external_reference' => '0' ] ],
				false,
			],
			'missing price'           => [
				(object) [],
				false,
			],
		];
	}
}
