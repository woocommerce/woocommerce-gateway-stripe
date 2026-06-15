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

	public function test_has_filters_is_false_when_no_option_set(): void {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertFalse( $filter->has_filters() );
	}

	public function test_get_filters_returns_empty_shape_by_default(): void {
		$filter  = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filters = $filter->get_filters();

		$this->assertSame(
			[
				'product_ids'          => [],
				'category_ids'         => [],
				'tag_ids'              => [],
				'brand_ids'            => [],
				'variable_product_ids' => [],
			],
			$filters
		);
	}

	public function test_save_filters_stores_validated_shape(): void {
		$cat = $this->create_term( 'shoes', 'product_cat' );
		$tag = $this->create_term( 'on-sale', 'product_tag' );

		$simple_product   = $this->create_simple_product();
		$variable_product = $this->create_variable_product();

		$expected_brand_ids = [];
		$brands_input       = [];
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brand              = $this->create_term( 'nike', 'product_brand' );
			$expected_brand_ids = [ $brand['term_id'] ];
			$brands_input       = [ $brand['slug'] ];
		}

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$this->assertTrue(
			$filter->save_filters(
				[
					'product_ids'          => [ 123, '456', 0, -1, 'abc', $simple_product->get_id(), $variable_product->get_id() ],
					'categories'           => [ $cat['term_id'], 'shoes', '' ],
					'tags'                 => [ $tag['slug'], $tag['term_id'] ],
					'brands'               => $brands_input,
					'variable_product_ids' => [ '345', 567, -1, 'test', 0.0, $simple_product->get_id(), $variable_product->get_id() ],
				]
			)
		);

		$stored = get_option( $this->option_name );
		$this->assertSame(
			[
				'product_ids'          => [ $simple_product->get_id() ],
				'category_ids'         => [ $cat['term_id'] ],
				'tag_ids'              => [ $tag['term_id'] ],
				'brand_ids'            => $expected_brand_ids,
				'variable_product_ids' => [ $variable_product->get_id() ],
			],
			$stored
		);
	}

	public function test_save_filters_updates_filter_cache(): void {
		$simple_product = $this->create_simple_product();

		$default_filters = [
			'product_ids'          => [],
			'category_ids'         => [],
			'tag_ids'              => [],
			'brand_ids'            => [],
			'variable_product_ids' => [],
		];

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$filters = $filter->get_filters();
		$this->assertSame( $default_filters, $filters );

		$filter->save_filters(
			[
				'product_ids' => [ $simple_product->get_id() ],
			]
		);

		$filters = $filter->get_filters();
		$this->assertNotSame( $default_filters, $filters );
		$this->assertArrayHasKey( 'product_ids', $filters );
		$this->assertSame( [ $simple_product->get_id() ], $filters['product_ids'] );
	}

	public function test_save_filters_drops_unknown_terms_silently(): void {
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

	public function test_save_filters_silently_drops_brands_when_taxonomy_missing(): void {
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
	 * @dataProvider provide_corrupt_option_data
	 *
	 * @param mixed $stored Whatever ended up in the option.
	 */
	public function test_get_filters_normalizes_corrupt_option_data( $option_data ): void {
		update_option( $this->option_name, $option_data );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$this->assertSame(
			[
				'product_ids'          => [],
				'category_ids'         => [],
				'tag_ids'              => [],
				'brand_ids'            => [],
				'variable_product_ids' => [],
			],
			$filter->get_filters()
		);
	}

	/**
	 * Data provider for {@see test_get_filters_normalizes_corrupt_option_data()}.
	 *
	 * @return array
	 */
	public function provide_corrupt_option_data(): array {
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
					'variable_product_ids' => 'test',
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
					'variable_product_ids' => [ [] ],
				],
			],
		];
	}

	public function test_get_filters_only_calls_filter_once(): void {
		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$call_count     = 0;
		$capture_filter = function ( $filters ) use ( &$call_count ) {
			++$call_count;
			return $filters;
		};

		add_filter( 'wc_stripe_agentic_commerce_product_filter', $capture_filter );

		try {
			$filter->get_filters();
			$this->assertSame( 1, $call_count );

			$filter->get_filters();
			$this->assertSame( 1, $call_count );
		} finally {
			remove_filter( 'wc_stripe_agentic_commerce_product_filter', $capture_filter );
		}
	}

	// -----------------------------------------------------------------------
	// has_filters()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider provide_has_filters_scenarios
	 *
	 * @param array $stored_shape Shape persisted in the option (already in slug form).
	 * @param bool  $expected
	 */
	public function test_has_filters( array $stored_shape, bool $expected ): void {
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
	// get_effective_filter_types
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider provide_get_effective_filter_types_scenarios
	 *
	 * @param array $stored_filters The data to store in the option.
	 * @param array $expected_types The types we expect to be returned.
	 */
	public function test_get_effective_filter_types( array $stored_filters, array $expected_types ): void {
		update_option( $this->option_name, $stored_filters );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$actual_types = $filter->get_effective_filter_types();

		$this->assertEqualsCanonicalizing( $expected_types, $actual_types );
	}

	public function provide_get_effective_filter_types_scenarios(): array {
		$product_brand_exists = taxonomy_exists( 'product_brand' );

		return [
			'all empty'                                 => [ [], [] ],
			'all empty arrays'                          => [
				'option_data'    => [
					'product_ids'          => [],
					'category_ids'         => [],
					'tag_ids'              => [],
					'brand_ids'            => [],
					'variable_product_ids' => [],
				],
				'expected_types' => [],
			],
			'only product_ids set'                      => [
				'option_data'    => [ 'product_ids' => [ 123, 456 ] ],
				'expected_types' => [ 'product_ids' ],
			],
			'only category_ids set'                     => [
				'option_data'    => [ 'category_ids' => [ 321, 654 ] ],
				'expected_types' => [ 'category_ids' ],
			],
			'only tag_ids set'                          => [
				'option_data'    => [ 'tag_ids' => [ 42 ] ],
				'expected_types' => [ 'tag_ids' ],
			],
			'only brand_ids set'                        => [
				'option_data'    => [ 'brand_ids' => [ 987, 678, 567 ] ],
				'expected_types' => $product_brand_exists ? [ 'brand_ids' ] : [],
			],
			'only variable_product_ids set'             => [
				'option_data'    => [ 'variable_product_ids' => [ 123, 456 ] ],
				'expected_types' => [ 'variable_product_ids' ],
			],
			'product_ids and category_ids set'          => [
				'option_data'    => [
					'product_ids'  => [ 123 ],
					'category_ids' => [ 456 ],
				],
				'expected_types' => [ 'product_ids' ],
			],
			'product_ids and tag_ids set'               => [
				'option_data'    => [
					'product_ids' => [ 9999 ],
					'tag_ids'     => [ 5555 ],
				],
				'expected_types' => [ 'product_ids' ],
			],
			'product_ids and brand_ids set'             => [
				'option_data'    => [
					'product_ids' => [ 42 ],
					'brand_ids'   => [ 42 ],
				],
				'expected_types' => [ 'product_ids' ], // product IDs always supersede brand IDs.
			],
			'product_ids and variable_product_ids set'  => [
				'option_data'    => [
					'product_ids'          => [ 123, 456 ],
					'variable_product_ids' => [ 456, 789 ],
				],
				'expected_types' => [ 'product_ids' ],
			],
			'category_ids and tag_ids set'              => [
				'option_data'    => [
					'category_ids' => [ 321, 654 ],
					'tag_ids'      => [ 42, 567 ],
				],
				'expected_types' => [ 'category_ids', 'tag_ids' ],
			],
			'category_ids and brand_ids set'            => [
				'option_data'    => [
					'category_ids' => [ 321, 654 ],
					'brand_ids'    => [ 987, 678, 567 ],
				],
				'expected_types' => $product_brand_exists ? [ 'category_ids', 'brand_ids' ] : [ 'category_ids' ],
			],
			'category_ids and variable_product_ids set' => [
				'option_data'    => [
					'category_ids'         => [ 321, 654 ],
					'variable_product_ids' => [ 456, 789 ],
				],
				'expected_types' => [ 'variable_product_ids' ],
			],
			'tag_ids and brand_ids set'                 => [
				'option_data'    => [
					'tag_ids'   => [ 42, 567 ],
					'brand_ids' => [ 42 ],
				],
				'expected_types' => $product_brand_exists ? [ 'tag_ids', 'brand_ids' ] : [ 'tag_ids' ],
			],
			'tag_ids and variable_product_ids set'      => [
				'option_data'    => [
					'tag_ids'              => [ 42, 567 ],
					'variable_product_ids' => [ 456, 789 ],
				],
				'expected_types' => [ 'variable_product_ids' ],
			],
			'brand_ids and variable_product_ids set'    => [
				'option_data'    => [
					'brand_ids'            => [ 42, 567 ],
					'variable_product_ids' => [ 456, 789 ],
				],
				'expected_types' => [ 'variable_product_ids' ],
			],
			'category_ids, tag_ids, and brand_ids set'  => [
				'option_data'    => [
					'category_ids' => [ 321, 654 ],
					'tag_ids'      => [ 42, 567 ],
					'brand_ids'    => [ 987, 678, 567 ],
				],
				'expected_types' => $product_brand_exists ? [ 'category_ids', 'tag_ids', 'brand_ids' ] : [ 'category_ids', 'tag_ids' ],
			],
		];
	}

	/**
	 * @dataProvider provide_get_query_args_scenarios
	 *
	 * @param array      $option_data   The data to store in the option.
	 * @param array|null $expected_args The expected args.
	 */
	public function test_get_query_args( array $option_data, ?array $expected_args ): void {
		update_option( $this->option_name, $option_data );

		$filter = new WC_Stripe_Agentic_Commerce_Product_Filter();

		$actual_args = $filter->get_query_args();

		if ( null === $expected_args ) {
			$this->assertNull( $actual_args );
		} else {
			$this->assertIsArray( $actual_args );
			$this->assert_nested_array_equals( $expected_args, $actual_args );
		}
	}

	private function assert_nested_array_equals( array $expected, array $actual ) {
		$this->assertEquals( count( $expected ), count( $actual ) );

		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $actual );
			if ( is_array( $value ) ) {
				if ( is_array( $actual[ $key ] ) ) {
					$this->assert_nested_array_equals( $value, $actual[ $key ] );
				} else {
					$this->fail( 'Expected nested array but got ' . gettype( $actual[ $key ] ) );
				}
			} else {
				$this->assertEquals( $value, $actual[ $key ] );
			}
		}
	}

	public function provide_get_query_args_scenarios(): array {
		$product_brand_exists = taxonomy_exists( 'product_brand' );

		$normal_product_args = [
			'status' => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
			'type'   => [
				\Automattic\WooCommerce\Enums\ProductType::SIMPLE,
				\Automattic\WooCommerce\Enums\ProductType::VARIATION,
			],
		];

		$variable_product_args = [
			'status' => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
			'type'   => \Automattic\WooCommerce\Enums\ProductType::VARIATION,
		];

		return [
			'all empty'                                 => [
				'option_data'   => [],
				'expected_args' => null,
			],
			'all empty arrays'                          => [
				'option_data'   => [
					'product_ids'          => [],
					'category_ids'         => [],
					'tag_ids'              => [],
					'brand_ids'            => [],
					'variable_product_ids' => [],
				],
				'expected_args' => null,
			],
			'all set'                                   => [
				'option_data'   => [
					'product_ids'          => [ 123, 456, 789 ],
					'category_ids'         => [ 987, 654, 321 ],
					'tag_ids'              => [ 42, 9999 ],
					'brand_ids'            => [ 11, 12, 13 ],
					'variable_product_ids' => [ 1111, 3333, 5555 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[ 'include' => [ 123, 456, 789 ] ]
				),
			],
			'only product_ids set'                      => [
				'option_data'   => [
					'product_ids' => [ 123, 456, 789 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[ 'include' => [ 123, 456, 789 ] ]
				),
			],
			'only variable_product_ids set'             => [
				'option_data'   => [
					'variable_product_ids' => [ 123, 456, 789 ],
				],
				'expected_args' => array_merge(
					$variable_product_args,
					[ 'post_parent__in' => [ 123, 456, 789 ] ]
				),
			],
			'only category_ids set'                     => [
				'option_data'   => [
					'category_ids' => [ 321, 654 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[
						'tax_query' => [
							[
								[
									'taxonomy'         => 'product_cat',
									'field'            => 'term_id',
									'terms'            => [ 321, 654 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
							],
						],
					]
				),
			],
			'only tag_ids set'                          => [
				'option_data'   => [
					'tag_ids' => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[
						'tax_query' => [
							[
								[
									'taxonomy'         => 'product_tag',
									'field'            => 'term_id',
									'terms'            => [ 42, 9999 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
							],
						],
					],
				),
			],
			'only brand_ids set'                        => [
				'option_data'   => [
					'brand_ids' => [ 42, 9999 ],
				],
				'expected_args' => $product_brand_exists ? array_merge(
					$normal_product_args,
					[
						'tax_query' => [
							[
								[
									'taxonomy'         => 'product_brand',
									'field'            => 'term_id',
									'terms'            => [ 42, 9999 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
							],
						],
					]
				) : null,
			],
			'product_ids and category_ids set'          => [
				'option_data'   => [
					'product_ids'  => [ 123, 456, 789 ],
					'category_ids' => [ 321, 654 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[ 'include' => [ 123, 456, 789 ] ]
				),
			],
			'product_ids and tag_ids set'               => [
				'option_data'   => [
					'product_ids' => [ 123, 456, 789 ],
					'tag_ids'     => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[ 'include' => [ 123, 456, 789 ] ]
				),
			],
			'product_ids and brand_ids set'             => [
				'option_data'   => [
					'product_ids' => [ 123, 456, 789 ],
					'brand_ids'   => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[ 'include' => [ 123, 456, 789 ] ]
				),
			],
			'product_ids and variable_product_ids set'  => [
				'option_data'   => [
					'product_ids'          => [ 123, 456, 789 ],
					'variable_product_ids' => [ 987, 654 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[ 'include' => [ 123, 456, 789 ] ]
				),
			],
			'variable_product_ids and category_ids set' => [
				'option_data'   => [
					'variable_product_ids' => [ 987, 654 ],
					'category_ids'         => [ 321, 654 ],
				],
				'expected_args' => array_merge(
					$variable_product_args,
					[ 'post_parent__in' => [ 987, 654 ] ]
				),
			],
			'variable_product_ids and tag_ids set'      => [
				'option_data'   => [
					'variable_product_ids' => [ 987, 654 ],
					'tag_ids'              => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$variable_product_args,
					[ 'post_parent__in' => [ 987, 654 ] ]
				),
			],
			'variable_product_ids and brand_ids set'    => [
				'option_data'   => [
					'variable_product_ids' => [ 987, 654 ],
					'brand_ids'            => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$variable_product_args,
					[ 'post_parent__in' => [ 987, 654 ] ]
				),
			],
			'category_ids and tag_ids set'              => [
				'option_data'   => [
					'category_ids' => [ 321, 654 ],
					'tag_ids'      => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[
						'tax_query' => [
							[
								'relation' => 'OR',
								[
									'taxonomy'         => 'product_cat',
									'field'            => 'term_id',
									'terms'            => [ 321, 654 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
								[
									'taxonomy'         => 'product_tag',
									'field'            => 'term_id',
									'terms'            => [ 42, 9999 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
							],
						],
					]
				),
			],
			'category_ids and brand_ids set'            => [
				'option_data'   => [
					'category_ids' => [ 321, 654 ],
					'brand_ids'    => [ 42, 9999 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[
						'tax_query' => [
							// Note that we use array_merge() so we can cleanly handle
							// the case where the product brand taxonomy does not exist.
							array_merge(
								$product_brand_exists ? [ 'relation' => 'OR' ] : [],
								[
									[
										'taxonomy'         => 'product_cat',
										'field'            => 'term_id',
										'terms'            => [ 321, 654 ],
										'operator'         => 'IN',
										'include_children' => false,
									],
								],
								$product_brand_exists ? [
									[
										'taxonomy'         => 'product_brand',
										'field'            => 'term_id',
										'terms'            => [ 42, 9999 ],
										'operator'         => 'IN',
										'include_children' => false,
									],
								] : []
							),
						],
					]
				),
			],
			'category_ids, tag_ids, and brand_ids set'  => [
				'option_data'   => [
					'category_ids' => [ 321, 654 ],
					'tag_ids'      => [ 42, 9999 ],
					'brand_ids'    => [ 123, 456 ],
				],
				'expected_args' => array_merge(
					$normal_product_args,
					[
						'tax_query' => [
							[
								'relation' => 'OR',
								[
									'taxonomy'         => 'product_cat',
									'field'            => 'term_id',
									'terms'            => [ 321, 654 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
								[
									'taxonomy'         => 'product_tag',
									'field'            => 'term_id',
									'terms'            => [ 42, 9999 ],
									'operator'         => 'IN',
									'include_children' => false,
								],
								...( $product_brand_exists ? [
									[
										'taxonomy'         => 'product_brand',
										'field'            => 'term_id',
										'terms'            => [ 123, 456 ],
										'operator'         => 'IN',
										'include_children' => false,
									],
								] : [] ),
							],
						],
					]
				),
			],
		];
	}

	/**
	 * @dataProvider provide_product_id_query_handles_product_type_scenarios
	 *
	 * @param string $product_type   The product type to test.
	 * @param bool   $should_include Whether the product should be included in the query results.
	 */
	public function test_product_id_query_handles_product_type( string $product_type, bool $should_include ): void {
		$product_ids_to_query = [];
		if ( 'grouped' === $product_type ) {
			$grouped_product = WC_Helper_Product::create_grouped_product();

			$this->created_products[] = $grouped_product->get_id();
			$product_ids_to_query     = [ $grouped_product->get_id() ];

			foreach ( $grouped_product->get_children() as $child_id ) {
				$this->created_products[] = (int) $child_id;
			}
		} elseif ( in_array( $product_type, [ 'variable', 'variation' ], true ) ) {
			$variable_product = $this->create_variable_product();

			if ( 'variable' === $product_type ) {
				$product_ids_to_query = [ $variable_product->get_id() ];
			} else {
				$product_ids_to_query = $this->get_sorted_variation_ids( $variable_product );
			}
		} elseif ( 'simple' === $product_type ) {
			$simple_product = $this->create_simple_product();

			$product_ids_to_query = [ $simple_product->get_id() ];
		} else {
			$this->fail( 'Invalid product type: ' . $product_type );
		}

		update_option(
			$this->option_name,
			[
				'product_ids' => $product_ids_to_query,
			]
		);

		$filter     = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$query_args = $filter->get_query_args();

		$query_args['return'] = 'ids';

		$query_results = wc_get_products( $query_args );

		if ( $should_include ) {
			$this->assertEqualsCanonicalizing( $product_ids_to_query, $query_results );
		} else {
			$this->assertSame( [], $query_results );
		}
	}

	/**
	 * Data provider for {@see test_product_id_query_handles_product_type()}.
	 *
	 * @return array
	 */
	public function provide_product_id_query_handles_product_type_scenarios(): array {
		return [
			'grouped'   => [ 'grouped', false ],
			'variable'  => [ 'variable', false ],
			'simple'    => [ 'simple', true ],
			'variation' => [ 'variation', true ],
		];
	}

	/**
	 * @dataProvider provide_variable_product_id_query_handles_product_type_scenarios
	 *
	 * @param string $product_type   The product type to test.
	 */
	public function test_variable_product_id_query_handles_product_type( string $product_type ): void {
		$variable_product_ids_to_query = [];
		$expected_variation_ids        = [];

		if ( 'grouped' === $product_type ) {
			$grouped_product = WC_Helper_Product::create_grouped_product();

			$this->created_products[]      = $grouped_product->get_id();
			$variable_product_ids_to_query = [ $grouped_product->get_id() ];

			foreach ( $grouped_product->get_children() as $child_id ) {
				$this->created_products[] = (int) $child_id;
			}
		} elseif ( in_array( $product_type, [ 'variable', 'variation' ], true ) ) {
			$variable_product = $this->create_variable_product();
			$variation_ids    = $this->get_sorted_variation_ids( $variable_product );

			if ( 'variable' === $product_type ) {
				$variable_product_ids_to_query = [ $variable_product->get_id() ];
				$expected_variation_ids        = $variation_ids;
			} else {
				$variable_product_ids_to_query = $variation_ids;
			}
		} elseif ( 'simple' === $product_type ) {
			$simple_product                = $this->create_simple_product();
			$variable_product_ids_to_query = [ $simple_product->get_id() ];
		} else {
			$this->fail( 'Invalid product type: ' . $product_type );
		}

		update_option(
			$this->option_name,
			[
				'variable_product_ids' => $variable_product_ids_to_query,
			]
		);

		$filter     = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$query_args = $filter->get_query_args();

		$query_args['return'] = 'ids';

		$query_results = wc_get_products( $query_args );

		if ( [] !== $expected_variation_ids ) {
			$this->assertEqualsCanonicalizing( $expected_variation_ids, $query_results );
		} else {
			$this->assertSame( [], $query_results );
		}
	}

	/**
	 * Data provider for {@see test_product_id_query_handles_product_type()}.
	 *
	 * @return array
	 */
	public function provide_variable_product_id_query_handles_product_type_scenarios(): array {
		return [
			'grouped'   => [ 'grouped' ],
			'variable'  => [ 'variable' ],
			'simple'    => [ 'simple' ],
			'variation' => [ 'variation' ],
		];
	}

	/**
	 * @dataProvider provide_taxonomy_query_returns_simple_products_scenarios
	 *
	 * @param string $taxonomy       The taxonomy to test.
	 * @param string $filter_key     The key to use in the filter option data.
	 * @param string $setter_method  The method to set the taxonomy IDs on the product.
	 */
	public function test_taxonomy_query_returns_simple_products( string $taxonomy, string $filter_key, string $setter_method ): void {
		$term = $this->create_term( 'test-test-test', $taxonomy );

		$simple_product_1 = $this->create_simple_product();
		$simple_product_1->$setter_method( [ $term['term_id'] ] );
		$simple_product_1->save();

		$simple_product_2 = $this->create_simple_product();
		$simple_product_2->$setter_method( [ $term['term_id'] ] );
		$simple_product_2->save();

		$expected_result_ids = [
			$simple_product_1->get_id(),
			$simple_product_2->get_id(),
		];

		update_option(
			$this->option_name,
			[
				$filter_key => [ $term['term_id'] ],
			]
		);

		$filter     = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$query_args = $filter->get_query_args();

		$query_args['return'] = 'ids';

		$query_results = wc_get_products( $query_args );

		$this->assertEqualsCanonicalizing( $expected_result_ids, $query_results );
	}

	/**
	 * Data provider for {@see test_taxonomy_query_returns_simple_products()}.
	 *
	 * @return array
	 */
	public function provide_taxonomy_query_returns_simple_products_scenarios(): array {
		$scenarios = [
			'category' => [ 'product_cat', 'category_ids', 'set_category_ids' ],
			'tag'      => [ 'product_tag', 'tag_ids', 'set_tag_ids' ],
		];

		if ( taxonomy_exists( 'product_brand' ) ) {
			$scenarios['brand'] = [ 'product_brand', 'brand_ids', 'set_brand_ids' ];
		}

		return $scenarios;
	}

	public function test_multiple_taxonomy_queries_returns_simple_products(): void {
		$brand_taxonomy_exists = taxonomy_exists( 'product_brand' );

		$cat = $this->create_term( 'shoes', 'product_cat' );
		$tag = $this->create_term( 'on-sale', 'product_tag' );

		$shared   = $this->create_simple_product();
		$only_cat = $this->create_simple_product();
		$only_tag = $this->create_simple_product();

		$expected_product_ids = [
			$shared->get_id(),
			$only_cat->get_id(),
			$only_tag->get_id(),
		];

		$brand_ids = [];
		if ( $brand_taxonomy_exists ) {
			$brand      = $this->create_term( 'nike', 'product_brand' );
			$brand_ids  = [ $brand['term_id'] ];
			$only_brand = $this->create_simple_product();

			$shared->set_brand_ids( $brand_ids );
			$shared->save();

			$only_brand->set_brand_ids( $brand_ids );
			$only_brand->save();

			$expected_product_ids[] = $only_brand->get_id();
		}

		$shared->set_category_ids( [ $cat['term_id'] ] );
		$shared->set_tag_ids( [ $tag['term_id'] ] );
		$shared->save();

		$only_cat->set_category_ids( [ $cat['term_id'] ] );
		$only_cat->save();

		$only_tag->set_tag_ids( [ $tag['term_id'] ] );
		$only_tag->save();

		$option_data = [
			'category_ids' => [ $cat['term_id'] ],
			'tag_ids'      => [ $tag['term_id'] ],
			'brand_ids'    => $brand_ids,
		];
		update_option(
			$this->option_name,
			$option_data
		);

		$filter     = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$query_args = $filter->get_query_args();

		$query_args['return'] = 'ids';

		$query_results = wc_get_products( $query_args );

		$this->assertEqualsCanonicalizing( $expected_product_ids, $query_results );
	}

	// -----------------------------------------------------------------------
	// Override filter hook
	// -----------------------------------------------------------------------

	public function test_override_filter_replaces_option_derived_inputs(): void {
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

		$filter     = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$query_args = $filter->get_query_args();

		$query_args['return'] = 'ids';

		$query_results = wc_get_products( $query_args );

		$this->assertEquals( [ $simple->get_id() ], $query_results );
	}

	public function test_override_filter_return_is_normalized_to_expected_shape(): void {
		$simple = $this->create_simple_product();

		add_filter(
			'wc_stripe_agentic_commerce_product_filter',
			function () use ( $simple ) {
				return [
					'product_ids'          => [ (string) $simple->get_id(), 'not-a-number', -5 ],
					'categories'           => [ 0, '', 'real-slug' ],
					'tags'                 => [ 'not-a-number', -5 ],
					'brand_ids'            => [ 'not-a-number', -5 ],
					'variable_product_ids' => [ -100, 'slug', '999' ],
				];
			}
		);

		$filter  = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filters = $filter->get_filters();

		$this->assertSame( [ $simple->get_id() ], $filters['product_ids'] );
		$this->assertSame( [], $filters['category_ids'] );
		$this->assertSame( [], $filters['tag_ids'] );
		$this->assertSame( [], $filters['brand_ids'] );
		$this->assertSame( [ 999 ], $filters['variable_product_ids'] );
	}

	/**
	 * @dataProvider provide_bad_filter_return_scenarios
	 *
	 * @param mixed $bad_filter_return The bad filter return value to test.
	 */
	public function test_override_filter_return_is_normalized_for_bad_filter( $bad_filter_return ): void {
		add_filter(
			'wc_stripe_agentic_commerce_product_filter',
			function () use ( $bad_filter_return ) {
				return $bad_filter_return;
			}
		);

		$filter  = new WC_Stripe_Agentic_Commerce_Product_Filter();
		$filters = $filter->get_filters();

		$expected_values = [
			'product_ids'          => [],
			'category_ids'         => [],
			'tag_ids'              => [],
			'brand_ids'            => [],
			'variable_product_ids' => [],
		];

		foreach ( $expected_values as $key => $value ) {
			$this->assertArrayHasKey( $key, $filters );
			$this->assertSame( $value, $filters[ $key ] );
		}
		$this->assertCount( count( $expected_values ), $filters );
	}

	/**
	 * Data provider for {@see test_override_filter_return_is_normalized_for_bad_filter()}.
	 *
	 * @return array
	 */
	public function provide_bad_filter_return_scenarios(): array {
		return [
			'object'  => [ (object) [ 'product_ids' => [ 1234 ] ] ],
			'string'  => [ 'not-an-array' ],
			'number'  => [ 1234 ],
			'boolean' => [ true ],
		];
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function create_term( string $slug, string $taxonomy ): array {
		if ( 'product_brand' === $taxonomy && ! taxonomy_exists( 'product_brand' ) ) {
			throw new Exception( 'Product brand taxonomy does not exist' );
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
	private function get_sorted_variation_ids( WC_Product_Variable $variable ): array {
		$ids = array_map( 'intval', $variable->get_children() );
		sort( $ids );

		return $ids;
	}
}
