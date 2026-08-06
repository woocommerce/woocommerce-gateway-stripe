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
	 * Register the watcher and count the convergence action instead of letting it
	 * reach Action Scheduler, which isn't loaded in the unit suite.
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
	 * Password-protecting an already-synced product must converge Stripe's catalog.
	 *
	 * Without this the product only drops out on the next scheduled full sync, and
	 * the inventory tracker's archive path bails on the same predicate — so a later
	 * trash never reaches Stripe either.
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
	 * An edit that doesn't cross the eligibility boundary must NOT enqueue a
	 * full-catalog resync. Reacting to raw property changes instead would kick off
	 * a full sync on every ordinary price edit — exactly the work the inventory
	 * tracker's delta batching exists to avoid.
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
	 * Re-including a product converges too: it has to be pushed back into Stripe's
	 * catalog, not just stop being suppressed.
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
	 * The watcher must stay inert while the merchant hasn't switched Agentic
	 * Commerce on, so stores that never opted in do no extra work.
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
	 * Repeating a save that doesn't change eligibility must not re-fire: the marker
	 * is what makes the watcher idempotent across the several hooks it listens on.
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
}
