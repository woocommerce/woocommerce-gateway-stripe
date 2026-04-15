<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Inventory_Tracker
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Inventory_Tracker_Test
 *
 * Tests incremental inventory update tracking and CSV feed generation.
 */
class WC_Stripe_Agentic_Commerce_Inventory_Tracker_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Stripe_Agentic_Commerce_Inventory_Tracker
	 */
	private $sut;

	/**
	 * Setup test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce FeedInterface not available (requires WooCommerce 10.5.0+)' );
		}

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Inventory_Tracker' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Inventory_Tracker class not loaded' );
		}

		delete_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION );
		$this->sut = new WC_Stripe_Agentic_Commerce_Inventory_Tracker();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION );
		WC_Stripe_API::set_secret_key( '' );
		remove_all_filters( 'wc_stripe_is_agentic_commerce_enabled' );
		remove_all_filters( 'wc_stripe_agentic_commerce_files_api_pre_request' );
		remove_all_filters( 'pre_http_request' );

		// Remove any action hooks registered by this test's sut to prevent leaking into subsequent tests.
		if ( isset( $this->sut ) ) {
			remove_action( 'woocommerce_product_set_stock', [ $this->sut, 'track_stock_change' ] );
			remove_action( 'woocommerce_variation_set_stock', [ $this->sut, 'track_stock_change' ] );
			remove_action( WC_Stripe_Agentic_Commerce_Inventory_Tracker::SCHEDULED_ACTION, [ $this->sut, 'sync_inventory' ] );
			remove_action( 'before_delete_post', [ $this->sut, 'maybe_track_product_archive' ] );
			remove_action( 'wp_trash_post', [ $this->sut, 'maybe_track_product_archive' ] );
			remove_action( 'untrash_post', [ $this->sut, 'maybe_cancel_pending_archive' ] );
			remove_action( WC_Stripe_Agentic_Commerce_Inventory_Tracker::ARCHIVE_SCHEDULED_ACTION, [ $this->sut, 'sync_archives' ] );
		}

		// Reset the static secret key cache to prevent leaking into subsequent tests.
		WC_Stripe_API::set_secret_key( '' );

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * Test class constants are defined correctly.
	 *
	 * @return void
	 */
	public function test_constants() {
		$this->assertEquals( 'wc_stripe_agentic_pending_inventory', WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION );
		$this->assertEquals( 'wc_stripe_agentic_pending_archives', WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION );
		$this->assertEquals( 'wc_stripe_agentic_commerce_sync_inventory', WC_Stripe_Agentic_Commerce_Inventory_Tracker::SCHEDULED_ACTION );
		$this->assertEquals( 'wc_stripe_agentic_commerce_sync_archives', WC_Stripe_Agentic_Commerce_Inventory_Tracker::ARCHIVE_SCHEDULED_ACTION );
		$this->assertEquals( 1000, WC_Stripe_Agentic_Commerce_Inventory_Tracker::MAX_PENDING_UPDATES );
		$this->assertEquals( 60, WC_Stripe_Agentic_Commerce_Inventory_Tracker::BATCH_DELAY_SECONDS );
	}

	// -------------------------------------------------------------------------
	// register_hooks
	// -------------------------------------------------------------------------

	/**
	 * Test register_hooks attaches expected WooCommerce stock change hooks.
	 *
	 * @return void
	 */
	public function test_register_hooks_attaches_stock_hooks() {
		$this->sut->register_hooks();

		$this->assertNotFalse( has_action( 'woocommerce_product_set_stock', [ $this->sut, 'track_stock_change' ] ) );
		$this->assertNotFalse( has_action( 'woocommerce_variation_set_stock', [ $this->sut, 'track_stock_change' ] ) );
	}

	/**
	 * Test register_hooks attaches the sync action handler.
	 *
	 * @return void
	 */
	public function test_register_hooks_attaches_sync_action() {
		$this->sut->register_hooks();

		$this->assertNotFalse(
			has_action(
				WC_Stripe_Agentic_Commerce_Inventory_Tracker::SCHEDULED_ACTION,
				[ $this->sut, 'sync_inventory' ]
			)
		);
	}

	/**
	 * Test register_hooks attaches archive hooks.
	 *
	 * Uses before_delete_post and wp_trash_post (WordPress-level hooks) rather than
	 * the WooCommerce data-store hooks, which only fire through the REST API.
	 *
	 * @return void
	 */
	public function test_register_hooks_attaches_archive_hooks() {
		$this->sut->register_hooks();

		$this->assertNotFalse( has_action( 'before_delete_post', [ $this->sut, 'maybe_track_product_archive' ] ) );
		$this->assertNotFalse( has_action( 'wp_trash_post', [ $this->sut, 'maybe_track_product_archive' ] ) );
		$this->assertNotFalse( has_action( 'untrash_post', [ $this->sut, 'maybe_cancel_pending_archive' ] ) );
		$this->assertNotFalse(
			has_action(
				WC_Stripe_Agentic_Commerce_Inventory_Tracker::ARCHIVE_SCHEDULED_ACTION,
				[ $this->sut, 'sync_archives' ]
			)
		);
	}

	// -------------------------------------------------------------------------
	// maybe_track_product_archive
	// -------------------------------------------------------------------------

	/**
	 * Test that maybe_track_product_archive tracks a product.
	 *
	 * @return void
	 */
	public function test_maybe_track_product_archive_tracks_product() {
		$product = $this->create_simple_product_with_stock( 5 );

		$this->sut->maybe_track_product_archive( $product->get_id() );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertArrayHasKey( $product->get_id(), $pending );
	}

	/**
	 * Test that maybe_track_product_archive ignores non-product post types.
	 *
	 * @return void
	 */
	public function test_maybe_track_product_archive_ignores_non_products() {
		$page_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );

		$this->sut->maybe_track_product_archive( $page_id );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertEmpty( $pending );
	}

	/**
	 * Test that maybe_track_product_archive tracks a product variation.
	 *
	 * @return void
	 */
	public function test_maybe_track_product_archive_tracks_variation() {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Variable Product' );
		$parent->set_status( 'publish' );
		$parent->set_regular_price( '29.99' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '29.99' );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 5 );
		$variation->save();

		$this->sut->maybe_track_product_archive( $variation->get_id() );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertArrayHasKey( $variation->get_id(), $pending );
		$this->assertEquals( 'out_of_stock', $pending[ $variation->get_id() ]['availability'] );
	}

	// -------------------------------------------------------------------------
	// maybe_cancel_pending_archive
	// -------------------------------------------------------------------------

	/**
	 * Test that restoring a trashed product removes it from the pending archives queue.
	 *
	 * @return void
	 */
	public function test_maybe_cancel_pending_archive_removes_product() {
		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_product_archive( $product );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertArrayHasKey( $product->get_id(), $pending );

		$this->sut->maybe_cancel_pending_archive( $product->get_id() );

		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertArrayNotHasKey( $product->get_id(), $after );
	}

	/**
	 * Test that restoring a product preserves other pending archives.
	 *
	 * @return void
	 */
	public function test_maybe_cancel_pending_archive_preserves_other_entries() {
		$product_a = $this->create_simple_product_with_stock( 5 );
		$product_b = $this->create_simple_product_with_stock( 10 );

		$this->sut->track_product_archive( $product_a );
		$this->sut->track_product_archive( $product_b );

		$this->sut->maybe_cancel_pending_archive( $product_a->get_id() );

		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertArrayNotHasKey( $product_a->get_id(), $after );
		$this->assertArrayHasKey( $product_b->get_id(), $after );
	}

	/**
	 * Test that maybe_cancel_pending_archive ignores non-product post types.
	 *
	 * @return void
	 */
	public function test_maybe_cancel_pending_archive_ignores_non_products() {
		$page_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );

		// No error should occur even with no pending archives.
		$this->sut->maybe_cancel_pending_archive( $page_id );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertEmpty( $pending );
	}

	// -------------------------------------------------------------------------
	// track_stock_change
	// -------------------------------------------------------------------------

	/**
	 * Data provider for stock quantity scenarios.
	 *
	 * @return array
	 */
	public function stock_quantity_provider(): array {
		return [
			'positive quantity' => [ 10 ],
			'zero quantity'     => [ 0 ],
		];
	}

	/**
	 * Test that a stock change is stored in the pending updates option.
	 *
	 * @dataProvider stock_quantity_provider
	 * @param int $quantity Stock quantity to test.
	 * @return void
	 */
	public function test_track_stock_change_stores_pending_update( int $quantity ) {
		$product = $this->create_simple_product_with_stock( $quantity );

		$this->sut->track_stock_change( $product );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );

		$this->assertArrayHasKey( $product->get_id(), $pending );
		$this->assertEquals( $product->get_id(), $pending[ $product->get_id() ]['sku_id'] );
		$this->assertEquals( $quantity, $pending[ $product->get_id() ]['quantity'] );
		$this->assertArrayHasKey( 'timestamp', $pending[ $product->get_id() ] );
	}

	/**
	 * Test that multiple stock changes are batched into a single option.
	 *
	 * @return void
	 */
	public function test_track_stock_change_batches_multiple_products() {
		$product_a = $this->create_simple_product_with_stock( 5 );
		$product_b = $this->create_simple_product_with_stock( 20 );

		$this->sut->track_stock_change( $product_a );
		$this->sut->track_stock_change( $product_b );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );

		$this->assertCount( 2, $pending );
		$this->assertArrayHasKey( $product_a->get_id(), $pending );
		$this->assertArrayHasKey( $product_b->get_id(), $pending );
	}

	/**
	 * Test that a repeated change for the same product overwrites the previous entry.
	 *
	 * @return void
	 */
	public function test_track_stock_change_overwrites_earlier_update_for_same_product() {
		$product = $this->create_simple_product_with_stock( 10 );

		$this->sut->track_stock_change( $product );

		// Simulate a second stock change for the same product.
		$product->set_stock_quantity( 3 );
		$product->save();
		$this->sut->track_stock_change( $product );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );

		$this->assertCount( 1, $pending );
		$this->assertEquals( 3, $pending[ $product->get_id() ]['quantity'] );
	}

	/**
	 * Test that no new entries are added once the MAX_PENDING_UPDATES threshold is reached.
	 *
	 * @return void
	 */
	public function test_track_stock_change_stops_accumulating_at_threshold() {
		$max = WC_Stripe_Agentic_Commerce_Inventory_Tracker::MAX_PENDING_UPDATES;
		// Create the product first so we know its real DB ID before building the pre-fill.
		// This prevents the product's auto-increment ID from coinciding with a pre-filled key,
		// which would make assertArrayNotHasKey fail for the wrong reason.
		$extra_product = $this->create_simple_product_with_stock( 99 );
		$product_id    = $extra_product->get_id();

		// Pre-fill with MAX_PENDING_UPDATES entries, deliberately skipping the extra product's ID.
		$pending       = [];
		$pending_count = 0;
		$i             = 1;
		while ( $pending_count < $max ) {
			if ( $i !== $product_id ) {
				$pending[ $i ] = [
					'sku_id'    => $i,
					'quantity'  => $i,
					'timestamp' => time(),
				];
				++$pending_count;
			}
			++$i;
		}
		update_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, $pending );

		$this->sut->track_stock_change( $extra_product );

		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );

		// Should still be exactly MAX_PENDING_UPDATES, not MAX + 1.
		$this->assertCount( $max, $after );
		$this->assertArrayNotHasKey( $product_id, $after );
	}

	// -------------------------------------------------------------------------
	// generate_inventory_feed
	// -------------------------------------------------------------------------

	/**
	 * Test generate_inventory_feed returns null when there are no pending updates.
	 *
	 * @return void
	 */
	public function test_generate_inventory_feed_returns_null_when_no_pending() {
		$result = $this->sut->generate_inventory_feed();
		$this->assertNull( $result );
	}

	/**
	 * Test generate_inventory_feed returns a finalized feed with correct columns.
	 *
	 * @return void
	 */
	public function test_generate_inventory_feed_returns_finalized_feed() {
		$product = $this->create_simple_product_with_stock( 7 );
		$this->sut->track_stock_change( $product );

		$feed = $this->sut->generate_inventory_feed();

		$this->assertInstanceOf( WC_Stripe_Agentic_Commerce_Csv_Feed::class, $feed );
		$this->assertNotNull( $feed->get_file_path() );
		$this->assertFileExists( $feed->get_file_path() );

		wp_delete_file( $feed->get_file_path() );
	}

	/**
	 * Test generate_inventory_feed CSV contains correct sku_id and quantity columns.
	 *
	 * @return void
	 */
	public function test_generate_inventory_feed_csv_content() {
		$product = $this->create_simple_product_with_stock( 15 );
		$this->sut->track_stock_change( $product );

		$feed      = $this->sut->generate_inventory_feed();
		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );
		$lines     = array_filter( explode( "\n", trim( $content ) ) );

		// Header row + at least one data row.
		$this->assertGreaterThanOrEqual( 2, count( $lines ) );

		$header_cols = str_getcsv( array_shift( $lines ) );
		$this->assertContains( 'sku_id', $header_cols );
		$this->assertContains( 'inventory_quantity', $header_cols );

		// Verify data row contains the expected product ID and quantity.
		$data_row = str_getcsv( reset( $lines ) );
		$row      = array_combine( $header_cols, $data_row );

		$this->assertEquals( (string) $product->get_id(), $row['sku_id'] );
		$this->assertEquals( '15', $row['inventory_quantity'] );

		wp_delete_file( $file_path );
	}

	/**
	 * Test generate_inventory_feed CSV includes all pending products.
	 *
	 * @return void
	 */
	public function test_generate_inventory_feed_includes_all_pending_products() {
		$product_a = $this->create_simple_product_with_stock( 3 );
		$product_b = $this->create_simple_product_with_stock( 8 );
		$product_c = $this->create_simple_product_with_stock( 0 );

		$this->sut->track_stock_change( $product_a );
		$this->sut->track_stock_change( $product_b );
		$this->sut->track_stock_change( $product_c );

		$feed      = $this->sut->generate_inventory_feed();
		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );
		$lines     = array_values( array_filter( explode( "\n", trim( $content ) ) ) );

		// Header + 3 data rows.
		$this->assertCount( 4, $lines );

		wp_delete_file( $file_path );
	}

	// -------------------------------------------------------------------------
	// sync_inventory
	// -------------------------------------------------------------------------

	/**
	 * Test sync_inventory skips when feature flag is disabled.
	 *
	 * @return void
	 */
	public function test_sync_inventory_skips_when_feature_disabled() {
		// Explicitly disable the feature flag to verify the skip behavior.
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_false' );

		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_stock_change( $product );

		// Ensure the pending updates survive (i.e. sync did nothing).
		$this->sut->sync_inventory();

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertNotEmpty( $pending );
	}

	/**
	 * Test sync_inventory skips when there are no pending updates.
	 *
	 * @return void
	 */
	public function test_sync_inventory_skips_when_no_pending() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		// Should complete without error.
		$this->sut->sync_inventory();

		// Pending option remains empty.
		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertEmpty( $pending );
	}

	/**
	 * Test sync_inventory clears pending updates when threshold is exceeded.
	 *
	 * @return void
	 */
	public function test_sync_inventory_clears_pending_when_threshold_exceeded() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		$max     = WC_Stripe_Agentic_Commerce_Inventory_Tracker::MAX_PENDING_UPDATES;
		$pending = [];
		for ( $i = 1; $i <= $max; $i++ ) {
			$pending[ $i ] = [
				'sku_id'    => $i,
				'quantity'  => $i,
				'timestamp' => time(),
			];
		}
		update_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, $pending );

		$this->sut->sync_inventory();

		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertEmpty( $after );
	}

	/**
	 * Test sync_inventory clears pending updates after a successful upload.
	 *
	 * @return void
	 */
	public function test_sync_inventory_clears_pending_on_success() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		WC_Stripe_API::set_secret_key( 'sk_test_fake' );

		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_stock_change( $product );

		// Short-circuit the Files API upload.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_123' ];
			}
		);

		// Short-circuit the ImportSet creation.
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( false !== strpos( $url, 'import_sets' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'impset_test_456',
								'status' => 'pending',
							]
						),
					];
				}
				return $pre;
			},
			10,
			3
		);

		$this->sut->sync_inventory();

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertEmpty( $pending );
	}

	/**
	 * Test sync_inventory retains pending updates when upload fails.
	 *
	 * @return void
	 */
	public function test_sync_inventory_retains_pending_on_failure() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		WC_Stripe_API::set_secret_key( 'sk_test_fake' );

		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_stock_change( $product );

		// Make the Files API upload throw an exception.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				throw new Exception( 'Simulated upload failure' );
			}
		);

		$this->sut->sync_inventory();

		// Pending updates must be retained so the next run can retry.
		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertNotEmpty( $pending );
	}

	// -------------------------------------------------------------------------
	// Integration: full flow
	// -------------------------------------------------------------------------

	/**
	 * Test complete stock change → generate feed → upload flow.
	 *
	 * @return void
	 */
	public function test_full_flow_stock_change_to_upload() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		WC_Stripe_API::set_secret_key( 'sk_test_fake' );

		$product_a = $this->create_simple_product_with_stock( 10 );
		$product_b = $this->create_simple_product_with_stock( 0 );

		// Register hooks so WooCommerce stock actions are wired to track_stock_change/sync_inventory.
		$this->sut->register_hooks();

		// Simulate stock changes via WooCommerce hooks.
		do_action( 'woocommerce_product_set_stock', $product_a );
		do_action( 'woocommerce_variation_set_stock', $product_b );

		// Verify changes were tracked.
		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertCount( 2, $pending );

		// Short-circuit the network requests.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_999' ];
			}
		);

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( false !== strpos( $url, 'import_sets' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'impset_test_999',
								'status' => 'pending',
							]
						),
					];
				}
				return $pre;
			},
			10,
			3
		);

		// Trigger sync.
		do_action( WC_Stripe_Agentic_Commerce_Inventory_Tracker::SCHEDULED_ACTION );

		// Pending queue should be cleared.
		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertEmpty( $after );
	}

	// -------------------------------------------------------------------------
	// track_product_archive
	// -------------------------------------------------------------------------

	/**
	 * Test that a product archive is stored in the pending archives option.
	 *
	 * @return void
	 */
	public function test_track_product_archive_stores_pending_archive() {
		$product = $this->create_simple_product_with_stock( 5 );

		$this->sut->track_product_archive( $product );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );

		$this->assertArrayHasKey( $product->get_id(), $pending );
		$this->assertEquals( (string) $product->get_id(), $pending[ $product->get_id() ]['id'] );
		$this->assertEquals( 'out_of_stock', $pending[ $product->get_id() ]['availability'] );
		$this->assertArrayHasKey( 'title', $pending[ $product->get_id() ] );
		$this->assertArrayHasKey( 'description', $pending[ $product->get_id() ] );
		$this->assertArrayHasKey( 'link', $pending[ $product->get_id() ] );
		$this->assertArrayHasKey( 'price', $pending[ $product->get_id() ] );
		$this->assertArrayHasKey( 'timestamp', $pending[ $product->get_id() ] );
	}

	/**
	 * Test that tracking an archive removes the product from pending inventory updates.
	 *
	 * @return void
	 */
	public function test_track_product_archive_removes_from_pending_inventory() {
		$product = $this->create_simple_product_with_stock( 10 );

		// First track a stock change, then archive the product.
		$this->sut->track_stock_change( $product );

		$inventory_before = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertArrayHasKey( $product->get_id(), $inventory_before );

		$this->sut->track_product_archive( $product );

		$inventory_after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_UPDATES_OPTION, [] );
		$this->assertArrayNotHasKey( $product->get_id(), $inventory_after );
	}

	/**
	 * Test that multiple archives are batched into a single option.
	 *
	 * @return void
	 */
	public function test_track_product_archive_batches_multiple_products() {
		$product_a = $this->create_simple_product_with_stock( 5 );
		$product_b = $this->create_simple_product_with_stock( 10 );

		$this->sut->track_product_archive( $product_a );
		$this->sut->track_product_archive( $product_b );

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );

		$this->assertCount( 2, $pending );
		$this->assertArrayHasKey( $product_a->get_id(), $pending );
		$this->assertArrayHasKey( $product_b->get_id(), $pending );
	}

	/**
	 * Test that no new archives are added once the MAX_PENDING_UPDATES threshold is reached.
	 *
	 * @return void
	 */
	public function test_track_product_archive_stops_accumulating_at_threshold() {
		$max           = WC_Stripe_Agentic_Commerce_Inventory_Tracker::MAX_PENDING_UPDATES;
		$extra_product = $this->create_simple_product_with_stock( 1 );
		$product_id    = $extra_product->get_id();

		$pending       = [];
		$pending_count = 0;
		$i             = 1;
		while ( $pending_count < $max ) {
			if ( $i !== $product_id ) {
				$pending[ $i ] = [
					'id'        => (string) $i,
					'timestamp' => time(),
				];
				++$pending_count;
			}
			++$i;
		}
		update_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, $pending );

		$this->sut->track_product_archive( $extra_product );

		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );

		$this->assertCount( $max, $after );
		$this->assertArrayNotHasKey( $product_id, $after );
	}

	// -------------------------------------------------------------------------
	// generate_archive_feed
	// -------------------------------------------------------------------------

	/**
	 * Test generate_archive_feed returns null when there are no pending archives.
	 *
	 * @return void
	 */
	public function test_generate_archive_feed_returns_null_when_no_pending() {
		$result = $this->sut->generate_archive_feed();
		$this->assertNull( $result );
	}

	/**
	 * Test generate_archive_feed returns a finalized feed.
	 *
	 * @return void
	 */
	public function test_generate_archive_feed_returns_finalized_feed() {
		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_product_archive( $product );

		$feed = $this->sut->generate_archive_feed();

		$this->assertInstanceOf( WC_Stripe_Agentic_Commerce_Csv_Feed::class, $feed );
		$this->assertNotNull( $feed->get_file_path() );
		$this->assertFileExists( $feed->get_file_path() );

		wp_delete_file( $feed->get_file_path() );
	}

	/**
	 * Test generate_archive_feed CSV contains correct id and availability columns.
	 *
	 * @return void
	 */
	public function test_generate_archive_feed_csv_content() {
		$product = $this->create_simple_product_with_stock( 5 );
		$product->set_regular_price( '19.99' );
		$product->save();
		$this->sut->track_product_archive( $product );

		$feed      = $this->sut->generate_archive_feed();
		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );
		$lines     = array_filter( explode( "\n", trim( $content ) ) );

		// Header row + at least one data row.
		$this->assertGreaterThanOrEqual( 2, count( $lines ) );

		$header_cols = str_getcsv( array_shift( $lines ) );

		// Archive feed should use all schema columns.
		$expected_headers = WC_Stripe_Agentic_Commerce_Feed_Schema::get_csv_headers();
		$this->assertEquals( $expected_headers, $header_cols );

		// Verify data row contains the expected product data.
		$data_row = str_getcsv( reset( $lines ) );
		$row      = array_combine( $header_cols, $data_row );

		$this->assertEquals( (string) $product->get_id(), $row['id'] );
		$this->assertEquals( 'out_of_stock', $row['availability'] );
		$this->assertNotEmpty( $row['title'] );
		$this->assertNotEmpty( $row['description'] );
		$this->assertNotEmpty( $row['link'] );
		$this->assertNotEmpty( $row['price'] );
		$this->assertNotEmpty( $row['image_link'] );

		wp_delete_file( $file_path );
	}

	/**
	 * Test generate_archive_feed includes all pending products.
	 *
	 * @return void
	 */
	public function test_generate_archive_feed_includes_all_pending_products() {
		$product_a = $this->create_simple_product_with_stock( 3 );
		$product_b = $this->create_simple_product_with_stock( 8 );

		$this->sut->track_product_archive( $product_a );
		$this->sut->track_product_archive( $product_b );

		$feed      = $this->sut->generate_archive_feed();
		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );
		$lines     = array_values( array_filter( explode( "\n", trim( $content ) ) ) );

		// Header + 2 data rows.
		$this->assertCount( 3, $lines );

		wp_delete_file( $file_path );
	}

	// -------------------------------------------------------------------------
	// sync_archives
	// -------------------------------------------------------------------------

	/**
	 * Test sync_archives skips when feature flag is disabled.
	 *
	 * @return void
	 */
	public function test_sync_archives_skips_when_feature_disabled() {
		// Explicitly disable the feature flag to verify the skip behavior.
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_false' );

		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_product_archive( $product );

		$this->sut->sync_archives();

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertNotEmpty( $pending );
	}

	/**
	 * Test sync_archives skips when there are no pending archives.
	 *
	 * @return void
	 */
	public function test_sync_archives_skips_when_no_pending() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		$this->sut->sync_archives();

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertEmpty( $pending );
	}

	/**
	 * Test sync_archives clears pending archives when threshold is exceeded.
	 *
	 * @return void
	 */
	public function test_sync_archives_clears_pending_when_threshold_exceeded() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		$max     = WC_Stripe_Agentic_Commerce_Inventory_Tracker::MAX_PENDING_UPDATES;
		$pending = [];
		for ( $i = 1; $i <= $max; $i++ ) {
			$pending[ $i ] = [
				'id'        => $i,
				'timestamp' => time(),
			];
		}
		update_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, $pending );

		$this->sut->sync_archives();

		$after = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertEmpty( $after );
	}

	/**
	 * Test sync_archives clears pending archives after a successful upload.
	 *
	 * @return void
	 */
	public function test_sync_archives_clears_pending_on_success() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		WC_Stripe_API::set_secret_key( 'sk_test_fake' );

		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_product_archive( $product );

		// Short-circuit the Files API upload.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_arc_123' ];
			}
		);

		// Short-circuit the ImportSet creation.
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( false !== strpos( $url, 'import_sets' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'impset_test_arc_456',
								'status' => 'pending',
							]
						),
					];
				}
				return $pre;
			},
			10,
			3
		);

		$this->sut->sync_archives();

		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertEmpty( $pending );
	}

	/**
	 * Test sync_archives retains pending archives when upload fails.
	 *
	 * @return void
	 */
	public function test_sync_archives_retains_pending_on_failure() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		WC_Stripe_API::set_secret_key( 'sk_test_fake' );

		$product = $this->create_simple_product_with_stock( 5 );
		$this->sut->track_product_archive( $product );

		// Make the Files API upload throw an exception.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				throw new Exception( 'Simulated upload failure' );
			}
		);

		$this->sut->sync_archives();

		// Pending archives must be retained so the next run can retry.
		$pending = get_option( WC_Stripe_Agentic_Commerce_Inventory_Tracker::PENDING_ARCHIVES_OPTION, [] );
		$this->assertNotEmpty( $pending );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a simple WooCommerce product with a given stock quantity.
	 *
	 * @param int $quantity Stock quantity.
	 * @return \WC_Product_Simple
	 */
	private function create_simple_product_with_stock( int $quantity ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product ' . wp_generate_password( 4, false ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( '9.99' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $quantity );
		$product->save();

		return $product;
	}
}
