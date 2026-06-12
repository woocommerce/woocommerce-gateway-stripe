<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Exclusion
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Covers the per-product exclude flag storage and the should-sync filter.
 */
class WC_Stripe_Agentic_Commerce_Product_Exclusion_Test extends WP_UnitTestCase {

	/**
	 * Skip if the class isn't autoloaded.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Exclusion' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Exclusion class not loaded' );
		}
	}

	/**
	 * is_excluded() returns true only for a literal 'yes'; everything else is included.
	 */
	public function test_is_excluded_reads_meta_value(): void {
		$product = WC_Helper_Product::create_simple_product();

		$this->assertFalse(
			WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( $product->get_id() ),
			'Default (no meta) must mean the product is included.'
		);

		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::META_KEY, 'yes' );
		$this->assertTrue( WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( $product->get_id() ) );

		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::META_KEY, 'no' );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( $product->get_id() ) );

		$product->delete( true );
	}

	/**
	 * Invalid IDs (zero, negative, non-product) return false rather than fatal.
	 */
	public function test_is_excluded_handles_invalid_ids(): void {
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( 0 ) );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( -1 ) );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( 999999 ) );
	}

	/**
	 * Variations inherit the parent's flag (the checkbox lives on the parent).
	 */
	public function test_is_excluded_for_variation_reads_parent_meta(): void {
		$parent = WC_Helper_Product::create_variation_product();
		update_post_meta( $parent->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::META_KEY, 'yes' );

		$variation_ids = $parent->get_children();
		$this->assertNotEmpty( $variation_ids );

		foreach ( $variation_ids as $variation_id ) {
			$this->assertTrue(
				WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( (int) $variation_id ),
				"Variation $variation_id must inherit the parent's exclude flag."
			);
		}

		$parent->delete( true );
	}

	/**
	 * set_excluded() persists the flag and reports whether the stored value changed.
	 */
	public function test_set_excluded_persists_and_reports_change(): void {
		$product = WC_Helper_Product::create_simple_product();

		// Missing meta → 'yes' counts as a change.
		$this->assertTrue( WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product->get_id(), true ) );
		$this->assertSame( 'yes', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::META_KEY, true ) );

		// Same value again → no change.
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product->get_id(), true ) );

		// Flip back off → change.
		$this->assertTrue( WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product->get_id(), false ) );
		$this->assertSame( 'no', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::META_KEY, true ) );

		// Invalid ID is a no-op, not a fatal.
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( 0, true ) );

		$product->delete( true );
	}

	/**
	 * init()'s filter callback votes false for an excluded product.
	 */
	public function test_filter_callback_returns_false_when_excluded(): void {
		$exclusion = new WC_Stripe_Agentic_Commerce_Product_Exclusion();
		$exclusion->init();

		$included = WC_Helper_Product::create_simple_product();
		$excluded = WC_Helper_Product::create_simple_product();
		update_post_meta( $excluded->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::META_KEY, 'yes' );

		$this->assertTrue( apply_filters( 'wc_stripe_agentic_commerce_should_sync_product', true, $included ) );
		$this->assertFalse( apply_filters( 'wc_stripe_agentic_commerce_should_sync_product', true, $excluded ) );

		remove_filter( 'wc_stripe_agentic_commerce_should_sync_product', [ $exclusion, 'filter_should_sync_product' ], 10 );
		$included->delete( true );
		$excluded->delete( true );
	}

	/**
	 * The callback respects a prior false vote rather than resurrecting the product.
	 */
	public function test_filter_callback_respects_prior_false_vote(): void {
		$exclusion = new WC_Stripe_Agentic_Commerce_Product_Exclusion();
		$product   = WC_Helper_Product::create_simple_product();

		$this->assertFalse( $exclusion->filter_should_sync_product( false, $product ) );

		$product->delete( true );
	}

	/**
	 * A non-WC_Product payload preserves the incoming vote instead of fataling,
	 * normalizing mixed hook input so a prior false vote is never revived.
	 *
	 * @dataProvider provide_mixed_should_sync_votes
	 * @param mixed $vote     Raw value a prior filter callback may have returned.
	 * @param bool  $expected Expected normalized result.
	 */
	public function test_filter_callback_tolerates_non_product_payload( $vote, bool $expected ): void {
		$exclusion = new WC_Stripe_Agentic_Commerce_Product_Exclusion();

		$this->assertSame( $expected, $exclusion->filter_should_sync_product( $vote, null ) );
	}

	/**
	 * Mixed hook-input votes and their normalized boolean results.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function provide_mixed_should_sync_votes(): array {
		return [
			'bool true'      => [ true, true ],
			'bool false'     => [ false, false ],
			'string "false"' => [ 'false', false ],
			'string "0"'     => [ '0', false ],
			'int 0'          => [ 0, false ],
			'empty string'   => [ '', false ],
			'string "1"'     => [ '1', true ],
			'int 1'          => [ 1, true ],
		];
	}
}
