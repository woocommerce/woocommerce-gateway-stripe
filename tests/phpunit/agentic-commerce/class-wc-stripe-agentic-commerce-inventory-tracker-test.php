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
		remove_all_filters( 'wc_stripe_is_agentic_commerce_enabled' );
		remove_all_filters( 'wc_stripe_agentic_commerce_files_api_pre_request' );
		remove_all_filters( 'pre_http_request' );

		// Remove any action hooks registered by this test's sut to prevent leaking into subsequent tests.
		if ( isset( $this->sut ) ) {
			remove_action( 'woocommerce_product_set_stock', [ $this->sut, 'track_stock_change' ] );
			remove_action( 'woocommerce_variation_set_stock', [ $this->sut, 'track_stock_change' ] );
			remove_action( WC_Stripe_Agentic_Commerce_Inventory_Tracker::SCHEDULED_ACTION, [ $this->sut, 'sync_inventory' ] );
		}

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
		$this->assertEquals( 'wc_stripe_agentic_commerce_sync_inventory', WC_Stripe_Agentic_Commerce_Inventory_Tracker::SCHEDULED_ACTION );
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
		update_option( 'woocommerce_stripe_settings', [ 'secret_key' => 'sk_test_fake' ] );

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
		update_option( 'woocommerce_stripe_settings', [ 'secret_key' => 'sk_test_fake' ] );

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
		update_option( 'woocommerce_stripe_settings', [ 'secret_key' => 'sk_test_fake' ] );

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
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $quantity );
		$product->save();

		return $product;
	}
}
