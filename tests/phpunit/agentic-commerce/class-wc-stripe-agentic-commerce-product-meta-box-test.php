<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Meta_Box
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Product_Meta_Box_Test
 *
 * Covers the per-product exclude toggle, its filter integration with
 * `wc_stripe_agentic_commerce_should_sync_product`, and the save/render
 * paths on the WC product editor.
 */
class WC_Stripe_Agentic_Commerce_Product_Meta_Box_Test extends WP_UnitTestCase {

	/**
	 * Skip suite when the meta box class isn't autoloaded — keeps the
	 * suite green on hosts that aren't running `composer dump-autoload`
	 * (the class is in the agentic-commerce classmap directory but a
	 * stale autoloader would still miss the new file).
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Meta_Box' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Meta_Box class not loaded' );
		}
	}

	/**
	 * is_excluded() must be the single source of truth: a literal
	 * 'yes' meta value means excluded, everything else (default 'no',
	 * empty, missing) means included.
	 */
	public function test_is_excluded_reads_meta_value(): void {
		$product = WC_Helper_Product::create_simple_product();

		$this->assertFalse(
			WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( $product->get_id() ),
			'Default (no meta) must mean the product is included.'
		);

		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'yes' );
		$this->assertTrue( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( $product->get_id() ) );

		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'no' );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( $product->get_id() ) );

		$product->delete( true );
	}

	/**
	 * Invalid IDs (zero, negative, non-product post IDs) must safely
	 * return false rather than fatal — the filter runs on every walked
	 * product and can't assume the caller pre-validated.
	 */
	public function test_is_excluded_handles_invalid_ids(): void {
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( 0 ) );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( -1 ) );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( 999999 ) );
	}

	/**
	 * Variations inherit the parent's flag — the editor UI surfaces the
	 * checkbox on the parent only, so reading the variation's own meta
	 * would always be 'no' and let variants leak past the parent's
	 * setting during sync.
	 */
	public function test_is_excluded_for_variation_reads_parent_meta(): void {
		$parent = WC_Helper_Product::create_variation_product();
		update_post_meta( $parent->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'yes' );

		$variation_ids = $parent->get_children();
		$this->assertNotEmpty( $variation_ids );

		foreach ( $variation_ids as $variation_id ) {
			$this->assertTrue(
				WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( (int) $variation_id ),
				"Variation $variation_id must inherit the parent's exclude flag."
			);
		}

		$parent->delete( true );
	}

	/**
	 * The filter callback wired up by init() must convert an excluded
	 * product to a `false` vote on the shared should-sync filter.
	 */
	public function test_filter_callback_returns_false_when_excluded(): void {
		$meta_box = new WC_Stripe_Agentic_Commerce_Product_Meta_Box();
		$meta_box->init();

		$included = WC_Helper_Product::create_simple_product();
		$excluded = WC_Helper_Product::create_simple_product();
		update_post_meta( $excluded->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'yes' );

		$this->assertTrue( apply_filters( 'wc_stripe_agentic_commerce_should_sync_product', true, $included ) );
		$this->assertFalse( apply_filters( 'wc_stripe_agentic_commerce_should_sync_product', true, $excluded ) );

		remove_filter( 'wc_stripe_agentic_commerce_should_sync_product', [ $meta_box, 'filter_should_sync_product' ], 10 );
		$included->delete( true );
		$excluded->delete( true );
	}

	/**
	 * When an earlier callback already voted false, ours must respect
	 * that decision — never resurrect a product another adapter has
	 * already excluded.
	 */
	public function test_filter_callback_respects_prior_false_vote(): void {
		$meta_box = new WC_Stripe_Agentic_Commerce_Product_Meta_Box();
		$product  = WC_Helper_Product::create_simple_product();

		$this->assertFalse( $meta_box->filter_should_sync_product( false, $product ) );

		$product->delete( true );
	}

	/**
	 * save_meta() must reject calls without a valid WC meta nonce —
	 * the action it hooks is public and a plugin or CLI call could
	 * fire it without WC's save-post nonce check.
	 */
	public function test_save_meta_rejects_missing_nonce(): void {
		$product = WC_Helper_Product::create_simple_product();
		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'no' );

		$_POST = [
			WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY => 'yes',
		];

		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->save_meta( $product->get_id() );

		$this->assertSame(
			'no',
			get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, true ),
			'A nonce-less save must not persist any new value.'
		);

		$_POST = [];
		$product->delete( true );
	}

	/**
	 * With a valid nonce and 'yes' in POST the meta flips on; with the
	 * key absent (unchecked checkbox) it flips back to 'no'. A tampered
	 * `value="yes-but-not-quite"` is rejected because we strict-compare
	 * to the literal 'yes' string.
	 */
	public function test_save_meta_persists_valid_input(): void {
		$product = WC_Helper_Product::create_simple_product();
		$meta    = new WC_Stripe_Agentic_Commerce_Product_Meta_Box();

		$_POST = [
			'woocommerce_meta_nonce'                              => wp_create_nonce( 'woocommerce_save_data' ),
			WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY => 'yes',
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 'yes', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, true ) );

		// Unchecked checkboxes send no key — the saver must flip the meta back to 'no'.
		$_POST = [
			'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ),
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 'no', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, true ) );

		// Tampered value: anything other than the literal 'yes' must collapse to 'no'.
		$_POST = [
			'woocommerce_meta_nonce'                              => wp_create_nonce( 'woocommerce_save_data' ),
			WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY => 'forged',
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 'no', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, true ) );

		$_POST = [];
		$product->delete( true );
	}

	/**
	 * render_checkbox() must emit a checkbox using WC's helper — guards
	 * against a refactor that swaps in raw HTML and silently drops the
	 * `desc_tip` styling that ai-storefront and the surrounding
	 * Inventory checkboxes use.
	 */
	public function test_render_checkbox_outputs_checkbox_field(): void {
		// `woocommerce_wp_checkbox` lives in WC's admin meta-box helpers
		// (`includes/admin/wc-meta-box-functions.php`), which the test
		// bootstrap doesn't load. Pull it in explicitly so the render
		// path actually executes — otherwise the early `function_exists`
		// guard in `render_checkbox()` returns silently and the test
		// would assert against empty output.
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$meta_box_helpers = WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
			if ( file_exists( $meta_box_helpers ) ) {
				require_once $meta_box_helpers;
			}
		}

		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$this->markTestSkipped( 'woocommerce_wp_checkbox helper not available.' );
		}

		// The helper reads `$thepostid`/`$post` to pull the existing meta
		// value — seed them with a real product so it doesn't throw.
		$product = WC_Helper_Product::create_simple_product();
		global $thepostid, $post;
		$thepostid = $product->get_id();
		$post      = get_post( $thepostid );

		ob_start();
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->render_checkbox();
		$output = ob_get_clean();

		$this->assertStringContainsString( WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );

		$thepostid = null;
		$post      = null;
		$product->delete( true );
	}
}
