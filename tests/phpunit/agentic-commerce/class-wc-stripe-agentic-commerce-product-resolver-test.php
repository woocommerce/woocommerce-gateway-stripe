<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Resolver
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * @covers WC_Stripe_Agentic_Commerce_Product_Resolver
 */
class WC_Stripe_Agentic_Commerce_Product_Resolver_Test extends WP_UnitTestCase {

	/**
	 * SKU lookup resolves to the matching WC product, regardless of whether
	 * the SKU happens to be alphanumeric or fully numeric.
	 */
	public function test_resolves_via_sku() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[ 'sku' => 'RESOLVER-' . uniqid() ]
		);

		try {
			$this->assertSame(
				$product->get_id(),
				WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference(
					$product->get_sku()
				)
			);
		} finally {
			$product->delete( true );
		}
	}

	/**
	 * Catalogs synced under the legacy contract carry the WC product ID in
	 * `external_reference`. The SKU lookup misses, so the numeric fallback
	 * must resolve so SKU-less products keep working end-to-end.
	 */
	public function test_falls_back_to_numeric_product_id() {
		$product = WC_Helper_Product::create_simple_product( true, [ 'sku' => '' ] );

		try {
			$this->assertSame(
				$product->get_id(),
				WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference(
					(string) $product->get_id()
				)
			);
		} finally {
			$product->delete( true );
		}
	}

	/**
	 * A non-empty external reference that matches neither a SKU nor a real
	 * product ID returns 0 — the resolver is a lookup, not a coercion.
	 */
	public function test_returns_zero_for_unknown_reference() {
		$this->assertSame(
			0,
			WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference(
				'UNKNOWN-' . uniqid()
			)
		);
	}

	/**
	 * Numeric reference for a non-existent product ID also returns 0; the
	 * fallback must not coerce numbers into IDs without verifying the
	 * product exists.
	 */
	public function test_returns_zero_for_unknown_numeric_reference() {
		$this->assertSame(
			0,
			WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( '99999999' )
		);
	}

	/**
	 * Empty string short-circuits to 0 without running any lookup.
	 */
	public function test_returns_zero_for_empty_reference() {
		$this->assertSame(
			0,
			WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( '' )
		);
	}

	/**
	 * SKU lookup wins over the numeric fallback even when the SKU is itself
	 * a digit string. A merchant who deliberately set a numeric SKU on
	 * product B that collides with product A's ID must still be routed to
	 * B (the SKU match), not A (the fallback).
	 */
	public function test_sku_match_wins_over_numeric_fallback() {
		$collide_target = WC_Helper_Product::create_simple_product( true, [ 'sku' => '' ] );
		$sku_owner      = WC_Helper_Product::create_simple_product(
			true,
			[ 'sku' => (string) $collide_target->get_id() ]
		);

		try {
			$this->assertSame(
				$sku_owner->get_id(),
				WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference(
					(string) $collide_target->get_id()
				)
			);
		} finally {
			$collide_target->delete( true );
			$sku_owner->delete( true );
		}
	}
}
