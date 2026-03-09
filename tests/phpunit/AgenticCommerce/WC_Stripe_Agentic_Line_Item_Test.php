<?php
/**
 * Tests for WC_Stripe_Agentic_Line_Item
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Stripe_Agentic_Line_Item;

/**
 * Class WC_Stripe_Agentic_Line_Item_Test
 *
 * Tests the typed wrapper around a Stripe checkout session line item.
 *
 * @covers WC_Stripe_Agentic_Line_Item
 */
class WC_Stripe_Agentic_Line_Item_Test extends WP_UnitTestCase {

	/**
	 * Test get_id returns the line item ID.
	 */
	public function test_get_id() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [ 'id' => 'li_test_123' ] );
		$this->assertSame( 'li_test_123', $item->get_id() );
	}

	/**
	 * Test get_id returns empty string when missing.
	 */
	public function test_get_id_returns_empty_when_missing() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [] );
		$this->assertSame( '', $item->get_id() );
	}

	/**
	 * Test get_description returns the description.
	 */
	public function test_get_description() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [ 'description' => 'Test Widget' ] );
		$this->assertSame( 'Test Widget', $item->get_description() );
	}

	/**
	 * Test get_description returns empty string when missing.
	 */
	public function test_get_description_returns_empty_when_missing() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [] );
		$this->assertSame( '', $item->get_description() );
	}

	/**
	 * Test get_quantity returns the quantity.
	 */
	public function test_get_quantity() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [ 'quantity' => 3 ] );
		$this->assertSame( 3, $item->get_quantity() );
	}

	/**
	 * Test get_quantity defaults to 1 when missing.
	 */
	public function test_get_quantity_defaults_to_one() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [] );
		$this->assertSame( 1, $item->get_quantity() );
	}

	/**
	 * Test get_amount_total returns the total.
	 */
	public function test_get_amount_total() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [ 'amount_total' => 2500 ] );
		$this->assertSame( 2500, $item->get_amount_total() );
	}

	/**
	 * Test get_amount_total defaults to zero.
	 */
	public function test_get_amount_total_defaults_to_zero() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [] );
		$this->assertSame( 0, $item->get_amount_total() );
	}

	/**
	 * Test get_amount_tax returns the tax amount.
	 */
	public function test_get_amount_tax() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [ 'amount_tax' => 300 ] );
		$this->assertSame( 300, $item->get_amount_tax() );
	}

	/**
	 * Test get_amount_tax defaults to zero.
	 */
	public function test_get_amount_tax_defaults_to_zero() {
		$item = new WC_Stripe_Agentic_Line_Item( (object) [] );
		$this->assertSame( 0, $item->get_amount_tax() );
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
