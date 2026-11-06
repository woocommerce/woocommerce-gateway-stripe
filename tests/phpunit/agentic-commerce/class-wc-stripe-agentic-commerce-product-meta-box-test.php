<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Meta_Box
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Covers the per-product exclude toggle's editor UI: render and save paths.
 *
 * The flag storage and should-sync filter are covered in
 * {@see WC_Stripe_Agentic_Commerce_Product_Exclusion_Test}.
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
	 * Resolve the protected immediate-resync action name for scheduling assertions.
	 */
	private function immediate_action(): string {
		return WC_Stripe_Test_Helper::get_class_const_value(
			WC_Stripe_Agentic_Commerce_Integration::class,
			'IMMEDIATE_SYNC_ACTION',
			'string'
		);
	}

	/**
	 * save_meta() rejects calls without a valid WC meta nonce.
	 */
	public function test_save_meta_rejects_missing_nonce(): void {
		$product = WC_Helper_Product::create_simple_product();
		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), 'no' );

		$_POST = [
			WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() => 'yes',
		];

		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->save_meta( $product->get_id() );

		$this->assertSame(
			'no',
			get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ),
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
			'woocommerce_meta_nonce'                                     => wp_create_nonce( 'woocommerce_save_data' ),
			WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() => 'yes',
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 'yes', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ) );

		// Unchecked checkboxes send no key — the saver must flip the meta back to 'no'.
		$_POST = [
			'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ),
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 'no', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ) );

		// Tampered value: anything other than the literal 'yes' must collapse to 'no'.
		$_POST = [
			'woocommerce_meta_nonce'                                     => wp_create_nonce( 'woocommerce_save_data' ),
			WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() => 'forged',
		];
		$meta->save_meta( $product->get_id() );
		$this->assertSame( 'no', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ) );

		$_POST = [];
		$product->delete( true );
	}

	/**
	 * A real flag change enqueues an immediate resync; a no-op save does not.
	 */
	public function test_save_meta_schedules_resync_only_on_real_change(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$immediate = $this->immediate_action();
		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );

		$product = WC_Helper_Product::create_simple_product();
		$meta    = new WC_Stripe_Agentic_Commerce_Product_Meta_Box();

		// First flip on (missing meta → 'yes') must enqueue a resync.
		$_POST = [
			'woocommerce_meta_nonce'                                     => wp_create_nonce( 'woocommerce_save_data' ),
			WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() => 'yes',
		];
		$meta->save_meta( $product->get_id() );
		$this->assertNotFalse(
			as_has_scheduled_action( $immediate ),
			'A real change must enqueue an immediate resync.'
		);

		// Clear the queue, then save the same value — no new resync should enqueue.
		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		$meta->save_meta( $product->get_id() );
		$this->assertFalse(
			as_has_scheduled_action( $immediate ),
			'A no-op save must not enqueue a resync.'
		);

		// Flipping back off is a real change → enqueues again.
		$_POST = [ 'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ) ];
		$meta->save_meta( $product->get_id() );
		$this->assertNotFalse(
			as_has_scheduled_action( $immediate ),
			'Flipping the flag back off must enqueue a resync.'
		);

		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		$_POST = [];
		$product->delete( true );
	}

	/**
	 * An unsupported product type leaves exclusion meta untouched and fires no resync.
	 */
	public function test_save_meta_skips_unsupported_product_type(): void {
		$product = WC_Helper_Product::create_grouped_product();
		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), 'yes' );

		$immediate = $this->immediate_action();
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		}

		// Unsupported type → the hidden checkbox posts no key, just the nonce.
		$_POST = [ 'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ) ];
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->save_meta( $product->get_id() );

		$this->assertSame(
			'yes',
			get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ),
			'Unsupported type must not clobber existing exclusion meta.'
		);
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			$this->assertFalse(
				as_has_scheduled_action( $immediate ),
				'Unsupported type must not schedule a resync.'
			);
		}

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

		$this->assertStringContainsString( WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), $output );
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
		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), 'yes' );

		// Valid nonce, no key posted (unchecked) — would normally flip to 'no'.
		$_POST = [ 'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ) ];
		( new WC_Stripe_Agentic_Commerce_Product_Meta_Box() )->save_meta( $product->get_id() );

		$this->assertSame(
			'yes',
			get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ),
			'A save while the feature is off must leave the stored value untouched.'
		);

		$_POST = [];
		$product->delete( true );
	}
}
