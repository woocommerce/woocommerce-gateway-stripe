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
		remove_all_filters( 'wc_stripe_agentic_commerce_product_filters' );

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
	// is_configured / get_inputs / set_inputs round-tripping
	// -----------------------------------------------------------------------

	public function test_is_configured_is_false_when_no_option_set() {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertFalse( $filter->is_configured() );
	}

	public function test_get_inputs_returns_strict_empty_shape_by_default() {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$inputs = $filter->get_inputs();

		$this->assertSame(
			[
				'product_ids' => [],
				'categories'  => [],
				'tags'        => [],
				'brands'      => [],
			],
			$inputs
		);
	}

	public function test_set_inputs_stores_normalized_shape() {
		$cat   = $this->create_term( 'shoes', 'product_cat' );
		$tag   = $this->create_term( 'on-sale', 'product_tag' );
		$brand = $this->create_term( 'nike', 'product_brand' );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertTrue(
			$filter->set_inputs(
				[
					'product_ids' => [ 123, '456', 0, -1, 'abc' ],
					'categories'  => [ $cat['term_id'], 'shoes', '' ],
					'tags'        => [ $tag['slug'], $tag['term_id'] ],
					'brands'      => [ $brand['slug'] ],
				]
			)
		);

		$stored = get_option( $this->option_name );
		$this->assertSame(
			[
				'product_ids' => [ 123, 456 ],
				'categories'  => [ 'shoes' ],
				'tags'        => [ 'on-sale' ],
				'brands'      => [ 'nike' ],
			],
			$stored
		);
	}

	public function test_set_inputs_drops_unknown_term_ids_silently() {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filter->set_inputs(
			[
				'categories' => [ 9999999, 'real-slug', 'real-slug' ],
			]
		);

		$stored = get_option( $this->option_name );
		$this->assertSame( [ 'real-slug' ], $stored['categories'] );
	}

	public function test_set_inputs_silently_drops_brands_when_taxonomy_missing() {
		if ( taxonomy_exists( 'product_brand' ) ) {
			$this->markTestSkipped( 'product_brand taxonomy is registered on this environment.' );
		}

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filter->set_inputs(
			[
				'brands' => [ 'nike', 42 ],
			]
		);

		$stored = get_option( $this->option_name );
		$this->assertSame( [], $stored['brands'] );
	}

	/**
	 * @dataProvider provide_corrupt_option_shapes
	 *
	 * @param mixed $stored Whatever ended up in the option.
	 */
	public function test_get_inputs_coerces_corrupt_option_to_strict_shape( $stored ) {
		update_option( $this->option_name, $stored );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$this->assertSame(
			[
				'product_ids' => [],
				'categories'  => [],
				'tags'        => [],
				'brands'      => [],
			],
			$filter->get_inputs()
		);
	}

	public function provide_corrupt_option_shapes(): array {
		return [
			'non-array option'           => [ 'not-an-array' ],
			'empty array'                => [ [] ],
			'only unrelated keys'        => [ [ 'foo' => 'bar' ] ],
			'non-array dimension values' => [
				[
					'product_ids' => 'oops',
					'categories'  => 42,
					'tags'        => null,
					'brands'      => false,
				],
			],
			'non-scalar entries'         => [
				[
					'product_ids' => [ [ 'nested' ], (object) [ 'x' => 1 ] ],
					'categories'  => [ 7, false, null ],
					'tags'        => [ '', '   ' ],
					'brands'      => [ [] ],
				],
			],
		];
	}

	// -----------------------------------------------------------------------
	// is_configured detection per dimension
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider provide_is_configured_scenarios
	 *
	 * @param array $stored_shape Shape persisted in the option (already in slug form).
	 * @param bool  $expected
	 */
	public function test_is_configured( array $stored_shape, bool $expected ) {
		update_option( $this->option_name, $stored_shape );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( $expected, $filter->is_configured() );
	}

	public function provide_is_configured_scenarios(): array {
		return [
			'all empty'       => [ [], false ],
			'product_ids set' => [ [ 'product_ids' => [ 42 ] ], true ],
			'categories set'  => [ [ 'categories' => [ 'shoes' ] ], true ],
			'tags set'        => [ [ 'tags' => [ 'on-sale' ] ], true ],
			'brands set'      => [ [ 'brands' => [ 'nike' ] ], true ],
		];
	}

	// -----------------------------------------------------------------------
	// resolve_ids per dimension
	// -----------------------------------------------------------------------

	public function test_resolve_ids_explicit_product_ids() {
		$simple = $this->create_simple_product();

		update_option(
			$this->option_name,
			[
				'product_ids' => [ $simple->get_id() ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->resolve_ids() );
	}

	public function test_resolve_ids_explicit_product_ids_drops_grouped() {
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
		$this->assertSame( [ 0 ], $filter->resolve_ids() );
	}

	public function test_resolve_ids_explicit_variable_id_expands_to_variations() {
		$variable = $this->create_variable_product();
		$expected = $this->get_variation_ids( $variable );

		update_option(
			$this->option_name,
			[
				'product_ids' => [ $variable->get_id() ],
			]
		);

		$filter   = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$resolved = $filter->resolve_ids();

		sort( $expected );
		$this->assertSame( $expected, $resolved );
		$this->assertNotContains( $variable->get_id(), $resolved );
	}

	public function test_resolve_ids_explicit_variation_id_kept_as_is() {
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
		$this->assertSame( [ $picked ], $filter->resolve_ids() );
	}

	public function test_resolve_ids_category_returns_simple_products_and_expanded_variations() {
		$cat = $this->create_term( 'shoes', 'product_cat' );

		$simple = $this->create_simple_product();
		wp_set_object_terms( $simple->get_id(), [ (int) $cat['term_id'] ], 'product_cat', false );

		$variable = $this->create_variable_product();
		wp_set_object_terms( $variable->get_id(), [ (int) $cat['term_id'] ], 'product_cat', false );
		$variations = $this->get_variation_ids( $variable );

		update_option(
			$this->option_name,
			[
				'categories' => [ 'shoes' ],
			]
		);

		$filter   = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$resolved = $filter->resolve_ids();

		$expected = array_merge( [ $simple->get_id() ], $variations );
		sort( $expected );

		$this->assertSame( $expected, $resolved );
		$this->assertNotContains( $variable->get_id(), $resolved );
	}

	public function test_resolve_ids_tag_dimension() {
		$tag    = $this->create_term( 'on-sale', 'product_tag' );
		$simple = $this->create_simple_product();
		wp_set_object_terms( $simple->get_id(), [ (int) $tag['term_id'] ], 'product_tag', false );

		update_option(
			$this->option_name,
			[
				'tags' => [ 'on-sale' ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->resolve_ids() );
	}

	public function test_resolve_ids_brand_dimension() {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			$this->markTestSkipped( 'product_brand taxonomy not registered on this environment.' );
		}

		$brand  = $this->create_term( 'nike', 'product_brand' );
		$simple = $this->create_simple_product();
		wp_set_object_terms( $simple->get_id(), [ (int) $brand['term_id'] ], 'product_brand', false );

		update_option(
			$this->option_name,
			[
				'brands' => [ 'nike' ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->resolve_ids() );
	}

	public function test_resolve_ids_unions_across_dimensions_and_dedupes() {
		$cat      = $this->create_term( 'shoes', 'product_cat' );
		$tag      = $this->create_term( 'on-sale', 'product_tag' );
		$shared   = $this->create_simple_product();
		$only_cat = $this->create_simple_product();
		$only_tag = $this->create_simple_product();

		wp_set_object_terms( $shared->get_id(), [ (int) $cat['term_id'] ], 'product_cat', false );
		wp_set_object_terms( $shared->get_id(), [ (int) $tag['term_id'] ], 'product_tag', false );
		wp_set_object_terms( $only_cat->get_id(), [ (int) $cat['term_id'] ], 'product_cat', false );
		wp_set_object_terms( $only_tag->get_id(), [ (int) $tag['term_id'] ], 'product_tag', false );

		update_option(
			$this->option_name,
			[
				'categories' => [ 'shoes' ],
				'tags'       => [ 'on-sale' ],
			]
		);

		$filter   = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$resolved = $filter->resolve_ids();

		$expected = [ $shared->get_id(), $only_cat->get_id(), $only_tag->get_id() ];
		sort( $expected );

		$this->assertSame( $expected, $resolved );
	}

	public function test_resolve_ids_returns_sentinel_when_configured_but_no_matches() {
		update_option(
			$this->option_name,
			[
				'categories' => [ '__no-such-category__' ],
			]
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ 0 ], $filter->resolve_ids() );
	}

	// -----------------------------------------------------------------------
	// Override filter hook
	// -----------------------------------------------------------------------

	public function test_override_filter_replaces_option_derived_inputs() {
		update_option(
			$this->option_name,
			[
				'categories' => [ 'shoes' ],
			]
		);

		$simple = $this->create_simple_product();

		add_filter(
			'wc_stripe_agentic_commerce_product_filters',
			function () use ( $simple ) {
				return [
					'product_ids' => [ $simple->get_id() ],
					'categories'  => [],
					'tags'        => [],
					'brands'      => [],
				];
			}
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertSame( [ $simple->get_id() ], $filter->resolve_ids() );
	}

	public function test_override_filter_return_is_coerced_to_strict_shape() {
		$simple = $this->create_simple_product();

		add_filter(
			'wc_stripe_agentic_commerce_product_filters',
			function () use ( $simple ) {
				return [
					'product_ids' => [ (string) $simple->get_id(), 'not-a-number', -5 ],
					'categories'  => [ 0, '', 'real-slug' ],
				];
			}
		);

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$inputs = $filter->get_inputs();

		$this->assertSame( [ $simple->get_id() ], $inputs['product_ids'] );
		$this->assertSame( [ 'real-slug' ], $inputs['categories'] );
		$this->assertSame( [], $inputs['tags'] );
		$this->assertSame( [], $inputs['brands'] );
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
