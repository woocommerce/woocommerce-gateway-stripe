<?php
/**
 * Product filter for the Agentic Commerce feed.
 *
 * Resolves a union (OR) of four input dimensions — explicit product IDs and
 * product category/tag/brand term slugs — into a flat list of WooCommerce
 * product IDs suitable for passing as `wc_get_products( 'include' => ... )`.
 * Variable products selected by any dimension are expanded to their variation
 * children; the variable parent itself is dropped because the feed only carries
 * `simple` and `variation` types.
 *
 * @package WooCommerce_Stripe
 * @since 10.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves Agentic Commerce feed inputs into a WooCommerce product ID set.
 *
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Product_Filter {

	/**
	 * Single keyed option storing all four dimensions.
	 *
	 * Protected because the storage key is an implementation detail; external
	 * callers should round-trip through {@see self::get_inputs()} and
	 * {@see self::set_inputs()} rather than touching the raw option.
	 *
	 * @var string
	 * @since 10.9.0
	 */
	protected const OPTION_NAME = 'wc_stripe_agentic_commerce_product_filters';

	/**
	 * Sentinel returned by {@see self::resolve_ids()} when filters are
	 * configured but resolve to zero matching products.
	 *
	 * `wc_get_products( [ 'include' => [] ] )` is the WP/WC empty-array
	 * footgun: passing an empty array is treated as "no filter" and returns
	 * every product. Using `[0]` (a never-existing post ID) forces the data
	 * store to return nothing instead, which lets the caller treat
	 * "configured but no matches" as the merchant's intent.
	 *
	 * @var int[]
	 * @since 10.9.0
	 */
	private const EMPTY_RESOLUTION = [ 0 ];

	/**
	 * Read the persisted option and apply the override filter.
	 *
	 * @since 10.9.0
	 * @return array{product_ids: int[], categories: string[], tags: string[], brands: string[]}
	 */
	public function get_inputs(): array {
		$stored = get_option( self::OPTION_NAME, [] );
		$shape  = $this->coerce_shape( is_array( $stored ) ? $stored : [] );

		/**
		 * Filter the resolved set of product-filter inputs before resolution runs.
		 *
		 * Use this to override the option-derived input set entirely or to patch
		 * individual dimensions from code. The return value is coerced back into
		 * the strict shape used by the resolver, so non-conforming entries are
		 * dropped silently.
		 *
		 * The args produced by the resolver still pass through the lower-level
		 * `wc_stripe_agentic_commerce_product_query_args` filter for full
		 * escape-hatch control over the final `wc_get_products()` arguments.
		 *
		 * @since 10.9.0
		 * @param array{
		 *     product_ids: int[],
		 *     categories: string[],
		 *     tags: string[],
		 *     brands: string[],
		 * } $shape Inputs derived from the persisted option.
		 */
		$filtered = apply_filters( 'wc_stripe_agentic_commerce_product_filters', $shape );

		return $this->coerce_shape( is_array( $filtered ) ? $filtered : [] );
	}

	/**
	 * Persist a caller-supplied input set, normalizing IDs/slugs first.
	 *
	 * Taxonomy dimensions accept a mix of int term IDs and string slugs;
	 * normalization rewrites everything to slug strings (resolving term IDs
	 * via `get_term()`) and drops entries that can't be resolved. After this
	 * method returns, the stored option matches the strict shape returned by
	 * {@see self::get_inputs()}.
	 *
	 * @since 10.9.0
	 * @param array $raw Raw caller-supplied input. Unknown keys are ignored.
	 * @return bool True when the option was written successfully.
	 */
	public function set_inputs( array $raw ): bool {
		$product_ids = isset( $raw['product_ids'] ) && is_array( $raw['product_ids'] )
			? $this->normalize_product_ids( $raw['product_ids'] )
			: [];

		$categories = isset( $raw['categories'] ) && is_array( $raw['categories'] )
			? $this->normalize_taxonomy_input( $raw['categories'], 'product_cat' )
			: [];

		$tags = isset( $raw['tags'] ) && is_array( $raw['tags'] )
			? $this->normalize_taxonomy_input( $raw['tags'], 'product_tag' )
			: [];

		// Brand dimension silently drops when the taxonomy isn't registered on
		// this site — matches the mapper's `taxonomy_exists` guard so we don't
		// persist brand slugs that can never resolve.
		$brands = taxonomy_exists( 'product_brand' ) && isset( $raw['brands'] ) && is_array( $raw['brands'] )
			? $this->normalize_taxonomy_input( $raw['brands'], 'product_brand' )
			: [];

		return update_option(
			self::OPTION_NAME,
			[
				'product_ids' => $product_ids,
				'categories'  => $categories,
				'tags'        => $tags,
				'brands'      => $brands,
			],
			false
		);
	}

	/**
	 * True when any dimension is non-empty after option read + override filter.
	 *
	 * @since 10.9.0
	 * @return bool
	 */
	public function is_configured(): bool {
		$inputs = $this->get_inputs();
		return ! empty( $inputs['product_ids'] )
			|| ! empty( $inputs['categories'] )
			|| ! empty( $inputs['tags'] )
			|| ! empty( $inputs['brands'] );
	}

	/**
	 * Resolve the union of all configured dimensions to a product ID set.
	 *
	 * Variable products selected by any dimension expand to their variation
	 * children and the parent is dropped — only `simple` and `variation` types
	 * are returned, matching the feed's supported types. Returns
	 * {@see self::EMPTY_RESOLUTION} (`[0]`) when filters are configured but
	 * the union is empty, so callers can pass the result as
	 * `wc_get_products()`'s `include` without tripping the empty-array footgun.
	 *
	 * @since 10.9.0
	 * @return int[] Sorted, deduplicated list of product IDs.
	 */
	public function resolve_ids(): array {
		$inputs = $this->get_inputs();

		$union = [];

		if ( ! empty( $inputs['product_ids'] ) ) {
			$union = array_merge( $union, $this->resolve_by_ids( $inputs['product_ids'] ) );
		}
		if ( ! empty( $inputs['categories'] ) ) {
			$union = array_merge( $union, $this->resolve_by_taxonomy( 'product_cat', $inputs['categories'] ) );
		}
		if ( ! empty( $inputs['tags'] ) ) {
			$union = array_merge( $union, $this->resolve_by_taxonomy( 'product_tag', $inputs['tags'] ) );
		}
		if ( ! empty( $inputs['brands'] ) && taxonomy_exists( 'product_brand' ) ) {
			$union = array_merge( $union, $this->resolve_by_taxonomy( 'product_brand', $inputs['brands'] ) );
		}

		if ( empty( $union ) ) {
			return self::EMPTY_RESOLUTION;
		}

		$union = array_values( array_unique( array_map( 'intval', $union ) ) );
		sort( $union );

		return $union;
	}

	/**
	 * Expand an explicit product-ID input into the set of feed-eligible IDs.
	 *
	 * Simple and variation IDs survive as-is. Variable parent IDs are expanded
	 * to their variation children and dropped. Grouped/external IDs match
	 * neither query inside {@see self::expand_variables()} and fall away.
	 *
	 * @since 10.9.0
	 * @param int[] $ids Caller-supplied product IDs (already normalized).
	 * @return int[]
	 */
	private function resolve_by_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return [];
		}
		return $this->expand_variables( $ids );
	}

	/**
	 * Resolve a taxonomy dimension (slugs) to feed-eligible product IDs.
	 *
	 * Uses `wc_get_products()`'s native `category` and `tag` slug-array keys
	 * for the standard WooCommerce taxonomies; falls back to an explicit
	 * `tax_query` with `field => 'slug'` for `product_brand`. The matched
	 * parent posts go through {@see self::expand_variables()} so variable
	 * parents become their variation children.
	 *
	 * @since 10.9.0
	 * @param string   $taxonomy Taxonomy slug (`product_cat`, `product_tag`, `product_brand`).
	 * @param string[] $slugs    Term slugs (already normalized).
	 * @return int[]
	 */
	private function resolve_by_taxonomy( string $taxonomy, array $slugs ): array {
		if ( empty( $slugs ) ) {
			return [];
		}

		$base_args = [
			'status' => [ 'publish' ],
			'limit'  => -1,
			'return' => 'ids',
		];

		if ( 'product_cat' === $taxonomy ) {
			$args = array_merge( $base_args, [ 'category' => $slugs ] );
		} elseif ( 'product_tag' === $taxonomy ) {
			$args = array_merge( $base_args, [ 'tag' => $slugs ] );
		} else {
			$args = array_merge(
				$base_args,
				[
					'tax_query' => [ // phpcs:ignore WordPress.DB.SlowDB.slow_db_query_tax_query -- Bounded by user-supplied slug list.
						[
							'taxonomy' => $taxonomy,
							'field'    => 'slug',
							'terms'    => $slugs,
						],
					],
				]
			);
		}

		$matched = wc_get_products( $args );
		if ( ! is_array( $matched ) || empty( $matched ) ) {
			return [];
		}

		return $this->expand_variables( array_map( 'intval', $matched ) );
	}

	/**
	 * Batched variable→variation expansion for a candidate ID set.
	 *
	 * Strategy:
	 *  1. Keep simple/variation IDs from the candidate set as-is.
	 *  2. Find variable parents in the candidate set and pull their variation
	 *     children with a single follow-up query.
	 *  3. Union the two lists and dedupe.
	 *
	 * Grouped/external products in the candidate set match neither query and
	 * fall away — they're not feed-eligible anyway. Two-to-three bounded
	 * `wc_get_products()` calls keeps memory and round-trip cost predictable
	 * even for large candidate sets.
	 *
	 * @since 10.9.0
	 * @param int[] $candidate_ids
	 * @return int[]
	 */
	private function expand_variables( array $candidate_ids ): array {
		if ( empty( $candidate_ids ) ) {
			return [];
		}

		$base = [
			'status'  => [ 'publish' ],
			'limit'   => -1,
			'return'  => 'ids',
			'include' => $candidate_ids,
		];

		$leaves = wc_get_products(
			array_merge( $base, [ 'type' => [ 'simple', 'variation' ] ] )
		);
		$leaves = is_array( $leaves ) ? array_map( 'intval', $leaves ) : [];

		$variable_parents = wc_get_products(
			array_merge( $base, [ 'type' => 'variable' ] )
		);
		$variable_parents = is_array( $variable_parents ) ? array_map( 'intval', $variable_parents ) : [];

		$children = [];
		if ( ! empty( $variable_parents ) ) {
			$children = wc_get_products(
				[
					'status' => [ 'publish' ],
					'limit'  => -1,
					'return' => 'ids',
					'type'   => 'variation',
					'parent' => $variable_parents,
				]
			);
			$children = is_array( $children ) ? array_map( 'intval', $children ) : [];
		}

		return array_values( array_unique( array_merge( $leaves, $children ) ) );
	}

	/**
	 * Normalize a caller-supplied product-ID list.
	 *
	 * Casts numeric values to int, drops `<= 0`, dedupes.
	 *
	 * @since 10.9.0
	 * @param array $raw
	 * @return int[]
	 */
	private function normalize_product_ids( array $raw ): array {
		$out = [];
		foreach ( $raw as $value ) {
			if ( ! is_numeric( $value ) ) {
				continue;
			}
			$id = (int) $value;
			if ( $id <= 0 ) {
				continue;
			}
			$out[] = $id;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize a caller-supplied taxonomy input to slug strings.
	 *
	 * Strings are trimmed and any empty values are dropped. Int term IDs are
	 * resolved to slugs via `get_term()`; unknown term IDs fall away.
	 *
	 * @since 10.9.0
	 * @param array  $raw
	 * @param string $taxonomy
	 * @return string[]
	 */
	private function normalize_taxonomy_input( array $raw, string $taxonomy ): array {
		$out = [];
		foreach ( $raw as $value ) {
			if ( is_string( $value ) ) {
				$value = trim( $value );
				if ( '' === $value ) {
					continue;
				}
				$out[] = $value;
				continue;
			}
			if ( is_numeric( $value ) ) {
				$term_id = (int) $value;
				if ( $term_id <= 0 ) {
					continue;
				}
				$term = get_term( $term_id, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$out[] = $term->slug;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Coerce a possibly-malformed shape (hand-edited option, filter return,
	 * etc.) into the strict shape used by the resolver. Missing keys default
	 * to empty arrays, non-array dimension values are dropped, non-numeric
	 * product IDs are dropped, and non-string taxonomy values are dropped.
	 *
	 * @since 10.9.0
	 * @param array $raw
	 * @return array{product_ids: int[], categories: string[], tags: string[], brands: string[]}
	 */
	private function coerce_shape( array $raw ): array {
		return [
			'product_ids' => $this->coerce_ids( $raw['product_ids'] ?? [] ),
			'categories'  => $this->coerce_strings( $raw['categories'] ?? [] ),
			'tags'        => $this->coerce_strings( $raw['tags'] ?? [] ),
			'brands'      => $this->coerce_strings( $raw['brands'] ?? [] ),
		];
	}

	/**
	 * Coerce a possibly-malformed value to int[] — drop non-numerics and `<= 0`.
	 *
	 * @since 10.9.0
	 * @param mixed $value
	 * @return int[]
	 */
	private function coerce_ids( $value ): array {
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

	/**
	 * Coerce a possibly-malformed value to string[] — drop non-strings and empties.
	 *
	 * @since 10.9.0
	 * @param mixed $value
	 * @return string[]
	 */
	private function coerce_strings( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$out = [];
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			$out[] = $entry;
		}
		return array_values( array_unique( $out ) );
	}
}
