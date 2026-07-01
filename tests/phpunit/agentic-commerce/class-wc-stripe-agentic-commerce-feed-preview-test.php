<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Feed_Preview
 *
 * @package WooCommerce\Stripe\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Subscription-variation fixture colocated with the test that uses it.

/**
 * Class WC_Stripe_Agentic_Commerce_Feed_Preview_Test
 *
 * Exercises the upload-free feed preview: that it walks the same product set a
 * sync would, classifies each product as included / filter-excluded / invalid,
 * and surfaces per-product validation errors with an edit link.
 */
class WC_Stripe_Agentic_Commerce_Feed_Preview_Test extends WP_UnitTestCase {

	/**
	 * Product category term ID used to satisfy the feed's category requirement.
	 *
	 * @var int
	 */
	private $category_id;

	/**
	 * Product IDs the preview query is scoped to for a deterministic walk.
	 *
	 * @var int[]
	 */
	private $scoped_ids = [];

	/**
	 * Product ID the faked `subscription` type override applies to.
	 *
	 * @var int
	 */
	private $subscription_product_id = 0;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! interface_exists( 'Automattic\\WooCommerce\\Internal\\ProductFeed\\Feed\\ProductMapperInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce ProductFeed interfaces not available (requires WooCommerce 10.5.0+)' );
		}

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Feed_Preview' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Feed_Preview class not loaded' );
		}

		// get_edit_post_link() returns an empty string unless the current user can
		// edit the product, so run these tests as an administrator.
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$term              = wp_insert_term( 'Preview Category ' . uniqid(), 'product_cat' );
		$this->category_id = is_array( $term ) ? (int) $term['term_id'] : 0;
	}

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'wc_stripe_agentic_commerce_product_query_args', [ $this, 'restrict_query' ] );
		remove_filter( 'woocommerce_product_class', [ $this, 'map_variation_to_subscription' ] );
		remove_filter( 'woocommerce_product_class', [ $this, 'map_simple_to_subscription' ] );
		remove_filter( 'woocommerce_product_type_query', [ $this, 'force_subscription_type' ] );
		remove_filter( 'woocommerce_data_stores', [ $this, 'register_subscription_variation_store' ] );
		remove_all_filters( 'woocommerce_agentic_commerce_should_sync_product' );
		parent::tearDown();
	}

	/**
	 * Load scoped variations as a subscription-variation type so the preview
	 * classifies them as subscriptions without WooCommerce Subscriptions. Pair
	 * with {@see self::register_subscription_variation_store()} — WC_Product loads
	 * its data store by type, and an unregistered type aborts construction.
	 *
	 * @param string $classname    Resolved product class.
	 * @param string $product_type Product type slug being instantiated.
	 * @return string
	 */
	public function map_variation_to_subscription( $classname, $product_type ): string {
		return 'variation' === $product_type ? WC_Stripe_Test_Subscription_Variation::class : $classname;
	}

	/**
	 * Load `subscription`-typed products as a stand-in class so a faked
	 * standalone subscription reports the type the preview buckets on.
	 *
	 * @param string $classname    Resolved product class.
	 * @param string $product_type Product type slug being instantiated.
	 * @return string
	 */
	public function map_simple_to_subscription( $classname, $product_type ): string {
		return 'subscription' === $product_type ? WC_Stripe_Test_Subscription::class : $classname;
	}

	/**
	 * Force the faked product to resolve as the `subscription` type, bypassing the
	 * data store's term lookup.
	 *
	 * @param string|false $override   Existing override, if any.
	 * @param int          $product_id Product being resolved.
	 * @return string|false
	 */
	public function force_subscription_type( $override, $product_id ) {
		return $product_id === $this->subscription_product_id ? 'subscription' : $override;
	}

	/**
	 * Point the synthetic `product-subscription_variation` data store at the core
	 * variation store so the faked subscription variations can be read.
	 *
	 * @param array $stores Registered data stores.
	 * @return array
	 */
	public function register_subscription_variation_store( $stores ): array {
		$stores['product-subscription_variation'] = 'WC_Product_Variation_Data_Store_CPT';
		return $stores;
	}

	/**
	 * Restrict the feed query to the scoped product IDs so the walk is
	 * deterministic regardless of other products in the test database.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function restrict_query( $args ): array {
		$args['include'] = $this->scoped_ids;
		return $args;
	}

	/**
	 * Scope the preview to a fixed set of product IDs.
	 *
	 * @param int[] $ids Product IDs.
	 * @return void
	 */
	private function scope_to( array $ids ): void {
		$this->scoped_ids = $ids;
		add_filter( 'wc_stripe_agentic_commerce_product_query_args', [ $this, 'restrict_query' ] );
	}

	/**
	 * Create a published simple product that passes feed validation (priced and
	 * categorised; brand/MPN/image are auto-filled by the mapper).
	 *
	 * @return WC_Product_Simple
	 */
	private function create_valid_product(): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Valid Product' );
		$product->set_regular_price( '19.99' );
		$product->set_category_ids( [ $this->category_id ] );
		$product->set_status( 'publish' );
		$product->save();
		return $product;
	}

	/**
	 * Create a published simple product with no price, which fails the feed's
	 * "price required" rule. (A missing category isn't enough — WooCommerce
	 * assigns the default "Uncategorized" term, which satisfies the category
	 * rule.)
	 *
	 * @param string $name Product name.
	 * @return WC_Product_Simple
	 */
	private function create_invalid_product( string $name = 'Invalid Product' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_category_ids( [ $this->category_id ] );
		$product->set_status( 'publish' );
		$product->save();
		return $product;
	}

	/**
	 * generate() tallies included, filter-excluded, and invalid products, and
	 * the four counts reconcile against the total scanned.
	 *
	 * @return void
	 */
	public function test_generate_reports_included_excluded_and_invalid_counts(): void {
		$valid    = $this->create_valid_product();
		$invalid  = $this->create_invalid_product();
		$excluded = $this->create_valid_product();

		// Exclude one product via the visibility filter — a merchant choice, not
		// a validation failure.
		add_filter(
			'woocommerce_agentic_commerce_should_sync_product',
			static function ( $should_sync, $product ) use ( $excluded ) {
				return $product->get_id() === $excluded->get_id() ? false : $should_sync;
			},
			10,
			2
		);

		$this->scope_to( [ $valid->get_id(), $invalid->get_id(), $excluded->get_id() ] );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertSame( 3, $preview['total_count'] );
		$this->assertSame( 1, $preview['included_count'] );
		$this->assertSame( 1, $preview['excluded_count'] );
		$this->assertSame( 1, $preview['invalid_count'] );
		$this->assertSame(
			$preview['total_count'],
			$preview['included_count'] + $preview['excluded_count'] + $preview['invalid_count']
		);
	}

	/**
	 * Invalid products are listed with their name, a non-empty error list, and
	 * an edit link; excluded and valid products are not listed.
	 *
	 * @return void
	 */
	public function test_invalid_product_listed_with_name_errors_and_edit_link(): void {
		$invalid = $this->create_invalid_product( 'Needs A Category' );
		$valid   = $this->create_valid_product();

		$this->scope_to( [ $invalid->get_id(), $valid->get_id() ] );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertCount( 1, $preview['validation_errors'] );

		$entry = $preview['validation_errors'][0];
		$this->assertSame( $invalid->get_id(), $entry['product_id'] );
		$this->assertSame( 'Needs A Category', $entry['product_name'] );
		$this->assertNotEmpty( $entry['errors'] );
		$this->assertStringContainsString(
			(string) $invalid->get_id(),
			$entry['edit_link'],
			'Edit link should target the failing product.'
		);
		$this->assertSame( 0, $preview['truncated'] );
	}

	/**
	 * A filter-excluded product is counted as excluded and never appears in the
	 * validation error list.
	 *
	 * @return void
	 */
	public function test_excluded_product_is_not_reported_as_error(): void {
		$excluded = $this->create_invalid_product( 'Hidden From Agents' );

		add_filter( 'woocommerce_agentic_commerce_should_sync_product', '__return_false' );

		$this->scope_to( [ $excluded->get_id() ] );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertSame( 1, $preview['excluded_count'] );
		$this->assertSame( 0, $preview['invalid_count'] );
		$this->assertSame( 0, $preview['included_count'] );
		$this->assertEmpty( $preview['validation_errors'] );
	}

	/**
	 * The detail limit caps the returned error list and rolls the remainder into
	 * the truncated count, while invalid_count still reflects every failure.
	 *
	 * @return void
	 */
	public function test_detail_limit_truncates_error_list(): void {
		$ids = [];
		for ( $i = 0; $i < 4; $i++ ) {
			$ids[] = $this->create_invalid_product( "Invalid {$i}" )->get_id();
		}

		$this->scope_to( $ids );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate( 2 );

		$this->assertSame( 4, $preview['invalid_count'] );
		$this->assertCount( 2, $preview['validation_errors'] );
		$this->assertSame( 2, $preview['truncated'] );
	}

	/**
	 * Subscription products are bucketed under the `subscriptions` reason, not
	 * lumped with merchant-driven exclusions.
	 *
	 * @return void
	 */
	public function test_excluded_breakdown_counts_subscriptions(): void {
		if ( ! class_exists( 'WC_Helper_Product' ) || ! method_exists( 'WC_Helper_Product', 'create_variation_product' ) ) {
			$this->markTestSkipped( 'WC_Helper_Product::create_variation_product not available' );
		}

		$variable      = WC_Helper_Product::create_variation_product();
		$variation_ids = $variable->get_children();
		$this->assertNotEmpty( $variation_ids );

		add_filter( 'woocommerce_data_stores', [ $this, 'register_subscription_variation_store' ] );
		add_filter( 'woocommerce_product_class', [ $this, 'map_variation_to_subscription' ], 10, 2 );
		$this->scope_to( $variation_ids );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertSame( count( $variation_ids ), $preview['excluded_count'] );
		$this->assertSame( count( $variation_ids ), $preview['excluded_breakdown']['subscriptions'] );
		$this->assertSame( 0, $preview['excluded_breakdown']['filtered'] );
	}

	/**
	 * A standalone subscription (type `subscription`), dropped from the sync query
	 * by type, is still walked by the preview and bucketed under `subscriptions`.
	 *
	 * @return void
	 */
	public function test_excluded_breakdown_counts_standalone_subscriptions(): void {
		// Fake a standalone subscription without WooCommerce Subscriptions. The
		// `subscription` term makes the widened query return it (the default query
		// would not); `woocommerce_product_type_query` forces the loaded type (the
		// data store would resolve the unregistered term to `simple`) and
		// `woocommerce_product_class` supplies a backing class.
		wp_insert_term( 'subscription', 'product_type' );
		add_filter( 'woocommerce_product_type_query', [ $this, 'force_subscription_type' ], 10, 2 );
		add_filter( 'woocommerce_product_class', [ $this, 'map_simple_to_subscription' ], 10, 2 );

		$product                       = $this->create_valid_product();
		$this->subscription_product_id = $product->get_id();
		wp_set_object_terms( $product->get_id(), 'subscription', 'product_type' );

		$this->scope_to( [ $product->get_id() ] );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertSame( 1, $preview['excluded_count'] );
		$this->assertSame( 1, $preview['excluded_breakdown']['subscriptions'] );
		$this->assertSame( 0, $preview['excluded_breakdown']['filtered'] );
	}

	/**
	 * A non-subscription product excluded via should_sync_product() is bucketed
	 * under `filtered`, not `subscriptions`.
	 *
	 * @return void
	 */
	public function test_excluded_breakdown_counts_filtered_products(): void {
		$excluded = $this->create_valid_product();

		add_filter( 'woocommerce_agentic_commerce_should_sync_product', '__return_false' );
		$this->scope_to( [ $excluded->get_id() ] );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertSame( 1, $preview['excluded_count'] );
		$this->assertSame( 1, $preview['excluded_breakdown']['filtered'] );
		$this->assertSame( 0, $preview['excluded_breakdown']['subscriptions'] );
	}

	/**
	 * The scan limit caps how many products the synchronous preview walks and
	 * flags the result as partial, so a large catalog can't run past the request
	 * time limit. Products beyond the cap are never counted.
	 *
	 * @return void
	 */
	public function test_scan_limit_caps_the_walk_and_flags_partial_result(): void {
		$ids = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->create_valid_product()->get_id();
		}

		$this->scope_to( $ids );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate( WC_Stripe_Agentic_Commerce_Feed_Preview::DEFAULT_DETAIL_LIMIT, 2 );

		$this->assertSame( 2, $preview['total_count'] );
		$this->assertTrue( $preview['scan_limited'] );
	}

	/**
	 * A walk that finishes within the scan limit reports a complete result.
	 *
	 * @return void
	 */
	public function test_complete_walk_is_not_flagged_as_partial(): void {
		$this->scope_to( [ $this->create_valid_product()->get_id() ] );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertFalse( $preview['scan_limited'] );
	}

	/**
	 * A failing variation links to its parent product's edit screen, since
	 * variations are edited there rather than on their own post.
	 *
	 * @return void
	 */
	public function test_variation_error_links_to_parent_product(): void {
		if ( ! class_exists( 'WC_Helper_Product' ) || ! method_exists( 'WC_Helper_Product', 'create_variation_product' ) ) {
			$this->markTestSkipped( 'WC_Helper_Product::create_variation_product not available' );
		}

		$variable      = WC_Helper_Product::create_variation_product();
		$variation_ids = $variable->get_children();
		$this->assertNotEmpty( $variation_ids );

		// Strip each variation's price so it fails the feed's price requirement
		// and lands in the validation error list.
		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			$variation->set_regular_price( '' );
			$variation->set_price( '' );
			$variation->save();
		}

		$this->scope_to( $variation_ids );

		$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

		$this->assertNotEmpty( $preview['validation_errors'] );
		foreach ( $preview['validation_errors'] as $entry ) {
			$this->assertStringContainsString(
				'post=' . $variable->get_id() . '&',
				$entry['edit_link'],
				'Variation edit link should point to the parent product.'
			);
		}
	}

	/**
	 * A configured zone with no flat-rate method surfaces as a shipping warning.
	 *
	 * @return void
	 */
	public function test_preview_flags_zone_without_flat_rate(): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Preview No-Flat-Rate Zone' );
		$zone->save();

		$product = $this->create_valid_product();
		$this->scope_to( [ $product->get_id() ] );

		try {
			$preview = ( new WC_Stripe_Agentic_Commerce_Feed_Preview() )->generate();

			$joined = implode( "\n", $preview['shipping_warnings'] );
			$this->assertStringContainsString( 'Preview No-Flat-Rate Zone', $joined );
		} finally {
			$zone->delete();
		}
	}
}

/**
 * Minimal subscription-variation stand-in: shares the `product_variation` post
 * type the feed walks, differing only in the type slug the mapper checks.
 */
class WC_Stripe_Test_Subscription_Variation extends WC_Product_Variation {

	/**
	 * Report the subscription-variation type the feed excludes by default.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'subscription_variation';
	}
}

/**
 * Minimal standalone-subscription stand-in: a simple product whose `get_type()`
 * reports `subscription`, returned by the preview's widened query via its term.
 */
class WC_Stripe_Test_Subscription extends WC_Product_Simple {

	/**
	 * Report the standalone subscription type the feed excludes by default.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'subscription';
	}
}
