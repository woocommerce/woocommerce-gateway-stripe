<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Loader.
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Product_Loader_Test
 *
 * Covers the secondary-query splice for both paginated and non-paginated
 * `get_products()` calls, including the page-1 state initialization, the
 * straddle page where parent's tail is padded with custom prefix, pure
 * post-straddle pages, and state reset across walks.
 */
class WC_Stripe_Agentic_Commerce_Product_Loader_Test extends WP_UnitTestCase {

	/**
	 * @var int[]
	 */
	private array $created_products = [];

	/**
	 * Wipe pre-existing products so query results are deterministic.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$existing = wc_get_products(
			[
				'status' => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'type'   => [
					\Automattic\WooCommerce\Enums\ProductType::SIMPLE,
					\Automattic\WooCommerce\Enums\ProductType::VARIATION,
					\Automattic\WooCommerce\Enums\ProductType::VARIABLE,
				],
				'limit'  => -1,
				'return' => 'ids',
			]
		);
		foreach ( $existing as $product_id ) {
			WC_Helper_Product::delete_product( $product_id );
		}
	}

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Loader' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Loader class not loaded' );
		}
	}

	public function tearDown(): void {
		foreach ( $this->created_products as $product_id ) {
			wp_delete_post( $product_id, true );
		}
		$this->created_products = [];

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Short-circuit branches
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider provider_absent_or_empty_custom_filter
	 */
	public function test_returns_parent_result_when_custom_filter_absent_or_empty( $value ): void {
		$simple_ids = $this->create_simple_products( 3 );

		$args = $this->parent_args( $simple_ids );
		if ( null !== $value ) {
			$args[ WC_Stripe_Agentic_Commerce_Product_Loader::CUSTOM_FILTER_KEY ] = $value;
		}

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();

		$result = $loader->get_products( $args );

		$this->assertIsArray( $result );
		$this->assertSame( $simple_ids, $this->ids( $result ) );
	}

	public function provider_absent_or_empty_custom_filter(): array {
		return [
			'absent'    => [ null ],
			'null'      => [ null ],
			'empty'     => [ [] ],
			'non array' => [ 'not-an-array' ],
		];
	}

	// -----------------------------------------------------------------------
	// Non-paginated branch
	// -----------------------------------------------------------------------

	public function test_non_paginated_branch_merges_parent_and_custom_arrays(): void {
		$simple_ids   = $this->create_simple_products( 3 );
		$variable_ids = $this->create_variable_products( 2 );

		$args = $this->parent_args( $simple_ids );
		$args[ WC_Stripe_Agentic_Commerce_Product_Loader::CUSTOM_FILTER_KEY ] = $this->custom_args( $variable_ids );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();

		$result = $loader->get_products( $args );

		$this->assertIsArray( $result );
		$this->assertSame(
			array_merge( $simple_ids, $variable_ids ),
			$this->ids( $result )
		);
	}

	// -----------------------------------------------------------------------
	// Paginated branch — page 1
	// -----------------------------------------------------------------------

	public function test_paginated_page_one_parent_fully_satisfies_returns_parent_only(): void {
		$simple_ids   = $this->create_simple_products( 4 );
		$variable_ids = $this->create_variable_products( 2 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();
		$args   = $this->paginated_args( $simple_ids, $variable_ids, 1, 2 );

		$result = $loader->get_products( $args );

		$this->assertInstanceOf( \stdClass::class, $result );
		$this->assertSame( array_slice( $simple_ids, 0, 2 ), $this->ids( $result->products ) );
		$this->assertSame( 4 + 2, $result->total );
		$this->assertSame( 3, $result->max_num_pages );
	}

	public function test_paginated_page_one_parent_under_fills_pads_with_custom_prefix(): void {
		$simple_ids   = $this->create_simple_products( 2 );
		$variable_ids = $this->create_variable_products( 3 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();
		$args   = $this->paginated_args( $simple_ids, $variable_ids, 1, 4 );

		$result = $loader->get_products( $args );

		$expected = array_merge( $simple_ids, [ $variable_ids[0], $variable_ids[1] ] );

		$this->assertInstanceOf( \stdClass::class, $result );
		$this->assertSame( $expected, $this->ids( $result->products ) );
		$this->assertSame( 2 + 3, $result->total );
		$this->assertSame( 2, $result->max_num_pages );
	}

	public function test_paginated_page_one_parent_empty_returns_only_custom(): void {
		$variable_ids = $this->create_variable_products( 2 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();
		$args   = $this->paginated_args( [], $variable_ids, 1, 3 );

		$result = $loader->get_products( $args );

		$this->assertSame( $variable_ids, $this->ids( $result->products ) );
		$this->assertSame( 2, $result->total );
		$this->assertSame( 1, $result->max_num_pages );
	}

	// -----------------------------------------------------------------------
	// Paginated branch — multi-call sequence
	// -----------------------------------------------------------------------

	public function test_paginated_walk_streams_parent_then_custom_with_correct_splice(): void {
		$simple_ids   = $this->create_simple_products( 5 );
		$variable_ids = $this->create_variable_products( 2 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();

		// Page 1: full parent slice.
		$page1 = $loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 1, 2 ) );
		$this->assertSame( array_slice( $simple_ids, 0, 2 ), $this->ids( $page1->products ) );
		$this->assertSame( 7, $page1->total );
		$this->assertSame( 4, $page1->max_num_pages );

		// Page 2: full parent slice.
		$page2 = $loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 2, 2 ) );
		$this->assertSame( array_slice( $simple_ids, 2, 2 ), $this->ids( $page2->products ) );

		// Page 3: straddle — parent's tail (1) + custom prefix (1).
		$page3 = $loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 3, 2 ) );
		$this->assertSame(
			[ $simple_ids[4], $variable_ids[0] ],
			$this->ids( $page3->products )
		);

		// Page 4: pure custom — offset 1 into custom, capped by what's left.
		$page4 = $loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 4, 2 ) );
		$this->assertSame( [ $variable_ids[1] ], $this->ids( $page4->products ) );

		// Combined totals/max_num_pages stay consistent across pages.
		foreach ( [ $page2, $page3, $page4 ] as $page ) {
			$this->assertSame( 7, $page->total );
			$this->assertSame( 4, $page->max_num_pages );
		}
	}

	public function test_paginated_walk_with_no_custom_results_drops_back_to_parent_only(): void {
		$simple_ids = $this->create_simple_products( 3 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();
		$args   = $this->paginated_args( $simple_ids, [ PHP_INT_MAX ], 1, 2 );

		$page1 = $loader->get_products( $args );
		$this->assertSame( array_slice( $simple_ids, 0, 2 ), $this->ids( $page1->products ) );
		$this->assertSame( 3, $page1->total );
		$this->assertSame( 2, $page1->max_num_pages );

		$page2 = $loader->get_products( $this->paginated_args( $simple_ids, [ PHP_INT_MAX ], 2, 2 ) );
		$this->assertSame( [ $simple_ids[2] ], $this->ids( $page2->products ) );
	}

	public function test_state_resets_when_page_one_is_observed_again(): void {
		$simple_ids   = $this->create_simple_products( 3 );
		$variable_ids = $this->create_variable_products( 2 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();

		// Partially walk: page 1 advances both counters past parent's first slice.
		$loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 1, 2 ) );
		$loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 2, 2 ) );

		// Restart at page 1: counters must reset so we get parent's first slice again.
		$restart = $loader->get_products( $this->paginated_args( $simple_ids, $variable_ids, 1, 2 ) );

		$this->assertSame( array_slice( $simple_ids, 0, 2 ), $this->ids( $restart->products ) );
		$this->assertSame( 5, $restart->total );
	}

	public function test_paginated_page_two_without_initialized_state_falls_back_to_parent(): void {
		$simple_ids   = $this->create_simple_products( 4 );
		$variable_ids = $this->create_variable_products( 2 );

		$loader = new WC_Stripe_Agentic_Commerce_Product_Loader();

		// Skipping page 1 entirely: must not invent state, falls back to passthrough.
		$args   = $this->paginated_args( $simple_ids, $variable_ids, 2, 2 );
		$result = $loader->get_products( $args );

		$this->assertInstanceOf( \stdClass::class, $result );
		// Without the splice, page 2 of just-parent is parent items [2..3].
		$this->assertSame( array_slice( $simple_ids, 2, 2 ), $this->ids( $result->products ) );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the args the parent query should see — restricted to a known set
	 * of simple products and ordered by include so pagination is deterministic.
	 *
	 * @param int[] $simple_ids Pinned simple-product IDs.
	 */
	private function parent_args( array $simple_ids ): array {
		if ( empty( $simple_ids ) ) {
			// `include => []` would be a no-op; use a sentinel ID that won't exist.
			return [
				'include' => [ PHP_INT_MAX ],
				'orderby' => 'include',
				'return'  => 'objects',
			];
		}

		return [
			'include' => $simple_ids,
			'orderby' => 'include',
			'return'  => 'objects',
		];
	}

	/**
	 * Build the custom-filter args — pinned to a known set of variable IDs.
	 *
	 * @param int[] $variable_ids Pinned variable-parent IDs.
	 */
	private function custom_args( array $variable_ids ): array {
		return [
			'include' => $variable_ids,
			'orderby' => 'include',
			'return'  => 'objects',
		];
	}

	/**
	 * Compose paginated args for a given page/limit.
	 *
	 * @param int[] $simple_ids
	 * @param int[] $variable_ids
	 */
	private function paginated_args( array $simple_ids, array $variable_ids, int $page, int $limit ): array {
		$args             = $this->parent_args( $simple_ids );
		$args['paginate'] = true;
		$args['page']     = $page;
		$args['limit']    = $limit;
		$args[ WC_Stripe_Agentic_Commerce_Product_Loader::CUSTOM_FILTER_KEY ] = $this->custom_args( $variable_ids );

		return $args;
	}

	/**
	 * Convert WC_Product objects to their integer IDs.
	 *
	 * @param array<int, WC_Product> $products
	 * @return int[]
	 */
	private function ids( array $products ): array {
		return array_map(
			static fn( WC_Product $product ): int => $product->get_id(),
			$products
		);
	}

	/**
	 * Create N simple products and return their IDs in creation order.
	 *
	 * @return int[]
	 */
	private function create_simple_products( int $count ): array {
		$ids = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$product                  = WC_Helper_Product::create_simple_product(
				true,
				[ 'sku' => 'PL-SIMPLE-' . uniqid( '', true ) ]
			);
			$ids[]                    = $product->get_id();
			$this->created_products[] = $product->get_id();
		}
		return $ids;
	}

	/**
	 * Create N variable products and return their parent IDs in creation order.
	 *
	 * Variations are also tracked for cleanup.
	 *
	 * @return int[]
	 */
	private function create_variable_products( int $count ): array {
		$ids = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$product = WC_Helper_Product::create_variation_product();
			// `create_variation_product` reuses a hardcoded SKU; re-key to keep them unique.
			$product->set_sku( 'PL-VAR-' . uniqid( '', true ) );
			$product->save();

			$ids[]                    = $product->get_id();
			$this->created_products[] = $product->get_id();
			foreach ( $product->get_children() as $variation_id ) {
				$this->created_products[] = (int) $variation_id;
			}
		}
		return $ids;
	}
}
