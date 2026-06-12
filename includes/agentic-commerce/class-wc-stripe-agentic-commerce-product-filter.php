<?php
/**
 * Product filter for the Agentic Commerce feed.
 *
 * @package WooCommerce_Stripe
 * @since 10.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages filtering for the Agentic Commerce feed.
 *
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Product_Filter {

	/**
	 * Single option storing all supported filter values.
	 *
	 * @var string
	 * @since 10.9.0
	 */
	protected const OPTION_NAME = 'wc_stripe_agentic_commerce_product_filters';

	/**
	 * Local cache for filters to prevent re-calculating the filters for a given instance.
	 *
	 * @var array|null
	 */
	private ?array $filters = null;

	/**
	 * Read the persisted option and apply the override filter.
	 *
	 * @since 10.9.0
	 * @return array {
	 *     @type int[] $product_ids             Product IDs.
	 *     @type int[] $category_ids            Category taxonomy IDs.
	 *     @type int[] $tag_ids                 Tag taxonomy IDs.
	 *     @type int[] $brand_ids               Brand taxonomy IDs.
	 *     @type int[] $variable_product_ids    Variable product IDs.
	 * }
	 */
	public function get_filters(): array {
		if ( null !== $this->filters ) {
			return $this->filters;
		}

		$stored = get_option( self::OPTION_NAME, [] );
		if ( is_array( $stored ) ) {
			$filters = $this->normalize_filter_data( $stored );
		} else {
			$filters = [];
		}

		/**
		 * Filter the resolved set of filter inputs before resolution runs.
		 *
		 * Note that data returned from this filter is still normalized, so will be dropped if it is invalid.
		 *
		 * The args produced by the resolver still pass through the lower-level
		 * `wc_stripe_agentic_commerce_product_query_args` filter for full
		 * escape-hatch control over the final `wc_get_products()` arguments.
		 *
		 * @since 10.9.0
		 * @param array $filters {
		 *     Normalized filters derived from the persisted option.
		 *
		 *     @type int[] $product_ids             Product IDs.
		 *     @type int[] $category_ids            Category taxonomy IDs.
		 *     @type int[] $tag_ids                 Tag taxonomy IDs.
		 *     @type int[] $brand_ids               Brand taxonomy IDs.
		 *     @type int[] $variable_product_ids    Variable product IDs.
		 * }
		 */
		$filtered = apply_filters( 'wc_stripe_agentic_commerce_product_filter', $filters );

		$filtered = is_array( $filtered ) ? $filtered : [];

		// Ensure we have normalized filter data.
		$this->filters = $this->normalize_filter_data( $filtered );

		return $this->filters;
	}

	/**
	 * Save a set of filters.
	 *
	 * Note that input data is normalized, resolved, and verified before being saved.
	 * {@see self::get_filters()}.
	 *
	 * @since 10.9.0
	 * @param array $filters Raw caller-supplied filters. Unknown keys are ignored.
	 * @return bool True when the option was written successfully.
	 */
	public function save_filters( array $filters ): bool {
		$product_ids = [];
		if ( is_array( $filters['product_ids'] ?? null ) ) {
			$product_ids = $this->validate_product_ids(
				$filters['product_ids'],
				[
					\Automattic\WooCommerce\Enums\ProductType::SIMPLE,
					\Automattic\WooCommerce\Enums\ProductType::VARIATION,
				]
			);
		}

		$variable_product_ids = [];
		if ( is_array( $filters['variable_product_ids'] ?? null ) ) {
			$variable_product_ids = $this->validate_product_ids(
				$filters['variable_product_ids'],
				[ \Automattic\WooCommerce\Enums\ProductType::VARIABLE ]
			);
		}

		$category_ids = [];
		if ( is_array( $filters['categories'] ?? null ) ) {
			$category_ids = array_merge( $category_ids, $this->validate_taxonomy_ids( $filters['categories'], 'product_cat' ) );
		}
		if ( is_array( $filters['category_ids'] ?? null ) ) {
			$category_ids = array_merge( $category_ids, $this->validate_taxonomy_ids( $filters['category_ids'], 'product_cat' ) );
		}

		$tag_ids = [];
		if ( is_array( $filters['tags'] ?? null ) ) {
			$tag_ids = array_merge( $tag_ids, $this->validate_taxonomy_ids( $filters['tags'], 'product_tag' ) );
		}
		if ( is_array( $filters['tag_ids'] ?? null ) ) {
			$tag_ids = array_merge( $tag_ids, $this->validate_taxonomy_ids( $filters['tag_ids'], 'product_tag' ) );
		}

		// Only add brand filters when the product brand taxonomy is registered.
		$brand_ids = [];
		if ( taxonomy_exists( 'product_brand' ) ) {
			if ( is_array( $filters['brands'] ?? null ) ) {
				$brand_ids = array_merge( $brand_ids, $this->validate_taxonomy_ids( $filters['brands'], 'product_brand' ) );
			}
			if ( is_array( $filters['brand_ids'] ?? null ) ) {
				$brand_ids = array_merge( $brand_ids, $this->validate_taxonomy_ids( $filters['brand_ids'], 'product_brand' ) );
			}
		}

		$this->filters = [
			'product_ids'          => $product_ids,
			'category_ids'         => $category_ids,
			'tag_ids'              => $tag_ids,
			'brand_ids'            => $brand_ids,
			'variable_product_ids' => $variable_product_ids,
		];

		return update_option(
			self::OPTION_NAME,
			$this->filters,
			false
		);
	}

	/**
	 * Indicate whether we have any filters defined.
	 * {@see self::get_filters()}
	 *
	 * @since 10.9.0
	 * @return bool
	 */
	public function has_filters(): bool {
		$filters = $this->get_filters();
		return ! $this->are_filters_empty( $filters );
	}

	/**
	 * At present, the product feed infrastructure can only support query criteria
	 * that can be handled by a single wc_get_products() call. As such,
	 * this method indicates which field types will be used to construct the query.
	 *
	 * @see get_query_args()
	 * @since 10.9.0
	 * @return string[] Field types that will be used to construct the query.
	 */
	public function get_effective_filter_types(): array {
		$filters = $this->get_filters();

		if ( [] !== $filters['product_ids'] ) {
			return [ 'product_ids' ];
		}

		if ( [] !== $filters['variable_product_ids'] ) {
			return [ 'variable_product_ids' ];
		}

		$taxonomy_filters = [];
		if ( [] !== $filters['category_ids'] ) {
			$taxonomy_filters[] = 'category_ids';
		}
		if ( [] !== $filters['tag_ids'] ) {
			$taxonomy_filters[] = 'tag_ids';
		}
		if ( [] !== $filters['brand_ids'] && taxonomy_exists( 'product_brand' ) ) {
			$taxonomy_filters[] = 'brand_ids';
		}

		return $taxonomy_filters;
	}

	/**
	 * Get the query arguments to apply the specified filters.
	 * Note that not all filter criteria can be applied in a single query,
	 * so this method has a hierarchy for applicable criteria.
	 * Call {@see get_effective_filter_types()} to identify which criteria will be used.
	 *
	 * @since 10.9.0
	 * @return array|null WC_Product_Query arguments. Null when no filters are specified.
	 */
	public function get_query_args(): ?array {
		$filters = $this->get_filters();

		if ( $this->are_filters_empty( $filters ) ) {
			return null;
		}

		// The product feed infrastructure can only support single queries, so we can only return
		// query criteria that can be handled by a single wc_get_products() call.

		$standard_product_types = [
			\Automattic\WooCommerce\Enums\ProductType::SIMPLE,
			\Automattic\WooCommerce\Enums\ProductType::VARIATION,
		];

		if ( [] !== $filters['product_ids'] ) {
			return [
				'status'  => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'type'    => $standard_product_types,
				'include' => $filters['product_ids'],
			];
		}

		if ( [] !== $filters['variable_product_ids'] ) {
			return [
				'status'          => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'type'            => \Automattic\WooCommerce\Enums\ProductType::VARIATION,
				// WooCommerce doesn't have a mapped keyword for parent product IDs, so we use the WordPress post_parent__in.
				'post_parent__in' => $filters['variable_product_ids'],
			];
		}

		// Consolidate the taxonomy conditions into a single query.
		$taxonomy_conditions = [];

		if ( [] !== $filters['category_ids'] ) {
			$taxonomy_conditions['product_cat'] = $filters['category_ids'];
		}
		if ( [] !== $filters['tag_ids'] ) {
			$taxonomy_conditions['product_tag'] = $filters['tag_ids'];
		}
		if ( [] !== $filters['brand_ids'] && taxonomy_exists( 'product_brand' ) ) {
			$taxonomy_conditions['product_brand'] = $filters['brand_ids'];
		}

		if ( [] !== $taxonomy_conditions ) {
			$tax_query_clauses = [];
			foreach ( $taxonomy_conditions as $taxonomy => $term_ids ) {
				$tax_query_clauses[] = [
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $term_ids,
					'operator'         => 'IN',
					'include_children' => false,
				];
			}
			if ( count( $tax_query_clauses ) > 1 ) {
				$tax_query_clauses['relation'] = 'OR';
			}

			return [
				'type'      => $standard_product_types,
				'status'    => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				// Use a nested array to ensure our OR is properly encapsulated.
				'tax_query' => [ $tax_query_clauses ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			];
		}

		return null;
	}

	/**
	 * Indicate whether the supplied filters are empty.
	 *
	 * @since 10.9.0
	 * @param array $filters
	 * @return bool
	 */
	private function are_filters_empty( array $filters ): bool {
		return ( [] === $filters['product_ids'] )
			&& ( [] === $filters['category_ids'] )
			&& ( [] === $filters['tag_ids'] )
			&& ( [] === $filters['brand_ids'] )
			&& ( [] === $filters['variable_product_ids'] );
	}

	/**
	 * Validate an array of possible taxonomy IDs and slugs and return an
	 * array of valid term IDs.
	 *
	 * Empty values are dropped and string terms are trimmed.
	 * Term slugs are resolved to term IDs via `get_term_by()`,
	 * and unknown term slugs and term IDs are removed.
	 *
	 * @since 10.9.0
	 * @param array  $values   Array of taxonomy IDs and/or slugs.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[] List of valid term IDs.
	 */
	private function validate_taxonomy_ids( array $values, string $taxonomy ): array {
		$validated_term_ids = [];

		foreach ( $values as $value ) {
			if ( is_int( $value ) || ctype_digit( $value ) ) {
				$term_id = (int) $value;
				if ( $term_id <= 0 ) {
					continue;
				}
				$term = get_term( $term_id, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$validated_term_ids[] = $term->term_id;
				}

				continue;
			}

			if ( is_string( $value ) ) {
				$value = trim( $value );
				if ( '' === $value ) {
					continue;
				}
				$term = get_term_by( 'slug', $value, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$validated_term_ids[] = $term->term_id;
				}

				continue;
			}
		}

		return array_values( array_unique( $validated_term_ids ) );
	}

	/**
	 * Normalize the filter data, which may be stored in the option or provided by a filter.
	 *
	 * @since 10.9.0
	 * @param array $raw The raw data stored in the option.
	 * @return array {
	 *     @type int[] $product_ids             Product IDs.
	 *     @type int[] $category_ids            Category taxonomy IDs.
	 *     @type int[] $tag_ids                 Tag taxonomy IDs.
	 *     @type int[] $brand_ids               Brand taxonomy IDs.
	 *     @type int[] $variable_product_ids    Variable product IDs.
	 *                                            them to their variations. Default false.
	 * }
	 */
	private function normalize_filter_data( array $raw ): array {
		$normalized_data = [
			'product_ids'          => [],
			'category_ids'         => [],
			'tag_ids'              => [],
			'brand_ids'            => [],
			'variable_product_ids' => [],
		];

		foreach ( array_keys( $normalized_data ) as $key ) {
			if ( is_array( $raw[ $key ] ?? null ) ) {
				$normalized_data[ $key ] = $this->normalize_ids( $raw[ $key ] );
			}
		}

		if ( ! taxonomy_exists( 'product_brand' ) ) {
			$normalized_data['brand_ids'] = [];
		}

		return $normalized_data;
	}

	/**
	 * Validate a list of product IDs.
	 *
	* @since 10.9.0
	 * @param mixed[]  $product_ids           List of product IDs to validate.
	 * @param string[] $allowed_product_types List of allowed product types.
	 * @return int[] List of validated product IDs.
	 */
	private function validate_product_ids( array $product_ids, array $allowed_product_types ): array {
		$candidate_product_ids = [];
		foreach ( $product_ids as $possible_product_id ) {
			if ( ! is_int( $possible_product_id ) && ! ctype_digit( $possible_product_id ) ) {
				continue;
			}
			$possible_product_id = (int) $possible_product_id;
			if ( $possible_product_id <= 0 ) {
				continue;
			}

			$candidate_product_ids[] = $possible_product_id;
		}

		if ( [] === $candidate_product_ids ) {
			return [];
		}

		$valid_product_ids = wc_get_products(
			[
				'include' => $candidate_product_ids,
				'type'    => $allowed_product_types,
				'return'  => 'ids',
			]
		);

		if ( [] === $valid_product_ids ) {
			return [];
		}

		// Make sure we have unique, integer product IDs.
		return array_values( array_filter( array_map( 'intval', array_unique( (array) $valid_product_ids ) ) ) );
	}

	/**
	 * Normalize a possibly-malformed value to int[] — drop non-numerics and `<= 0`.
	 *
	 * @since 10.9.0
	 * @param mixed $value
	 * @return int[]
	 */
	private function normalize_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$out = [];
		foreach ( $value as $entry ) {
			if ( ! is_numeric( $entry ) ) {
				continue;
			}
			$id = (int) $entry;
			if ( $id <= 0 ) {
				continue;
			}
			$out[] = $id;
		}
		return array_values( array_unique( $out ) );
	}
}
