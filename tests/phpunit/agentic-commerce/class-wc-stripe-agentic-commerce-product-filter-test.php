<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Filter.
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Product_Filter_Test
 *
 * Covers normalization (slug/ID input), per-dimension resolution, the
 * variable→variation expansion path, the configured-but-empty sentinel, and
 * the `wc_stripe_agentic_commerce_product_filters` override hook.
 */
class WC_Stripe_Agentic_Commerce_Product_Filter_Test extends WP_UnitTestCase {

	/**
	 * Resolved value of the protected OPTION_NAME constant, so tests can
	 * inspect the raw stored shape without exposing the storage key.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Track term IDs we create so tearDown can clean them up.
	 *
	 * @var array<int, array{0:int,1:string}>
	 */
	private array $created_terms = [];

	/**
	 * Track product IDs we create so tearDown can wipe them.
	 *
	 * @var int[]
	 */
	private array $created_products = [];

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Ensure that all existing products are deleted before the test suite runs,
		// as we need control over the products for tests that run queries.
		$existing_products = wc_get_products(
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

		foreach ( $existing_products as $product_id ) {
			WC_Helper_Product::delete_product( $product_id );
		}
	}

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Filter' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Filter class not loaded' );
		}

		$this->option_name = ( new \ReflectionClass( WC_Stripe_Agentic_Commerce_Product_Filter::class ) )
			->getConstant( 'OPTION_NAME' );

		delete_option( $this->option_name );
	}

	public function tearDown(): void {
		delete_option( $this->option_name );
		remove_all_filters( 'wc_stripe_agentic_commerce_product_filter' );

		foreach ( $this->created_products as $product_id ) {
			wp_delete_post( $product_id, true );
		}
		$this->created_products = [];

		foreach ( $this->created_terms as [$term_id, $taxonomy] ) {
			wp_delete_term( $term_id, $taxonomy );
		}
		$this->created_terms = [];

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// has_filters / get_filters / save_filters round-tripping
	// -----------------------------------------------------------------------

	public function test_has_filters_is_false_when_no_option_set() {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertFalse( $filter->has_filters() );
	}

	public function test_get_filters_returns_empty_shape_by_default() {
		$filter  = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filters = $filter->get_filters();

		$this->assertSame(
			[
				'product_ids'          => [],
				'category_ids'         => [],
				'tag_ids'              => [],
				'brand_ids'            => [],
				'variation_parent_ids' => [],
			],
			$filters
		);
	}

	public function test_save_filters_stores_normalized_shape() {
		$cat   = $this->create_term( 'shoes', 'product_cat' );
		$tag   = $this->create_term( 'on-sale', 'product_tag' );
		$brand = $this->create_term( 'nike', 'product_brand' );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertTrue(
			$filter->save_filters(
				[
					'product_ids'          => [ 123, '456', 0, -1, 'abc' ],
					'categories'           => [ $cat['term_id'], 'shoes', '' ],
					'tags'                 => [ $tag['slug'], $tag['term_id'] ],
					'brands'               => [ $brand['slug'] ],
					'variation_parent_ids' => [ '345', 567, -1, 'test', 0.0 ],
				]
			)
		);

		$stored = get_option( $this->option_name );
		$this->assertSame(
			[
				'product_ids'          => [ 123, 456 ],
				'category_ids'         => [ $cat['term_id'] ],
				'tag_ids'              => [ $tag['term_id'] ],
				'brand_ids'            => [ $brand['term_id'] ],
				'variation_parent_ids' => [ 345, 567 ],
			],
			$stored
		);
	}

	public function test_save_filters_drops_unknown_terms_silently() {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filter->save_filters(
			[
				'categories' => [ 9999999, 'bad-slug' ],
				'tags'       => [ 9999999, 'bad-tag' ],
			]
		);

		$stored = get_option( $this->option_name );
		$this->assertSame( [], $stored['category_ids'] );
		$this->assertSame( [], $stored['tag_ids'] );
	}

	public function test_save_filters_silently_drops_brands_when_taxonomy_missing() {
		if ( taxonomy_exists( 'product_brand' ) ) {
			$this->markTestSkipped( 'product_brand taxonomy is registered on this environment.' );
		}

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filter->save_filters(
			[
				'brand_ids' => [ 'nike', 9999999 ],
			]
		);

		$stored = get_option( $this->option_name );
		$this->assertSame( [], $stored['brand_ids'] );
	}

	/**
	 * @dataProvider provide_corrupt_option_shapes
	 *
	 * @param mixed $stored Whatever ended up in the option.
	 */
	public function test_get_filters_coerces_corrupt_option_to_strict_shape( $stored ) {
		update_option( $this->option_name, $stored );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$this->assertSame(
			[
				'product_ids'          => [],
				'category_ids'         => [],
				'tag_ids'              => [],
				'brand_ids'            => [],
				'variation_parent_ids' => [],
			],
			$filter->get_filters()
		);
	}

	public function provide_corrupt_option_shapes(): array {
		return [
			'non-array option'           => [ 'not-an-array' ],
			'empty array'                => [ [] ],
			'only unrelated keys'        => [ [ 'foo' => 'bar' ] ],
			'non-array dimension values' => [
				[
					'product_ids'          => 'oops',
					'categories'           => 42,
					'category_ids'         => null,
					'tags'                 => null,
					'tag_ids'              => 43,
					'brands'               => false,
					'brand_ids'            => true,
					'variation_parent_ids' => 'test',
				],
			],
			'non-scalar entries'         => [
				[
					'product_ids'          => [ [ 'nested' ], (object) [ 'x' => 1 ] ],
					'categories'           => [ 'test-invalid-slug', false, null ],
					'category_ids'         => [ 'some-invalid-slug', false, null ],
					'tags'                 => [ '', '   ' ],
					'tag_ids'              => [ '', '   ' ],
					'brands'               => [ [] ],
					'brand_ids'            => [ [] ],
					'variation_parent_ids' => [ [] ],
				],
			],
		];
	}

	// -----------------------------------------------------------------------
	// has_filters() detection
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider provide_has_filters_scenarios
	 *
	 * @param array $stored_shape Shape persisted in the option (already in slug form).
	 * @param bool  $expected
	 */
	public function test_has_filters( array $stored_shape, bool $expected ) {
		update_option( $this->option_name, $stored_shape );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( $expected, $filter->has_filters() );
	}

	public function provide_has_filters_scenarios(): array {
		return [
			'all empty'        => [ [], false ],
			'product_ids set'  => [ [ 'product_ids' => [ 42 ] ], true ],
			'category_ids set' => [ [ 'category_ids' => [ 42 ] ], true ],
			'tag_ids set'      => [ [ 'tag_ids' => [ 42 ] ], true ],
			'brand_ids set'    => [ [ 'brand_ids' => [ 42 ] ], true ],
		];
	}

	// -----------------------------------------------------------------------
	// get_filtered_product_ids
	// -----------------------------------------------------------------------

	public function test_get_filtered_product_ids_explicit_product_ids() {
		$simple = $this->create_simple_product();

		update_option(
			$this->option_name,
			[
				'product_ids' => [ $simple->get_id() ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->get_filtered_product_ids() );
	}

	public function test_get_filtered_product_ids_explicit_product_ids_drops_grouped() {
		$grouped                  = WC_Helper_Product::create_grouped_product();
		$this->created_products[] = $grouped->get_id();
		foreach ( $grouped->get_children() as $child_id ) {
			$this->created_products[] = (int) $child_id;
		}

		update_option(
			$this->option_name,
			[
				'product_ids' => [ $grouped->get_id() ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		// Grouped parent itself isn't simple/variation/variable, so it falls away.
		// Its children are simple products but weren't named in the input.
		$this->assertSame( [ 0 ], $filter->get_filtered_product_ids() );
	}

	public function test_get_filtered_product_ids_explicit_variable_id_not_expanded_when_disabled() {
		$variable = $this->create_variable_product();

		update_option(
			$this->option_name,
			[
				'product_ids'               => [ $variable->get_id() ],
				'include_variable_products' => false,
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$filtered_product_ids = $filter->get_filtered_product_ids();

		$this->assertSame( [ 0 ], $filtered_product_ids );
	}

	public function test_get_filtered_product_ids_explicit_variable_id_expands_to_variations_when_enabled() {
		$variable = $this->create_variable_product();
		$expected = $this->get_variation_ids( $variable );

		update_option(
			$this->option_name,
			[
				'product_ids'               => [ $variable->get_id() ],
				'include_variable_products' => true,
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$filtered_product_ids = $filter->get_filtered_product_ids();

		sort( $expected );
		$this->assertSame( $expected, $filtered_product_ids );
		$this->assertNotContains( $variable->get_id(), $filtered_product_ids );
	}

	public function test_get_filtered_product_ids_explicit_variation_id_kept_as_is() {
		$variable   = $this->create_variable_product();
		$variations = $this->get_variation_ids( $variable );
		$picked     = $variations[0];

		update_option(
			$this->option_name,
			[
				'product_ids' => [ $picked ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $picked ], $filter->get_filtered_product_ids() );
	}

	public function test_get_filtered_product_ids_category_returns_simple_products_and_expanded_variations() {
		$cat = $this->create_term( 'shoes', 'product_cat' );

		$simple = $this->create_simple_product();
		$simple->set_category_ids( [ $cat['term_id'] ] );
		$simple->save();

		$variable = $this->create_variable_product();
		$variable->set_category_ids( [ $cat['term_id'] ] );
		$variable->save();
		$variations = $this->get_variation_ids( $variable );

		update_option(
			$this->option_name,
			[
				'category_ids'              => [ $cat['term_id'] ],
				'include_variable_products' => true,
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$filtered_product_ids = $filter->get_filtered_product_ids();

		$expected = array_merge( [ $simple->get_id() ], $variations );
		sort( $expected );

		$this->assertSame( $expected, $filtered_product_ids );
		$this->assertNotContains( $variable->get_id(), $filtered_product_ids );
	}

	public function test_get_filtered_product_ids_tag_returns_simple_products() {
		$tag    = $this->create_term( 'on-sale', 'product_tag' );
		$simple = $this->create_simple_product();
		$simple->set_tag_ids( [ $tag['term_id'] ] );
		$simple->save();

		update_option(
			$this->option_name,
			[
				'tag_ids' => [ $tag['term_id'] ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->get_filtered_product_ids() );
	}

	public function test_get_filtered_product_ids_brand_returns_simple_products() {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			$this->markTestSkipped( 'product_brand taxonomy not registered on this environment.' );
		}

		$brand  = $this->create_term( 'nike', 'product_brand' );
		$simple = $this->create_simple_product();
		$simple->set_brand_ids( [ $brand['term_id'] ] );
		$simple->save();

		update_option(
			$this->option_name,
			[
				'brand_ids' => [ $brand['term_id'] ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->get_filtered_product_ids() );
	}

	/**
	 * @dataProvider provide_include_variable_product_cases
	 *
	 * @param bool $include_variable_products
	 */
	public function test_get_filtered_product_ids_with_multiple_filter_types( bool $include_variable_products ) {
		$brand_taxonomy_exists = taxonomy_exists( 'product_brand' );

		$cat = $this->create_term( 'shoes', 'product_cat' );
		$tag = $this->create_term( 'on-sale', 'product_tag' );

		$shared   = $this->create_simple_product();
		$only_cat = $this->create_simple_product();
		$only_tag = $this->create_simple_product();
		$only_id  = $this->create_simple_product();
		$variable = $this->create_variable_product();

		$expected = [
			$shared->get_id(),
			$only_cat->get_id(),
			$only_tag->get_id(),
			$only_id->get_id(),
		];

		if ( $include_variable_products ) {
			$expected = array_merge( $expected, $this->get_variation_ids( $variable ) );
		}

		$brand_ids = [];
		if ( $brand_taxonomy_exists ) {
			$brand      = $this->create_term( 'nike', 'product_brand' );
			$brand_ids  = [ $brand['term_id'] ];
			$only_brand = $this->create_simple_product();

			$shared->set_brand_ids( $brand_ids );
			$shared->save();

			$only_brand->set_brand_ids( $brand_ids );
			$only_brand->save();

			$expected[] = $only_brand->get_id();
		}

		$shared->set_category_ids( [ $cat['term_id'] ] );
		$shared->set_tag_ids( [ $tag['term_id'] ] );
		$shared->save();

		$only_cat->set_category_ids( [ $cat['term_id'] ] );
		$only_cat->save();

		$only_tag->set_tag_ids( [ $tag['term_id'] ] );
		$only_tag->save();

		$option_data = [
			'product_ids'               => [ $only_id->get_id(), $shared->get_id(), $variable->get_id() ],
			'category_ids'              => [ $cat['term_id'] ],
			'tag_ids'                   => [ $tag['term_id'] ],
			'brand_ids'                 => $brand_ids,
			'include_variable_products' => $include_variable_products,
		];
		update_option(
			$this->option_name,
			$option_data
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$filtered_product_ids = $filter->get_filtered_product_ids();

		sort( $expected );

		$this->assertSame( $expected, $filtered_product_ids );
	}

	/**
	 * Provide cases for the include_variable_products filter.
	 *
	 * @see test_get_filtered_product_ids_with_multiple_filter_types()
	 * @return array<string, bool>
	 */
	public function provide_include_variable_product_cases(): array {
		return [
			'enabled'  => [ true ],
			'disabled' => [ false ],
		];
	}

	public function test_get_filtered_product_ids_returns_sentinel_when_configured_but_no_matches() {
		update_option(
			$this->option_name,
			[
				'category_ids' => [ 9999999999 ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ 0 ], $filter->get_filtered_product_ids() );
	}

	// -----------------------------------------------------------------------
	// Override filter hook
	// -----------------------------------------------------------------------

	public function test_override_filter_replaces_option_derived_inputs() {
		update_option(
			$this->option_name,
			[
				'category_ids' => [ 1234 ],
			]
		);

		$simple = $this->create_simple_product();

		add_filter(
			'wc_stripe_agentic_commerce_product_filter',
			function () use ( $simple ) {
				return [
					'product_ids'  => [ $simple->get_id() ],
					'category_ids' => [],
					'tag_ids'      => [],
					'brand_ids'    => [],
				];
			}
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->get_filtered_product_ids() );
	}

	public function test_override_filter_return_is_normalized_to_expected_shape() {
		$simple = $this->create_simple_product();

		add_filter(
			'wc_stripe_agentic_commerce_product_filter',
			function () use ( $simple ) {
				return [
					'product_ids'               => [ (string) $simple->get_id(), 'not-a-number', -5 ],
					'categories'                => [ 0, '', 'real-slug' ],
					'tags'                      => [ 'not-a-number', -5 ],
					'brand_ids'                 => [ 'not-a-number', -5 ],
					'include_variable_products' => 0,
				];
			}
		);

		$filter  = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filters = $filter->get_filters();

		$this->assertSame( [ $simple->get_id() ], $filters['product_ids'] );
		$this->assertSame( [], $filters['category_ids'] );
		$this->assertSame( [], $filters['tag_ids'] );
		$this->assertSame( [], $filters['brand_ids'] );
		$this->assertFalse( $filters['include_variable_products'] );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function create_term( string $slug, string $taxonomy ): array {
		if ( 'product_brand' === $taxonomy && ! taxonomy_exists( 'product_brand' ) ) {
			register_taxonomy( 'product_brand', 'product', [ 'public' => true ] );
		}
		$term                  = wp_insert_term( $slug, $taxonomy, [ 'slug' => $slug ] );
		$this->created_terms[] = [ (int) $term['term_id'], $taxonomy ];
		return array_merge( $term, [ 'slug' => $slug ] );
	}

	private function create_simple_product(): WC_Product_Simple {
		$product                  = WC_Helper_Product::create_simple_product();
		$this->created_products[] = $product->get_id();
		return $product;
	}

	private function create_variable_product(): WC_Product_Variable {
		$product                  = WC_Helper_Product::create_variation_product();
		$this->created_products[] = $product->get_id();
		foreach ( $product->get_children() as $variation_id ) {
			$this->created_products[] = (int) $variation_id;
		}
		return $product;
	}

	/**
	 * @return int[] Sorted list of variation IDs.
	 */
	private function get_variation_ids( WC_Product_Variable $variable ): array {
		$ids = array_map( 'intval', $variable->get_children() );
		sort( $ids );
		return $ids;
	}
}
