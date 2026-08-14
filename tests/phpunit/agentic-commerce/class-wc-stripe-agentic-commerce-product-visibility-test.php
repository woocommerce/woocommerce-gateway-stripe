<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Visibility
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Covers the resync-on-transition watcher that converges Stripe's catalog when a
 * product crosses the sync-eligibility boundary.
 */
class WC_Stripe_Agentic_Commerce_Product_Visibility_Test extends WP_UnitTestCase {

	/**
	 * Number of times the convergence action fired during a test.
	 *
	 * @var int
	 */
	private int $resync_count = 0;

	/**
	 * Count the convergence action instead of letting it reach Action Scheduler.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Visibility' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Visibility class not loaded' );
		}

		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );

		$this->resync_count = 0;
		add_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $this, 'count_resync' ] );

		( new WC_Stripe_Agentic_Commerce_Product_Visibility() )->init();
	}

	/**
	 * Unhook everything this test registered so counts don't leak across cases.
	 */
	public function tearDown(): void {
		remove_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $this, 'count_resync' ] );
		remove_all_actions( 'woocommerce_update_product' );
		remove_all_actions( 'woocommerce_new_product' );
		remove_all_actions( 'post_updated' );
		remove_all_actions( 'added_post_meta' );
		remove_all_actions( 'updated_post_meta' );
		remove_all_actions( 'deleted_post_meta' );
		remove_all_filters( 'woocommerce_agentic_commerce_should_sync_product' );
		remove_all_actions( 'woocommerce_update_product_variation' );
		remove_all_actions( 'woocommerce_new_product_variation' );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );

		parent::tearDown();
	}

	/**
	 * Records a convergence request.
	 *
	 * @return void
	 */
	public function count_resync(): void {
		++$this->resync_count;
	}

	/**
	 * Password-protecting a synced product must converge Stripe's catalog.
	 *
	 * Otherwise it lingers until the next scheduled sync, and the tracker's
	 * archive path bails on the same predicate so a later trash never lands.
	 *
	 * @return void
	 */
	public function test_password_protecting_a_product_schedules_a_resync(): void {
		$product            = WC_Helper_Product::create_simple_product();
		$this->resync_count = 0;

		wp_update_post(
			[
				'ID'            => $product->get_id(),
				'post_password' => 'secret',
			]
		);

		$this->assertGreaterThan( 0, $this->resync_count );

		$product->delete( true );
	}

	/**
	 * Hiding a product from catalog and search must converge the same way.
	 *
	 * @return void
	 */
	public function test_hiding_a_product_schedules_a_resync(): void {
		$product            = WC_Helper_Product::create_simple_product();
		$this->resync_count = 0;

		$product->set_catalog_visibility( 'hidden' );
		$product->save();

		$this->assertGreaterThan( 0, $this->resync_count );

		$product->delete( true );
	}

	/**
	 * An edit that doesn't cross the boundary must NOT enqueue a resync — that is
	 * the work the tracker's delta batching exists to avoid.
	 *
	 * @return void
	 */
	public function test_ordinary_price_edit_does_not_schedule_a_resync(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();
		$this->resync_count = 0;

		$product->set_regular_price( '42.00' );
		$product->save();

		$this->assertSame( 0, $this->resync_count );

		$product->delete( true );
	}

	/**
	 * Re-including converges too: the product has to be pushed back, not just
	 * stop being suppressed.
	 *
	 * @return void
	 */
	public function test_unhiding_a_product_schedules_a_resync(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_catalog_visibility( 'hidden' );
		$product->save();

		$this->resync_count = 0;

		$product = wc_get_product( $product->get_id() );
		$product->set_catalog_visibility( 'visible' );
		$product->save();

		$this->assertGreaterThan( 0, $this->resync_count );

		$product->delete( true );
	}

	/**
	 * Stores that never opted in must do no extra work.
	 *
	 * @return void
	 */
	public function test_no_resync_when_merchant_is_not_enabled(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );

		$product            = WC_Helper_Product::create_simple_product();
		$this->resync_count = 0;

		$product->set_catalog_visibility( 'hidden' );
		$product->save();

		$this->assertSame( 0, $this->resync_count );

		$product->delete( true );
	}

	/**
	 * Writing the per-product exclude flag must converge immediately and keep the
	 * marker current, so a later ordinary edit doesn't fire a redundant resync.
	 *
	 * The exclusion surfaces write the flag AFTER every product-save hook this
	 * class watches (meta box) or with no product save at all (bulk edit), so
	 * only the meta-write hooks can observe the change.
	 *
	 * @return void
	 */
	public function test_exclude_flag_write_converges_and_keeps_marker_current(): void {
		// The exclude flag only affects should_sync_product() through the
		// exclusion class's filter, which the plugin bootstrap registers in
		// production but this suite must register itself.
		( new WC_Stripe_Agentic_Commerce_Product_Exclusion() )->init();

		$product            = WC_Helper_Product::create_simple_product();
		$this->resync_count = 0;

		WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product->get_id(), true );

		$this->assertGreaterThan( 0, $this->resync_count, 'An exclude-flag write must converge the catalog.' );

		// The marker tracking the write is observable behaviorally: an unrelated
		// edit right after must stay quiet.
		$this->resync_count = 0;
		$product            = wc_get_product( $product->get_id() );
		$product->set_regular_price( '42.00' );
		$product->save();
		$this->assertSame( 0, $this->resync_count, 'An ordinary edit after the exclusion must not re-fire.' );

		// Re-including converges again.
		WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product->get_id(), false );
		$this->assertGreaterThan( 0, $this->resync_count, 'Clearing the exclude flag must converge too.' );

		$product->delete( true );
	}

	/**
	 * The marker keeps the watcher idempotent across the hooks it listens on,
	 * several of which fire for the same save.
	 *
	 * @return void
	 */
	public function test_repeated_save_in_the_same_state_fires_once(): void {
		$product            = WC_Helper_Product::create_simple_product();
		$this->resync_count = 0;

		$product->set_catalog_visibility( 'hidden' );
		$product->save();

		$first_count = $this->resync_count;
		$this->assertGreaterThan( 0, $first_count );

		$product = wc_get_product( $product->get_id() );
		$product->set_description( 'An unrelated edit.' );
		$product->save();

		$this->assertSame( $first_count, $this->resync_count );

		$product->delete( true );
	}

	/**
	 * Variations fire their own CRUD hooks (`woocommerce_*_product_variation`),
	 * so an eligibility flip on a variation must converge like a top-level
	 * product's.
	 *
	 * @return void
	 */
	public function test_variation_eligibility_flip_schedules_a_resync(): void {
		$variable     = WC_Helper_Product::create_variation_product();
		$children     = $variable->get_children();
		$variation_id = (int) $children[0];

		// Make eligibility depend on variation-level data, since the built-in
		// predicates (password, catalog visibility) live on the parent.
		add_filter(
			'woocommerce_agentic_commerce_should_sync_product',
			function ( $should_sync, $product ) use ( $variation_id ) {
				if ( $product->get_id() === $variation_id && (float) $product->get_regular_price() > 100 ) {
					return false;
				}
				return $should_sync;
			},
			10,
			2
		);

		$this->resync_count = 0;

		$variation = wc_get_product( $variation_id );
		$variation->set_regular_price( '150.00' );
		$variation->save();

		$this->assertGreaterThan( 0, $this->resync_count );

		$variable->delete( true );
	}

	/**
	 * A parent flip must bring the variations' markers current too: they
	 * inherit the parent's password/visibility, the scheduled full resync
	 * already covers them, and a stale marker would make the next unrelated
	 * variation save fire a redundant full resync.
	 *
	 * @return void
	 */
	public function test_parent_flip_syncs_variation_markers(): void {
		$variable           = WC_Helper_Product::create_variation_product();
		$this->resync_count = 0;

		$variable->set_catalog_visibility( 'hidden' );
		$variable->save();

		$this->assertGreaterThan( 0, $this->resync_count, 'Hiding the parent must converge the catalog.' );

		$this->resync_count = 0;
		$children           = $variable->get_children();
		$variation          = wc_get_product( (int) $children[0] );
		$variation->set_regular_price( '19.00' );
		$variation->save();

		$this->assertSame( 0, $this->resync_count, 'A variation save with unchanged eligibility must stay quiet.' );

		$variable->delete( true );
	}
}
