<?php
/**
 * Product filter for the Agentic Commerce feed.
 *
 * @package WooCommerce_Stripe
 * @since 10.8.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages filtering for the Agentic Commerce feed.
 *
 * @since 10.8.0
 */
class WC_Stripe_Agentic_Commerce_Product_Filter {

	/**
	 * Single option storing all supported filter values.
	 *
	 * @var string
	 * @since 10.8.0
	 */
	protected const OPTION_NAME = 'wc_stripe_agentic_commerce_product_filters';

	/**
	 * Value returned by {@see self::get_filtered_product_ids()} to indicate no matching products.
	 * Intentionally not [] as that is treated as "no filter" by the Agentic feed logic.
	 * Uses 0 as the product ID to indicate a non-existent product ID should be matched.
	 *
	 * @var int[]
	 * @since 10.8.0
	 */
	private const EMPTY_RESOLUTION = [ 0 ];

	/**
	 * Read the persisted option and apply the override filter.
	 *
	 * @since 10.8.0
	 * @return array {
	 *     @type int[] $product_ids               Product IDs.
	 *     @type int[] $category_ids              Category taxonomy IDs.
	 *     @type int[] $tag_ids                   Tag taxonomy IDs.
	 *     @type int[] $brand_ids                 Brand taxonomy IDs.
	 *     @type bool  $include_variable_products Whether to query for variable products, and then resolve
	 *                                            them to their variations. Default false.
	 * }
	 */
	public function get_filters(): array {
		$stored  = get_option( self::OPTION_NAME, [] );
		$filters = $this->normalize_filter_data( is_array( $stored ) ? $stored : [] );

		/**
		 * Filter the resolved set of filter inputs before resolution runs.
		 *
		 * Note that data returned from this filter is still normalized, so will be dropped if it is invalid.
		 *
		 * The args produced by the resolver still pass through the lower-level
		 * `wc_stripe_agentic_commerce_product_query_args` filter for full
		 * escape-hatch control over the final `wc_get_products()` arguments.
		 *
		 * @since 10.8.0
		 * @param array $filters {
		 *     Normalized filters derived from the persisted option.
		 *
		 *     @type int[] $product_ids               Product IDs.
		 *     @type int[] $category_ids              Category taxonomy IDs.
		 *     @type int[] $tag_ids                   Tag taxonomy IDs.
		 *     @type int[] $brand_ids                 Brand taxonomy IDs.
		 *     @type bool  $include_variable_products Whether to query for variable products, and then resolve
		 *                                            them to their variations. Default false.
		 * }
		 */
		$filtered = apply_filters( 'wc_stripe_agentic_commerce_product_filter', $filters );

		return $this->normalize_filter_data( is_array( $filtered ) ? $filtered : [] );
	}

	/**
	 * Save a set of filters.
	 *
	 * Note that input data is normalized, resolved, and verified before being saved.
	 * {@see self::get_filters()}.
	 *
	 * @since 10.8.0
	 * @param array $filters Raw caller-supplied filters. Unknown keys are ignored.
	 * @return bool True when the option was written successfully.
	 */
	public function save_filters( array $filters ): bool {
		$product_ids = [];
		if ( is_array( $filters['product_ids'] ?? null ) ) {
			$product_ids = $this->normalize_ids( $filters['product_ids'] );
		}

		$category_ids = [];
		if ( is_array( $filters['categories'] ?? null ) ) {
			$category_ids = array_merge( $category_ids, $this->normalize_taxonomy_ids( $filters['categories'], 'product_cat' ) );
		}
		if ( is_array( $filters['category_ids'] ?? null ) ) {
			$category_ids = array_merge( $category_ids, $this->normalize_taxonomy_ids( $filters['category_ids'], 'product_cat' ) );
		}

		$tag_ids = [];
		if ( is_array( $filters['tags'] ?? null ) ) {
			$tag_ids = array_merge( $tag_ids, $this->normalize_taxonomy_ids( $filters['tags'], 'product_tag' ) );
		}
		if ( is_array( $filters['tag_ids'] ?? null ) ) {
			$tag_ids = array_merge( $tag_ids, $this->normalize_taxonomy_ids( $filters['tag_ids'], 'product_tag' ) );
		}

		// Only add brand filters when the product brand taxonomy is registered.
		$brand_ids = [];
		if ( taxonomy_exists( 'product_brand' ) ) {
			if ( is_array( $filters['brands'] ?? null ) ) {
				$brand_ids = array_merge( $brand_ids, $this->normalize_taxonomy_ids( $filters['brands'], 'product_brand' ) );
			}
			if ( is_array( $filters['brand_ids'] ?? null ) ) {
				$brand_ids = array_merge( $brand_ids, $this->normalize_taxonomy_ids( $filters['brand_ids'], 'product_brand' ) );
			}
		}

		$include_variable_products = true === ( $filters['include_variable_products'] ?? false );

		return update_option(
			self::OPTION_NAME,
			[
				'product_ids'               => $product_ids,
				'category_ids'              => $category_ids,
				'tag_ids'                   => $tag_ids,
				'brand_ids'                 => $brand_ids,
				'include_variable_products' => $include_variable_products,
			],
			false
		);
	}

	/**
	 * Indicate whether we have any filters defined.
	 * {@see self::get_filters()}
	 *
	 * @since 10.8.0
	 * @return bool
	 */
	public function has_filters(): bool {
		$filters = $this->get_filters();
		return ! $this->are_filters_empty( $filters );
	}

	/**
	 * Get the set of product IDs product by ORing all configured filters.
	 * Note that only simple and variation products are returned.
	 *
	 * Returns {@see self::EMPTY_RESOLUTION} (`[0]`) when the result is empty.
	 *
	 * @since 10.8.0
	 * @return int[] List of product IDs.
	 */
	public function get_filtered_product_ids(): array {
		$filters = $this->get_filters();

		if ( $this->are_filters_empty( $filters ) ) {
			return self::EMPTY_RESOLUTION;
		}

		$candidate_ids = [];

		if ( [] !== $filters['product_ids'] ) {
			$product_id_query = [
				'status'  => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'type'    => [
					\Automattic\WooCommerce\Enums\ProductType::SIMPLE,
					\Automattic\WooCommerce\Enums\ProductType::VARIATION,
					\Automattic\WooCommerce\Enums\ProductType::VARIABLE,
				],
				'limit'   => -1,
				'return'  => 'ids',
				'include' => $filters['product_ids'],
			];
			$product_ids      = (array) wc_get_products( $product_id_query );
			$candidate_ids    = array_merge( $candidate_ids, $product_ids );
		}

		// Consolidate the taxonomy conditions so we can run a single query for them.
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
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_ids,
				];
			}
			if ( count( $tax_query_clauses ) > 1 ) {
				$tax_query_clauses['relation'] = 'OR';
			}

			$tax_query_args = [
				'type'      => [
					\Automattic\WooCommerce\Enums\ProductType::SIMPLE,
					\Automattic\WooCommerce\Enums\ProductType::VARIATION,
					\Automattic\WooCommerce\Enums\ProductType::VARIABLE,
				],
				'status'    => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'limit'     => -1,
				'return'    => 'ids',
				'tax_query' => $tax_query_clauses,
			];

			$tax_product_ids = (array) wc_get_products( $tax_query_args );

			$candidate_ids = array_merge( $candidate_ids, $tax_product_ids );
		}

		$candidate_ids = array_unique( array_map( 'intval', $candidate_ids ) );
		$candidate_ids = array_values( array_filter( $candidate_ids ) );

		if ( [] === $candidate_ids ) {
			return self::EMPTY_RESOLUTION;
		}

		// We need to resolve variable products to their variations.
		// First step is to identify which product IDs in our list are variable products.
		$variable_product_ids = (array) wc_get_products(
			[
				'status'  => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'limit'   => -1,
				'return'  => 'ids',
				'type'    => [ \Automattic\WooCommerce\Enums\ProductType::VARIABLE ],
				'include' => $candidate_ids,
			]
		);

		if ( [] !== $variable_product_ids ) {
			// We have some variable products, so let's remove them from $candidate_ids and then find the child variations.
			$candidate_ids = array_diff( $candidate_ids, $variable_product_ids );

			$variation_query = [
				'status'          => [ \Automattic\WooCommerce\Enums\ProductStatus::PUBLISH ],
				'limit'           => -1,
				'return'          => 'ids',
				'type'            => [ \Automattic\WooCommerce\Enums\ProductType::VARIATION ],
				// WooCommerce doesn't have a mapped keyword for parent product IDs, so we use the WordPress post_parent__in.
				'post_parent__in' => $variable_product_ids,
			];

			$variation_product_ids = (array) wc_get_products( $variation_query );

			$candidate_ids = array_merge( $candidate_ids, $variation_product_ids );
		}

		$candidate_ids = array_values( array_unique( $candidate_ids ) );

		if ( [] === $candidate_ids ) {
			return self::EMPTY_RESOLUTION;
		}

		sort( $candidate_ids );

		return $candidate_ids;
	}

	/**
	 * Indicate whether the supplied filters are empty.
	 *
	 * @since 10.8.0
	 * @param array $filters
	 * @return bool
	 */
	private function are_filters_empty( array $filters ): bool {
		// Note that we don't check $filters['include_variable_products'] here,
		// as it only applies when other filters are present.
		return ( [] === $filters['product_ids'] )
			&& ( [] === $filters['category_ids'] )
			&& ( [] === $filters['tag_ids'] )
			&& ( [] === $filters['brand_ids'] );
	}

	/**
	 * Normalize an array of taxonomy IDs and slugs to an array of term IDs.
	 *
	 * Empty values are dropped and string terms are trimmed. Term slugs are
	 * resolved to term IDs via `get_term_by()` -- unknown term slugs are removed.
	 *
	 * @since 10.8.0
	 * @param array  $values   Array of taxonomy IDs and/or slugs.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[]
	 */
	private function normalize_taxonomy_ids( array $values, string $taxonomy ): array {
		$out = [];
		foreach ( $values as $value ) {
			if ( ctype_digit( $value ) ) {
				$term_id = (int) $value;
				if ( $term_id <= 0 ) {
					continue;
				}
				$term = get_term( $term_id, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$out[] = $term->term_id;
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
					$out[] = $term->term_id;
				}

				continue;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize the filter data, which may be stored in the option or provided by a filter.
	 *
	 * @since 10.8.0
	 * @param array $raw The raw data stored in the option.
	 * @return array {
	 *     @type int[] $product_ids               Product IDs.
	 *     @type int[] $category_ids              Category taxonomy IDs.
	 *     @type int[] $tag_ids                   Tag taxonomy IDs.
	 *     @type int[] $brand_ids                 Brand taxonomy IDs.
	 *     @type bool  $include_variable_products Whether to query for variable products, and then resolve
	 *                                            them to their variations. Default false.
	 * }
	 */
	private function normalize_filter_data( array $raw ): array {
		return [
			'product_ids'               => $this->normalize_ids( $raw['product_ids'] ?? [] ),
			'category_ids'              => $this->normalize_ids( $raw['category_ids'] ?? [] ),
			'tag_ids'                   => $this->normalize_ids( $raw['tag_ids'] ?? [] ),
			'brand_ids'                 => $this->normalize_ids( $raw['brand_ids'] ?? [] ),
			'include_variable_products' => true === ( $raw['include_variable_products'] ?? false ),
		];
	}

	/**
	 * Normalize a possibly-malformed value to int[] — drop non-numerics and `<= 0`.
	 *
	 * @since 10.8.0
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
