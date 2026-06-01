<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Meta_Box
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Covers the per-product exclude toggle: filter integration and save/render paths.
 */
class WC_Stripe_Agentic_Commerce_Product_Meta_Box_Test extends WP_UnitTestCase {

	/**
	 * Skip if the class isn't autoloaded; default the feature on (gate tests flip it off).
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Meta_Box' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Meta_Box class not loaded' );
		}

		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );
	}

	public function tearDown(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
		parent::tearDown();
	}

	/**
	 * is_excluded() returns true only for a literal 'yes'; everything else is included.
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
	 * Invalid IDs (zero, negative, non-product) return false rather than fatal.
	 */
	public function test_is_excluded_handles_invalid_ids(): void {
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( 0 ) );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( -1 ) );
		$this->assertFalse( WC_Stripe_Agentic_Commerce_Product_Meta_Box::is_excluded( 999999 ) );
	}

	/**
	 * Variations inherit the parent's flag (the checkbox lives on the parent).
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
	 * init()'s filter callback votes false for an excluded product.
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
	 * The callback respects a prior false vote rather than resurrecting the product.
	 */
	public function test_filter_callback_respects_prior_false_vote(): void {
		$meta_box = new WC_Stripe_Agentic_Commerce_Product_Meta_Box();
		$product  = WC_Helper_Product::create_simple_product();

		$this->assertFalse( $meta_box->filter_should_sync_product( false, $product ) );

		$product->delete( true );
	}

	/**
	 * save_meta() rejects calls without a valid WC meta nonce.
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
	 * 'yes' persists; an absent key or any non-'yes' value collapses to 'no'.
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
	 * The resync action fires only when the value actually changes, not on no-op saves.
	 */
	public function test_save_meta_schedules_resync_only_on_real_change(): void {
		$product = WC_Helper_Product::create_simple_product();
		$meta    = new WC_Stripe_Agentic_Commerce_Product_Meta_Box();

		$fired = 0;
		$bump  = function () use ( &$fired ) {
			++$fired;
		};
		add_action( 'wc_stripe_agentic_commerce_schedule_full_resync', $bump );

		// First flip on (missing meta → 'yes') must fire.
		$_POST = [
			'woocommerce_meta_nonce'                              => wp_create_nonce( 'woocommerce_save_data' ),
			WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY => 'yes',
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 1, $fired );

		// Saving again with the same value must not fire.
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 1, $fired, 'No-op save must not stack resync events.' );

		// Flipping back off must fire again.
		$_POST = [ 'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ) ];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 2, $fired );

		remove_action( 'wc_stripe_agentic_commerce_schedule_full_resync', $bump );
		$_POST = [];
		$product->delete( true );
	}

	/**
	 * An unsupported product type leaves exclusion meta untouched and fires no resync.
	 */
	public function test_save_meta_skips_unsupported_product_type(): void {
		$product = WC_Helper_Product::create_grouped_product();
		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'yes' );

		$fired = 0;
		$bump  = function () use ( &$fired ) {
			++$fired;
		};
		add_action( 'wc_stripe_agentic_commerce_schedule_full_resync', $bump );

		// Unsupported type → the hidden checkbox posts no key, just the nonce.
		$_POST = [ 'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ) ];
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->save_meta( $product->get_id() );

		$this->assertSame(
			'yes',
			get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, true ),
			'Unsupported type must not clobber existing exclusion meta.'
		);
		$this->assertSame( 0, $fired, 'Unsupported type must not schedule a resync.' );

		remove_action( 'wc_stripe_agentic_commerce_schedule_full_resync', $bump );
		$_POST = [];
		$product->delete( true );
	}

	/**
	 * render_checkbox() emits a checkbox via WC's helper.
	 */
	public function test_render_checkbox_outputs_checkbox_field(): void {
		// The bootstrap doesn't load WC's meta-box helpers; pull them in so the
		// render path runs instead of the function_exists guard returning early.
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$meta_box_helpers = WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
			if ( file_exists( $meta_box_helpers ) ) {
				require_once $meta_box_helpers;
			}
		}

		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$this->markTestSkipped( 'woocommerce_wp_checkbox helper not available.' );
		}

		// Seed $thepostid/$post (read by the helper) and $product_object (read by
		// the type gate) with a supported product so the checkbox renders.
		$product = WC_Helper_Product::create_simple_product();
		global $thepostid, $post, $product_object;
		$thepostid      = $product->get_id();
		$post           = get_post( $thepostid );
		$product_object = $product;

		ob_start();
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->render_checkbox();
		$output = ob_get_clean();

		$this->assertStringContainsString( WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );

		$thepostid      = null;
		$post           = null;
		$product_object = null;
		$product->delete( true );
	}

	/**
	 * The toggle doesn't render for an unsupported type (grouped product).
	 */
	public function test_render_checkbox_skips_unsupported_product_types(): void {
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$meta_box_helpers = WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
			if ( file_exists( $meta_box_helpers ) ) {
				require_once $meta_box_helpers;
			}
		}
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$this->markTestSkipped( 'woocommerce_wp_checkbox helper not available.' );
		}

		$product = WC_Helper_Product::create_grouped_product();
		global $thepostid, $post, $product_object;
		$thepostid      = $product->get_id();
		$post           = get_post( $thepostid );
		$product_object = $product;

		ob_start();
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->render_checkbox();
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'Unsupported product types must render nothing.' );

		$thepostid      = null;
		$post           = null;
		$product_object = null;
		$product->delete( true );
	}

	/**
	 * The toggle doesn't render while the feature is switched off in settings.
	 */
	public function test_render_checkbox_hidden_when_merchant_disabled(): void {
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$meta_box_helpers = WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
			if ( file_exists( $meta_box_helpers ) ) {
				require_once $meta_box_helpers;
			}
		}
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			$this->markTestSkipped( 'woocommerce_wp_checkbox helper not available.' );
		}

		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );

		$product = WC_Helper_Product::create_simple_product();
		global $thepostid, $post, $product_object;
		$thepostid      = $product->get_id();
		$post           = get_post( $thepostid );
		$product_object = $product;

		ob_start();
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->render_checkbox();
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'A disabled feature must render nothing.' );

		$thepostid      = null;
		$post           = null;
		$product_object = null;
		$product->delete( true );
	}

	/**
	 * save_meta() no-ops while the feature is off, preserving a stored 'yes'.
	 */
	public function test_save_meta_skips_when_merchant_disabled(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );

		$product = WC_Helper_Product::create_simple_product();
		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, 'yes' );

		// Valid nonce, no key posted (unchecked) — would normally flip to 'no'.
		$_POST = [ 'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ) ];
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->save_meta( $product->get_id() );

		$this->assertSame(
			'yes',
			get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Meta_Box::META_KEY, true ),
			'A save while the feature is off must leave the stored value untouched.'
		);

		$_POST = [];
		$product->delete( true );
	}
}
