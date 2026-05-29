<?php
/**
 * Stripe Agentic Commerce Product Loader.
 *
 * Extend the WooCommerce ProductLoader implementation to support
 * additional custom query results in the standard product feed.
 * The main use cases for this are to query for specific product IDs,
 * and to query for variable products that need to have their variations included.
 *
 * @package WooCommerce_Stripe
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductLoader' ) ) {
	return;
}

/**
 * Stateful product loader that allows custom queries to be added to the
 * results from the primary query.
 *
 * The custom query args are passed via the `wc_stripe_custom_product_queries`
 * key in `$args`. The values are forwarded to {@see wc_get_products()} independently.
 *
 * Instance state is reset whenever a `page === 1` paginated call occurs.
 */
class WC_Stripe_Agentic_Commerce_Product_Loader extends \Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductLoader {

	/**
	 * Argument key used to pass additional custom `wc_get_products()` query filters.
	 * Value must be an array of args arrays.
	 *
	 * @var string
	 */
	public const CUSTOM_QUERIES_KEY = 'wc_stripe_custom_product_queries';

	/**
	 * Total parent results, calculated on page 1 of a paginated walk.
	 */
	private int $parent_total = 0;

	/**
	 * Total custom results, calculated on page 1 of a paginated walk for each custom query.
	 *
	 * @var array<int|string, int>
	 */
	private array $custom_totals = [];

	/**
	 * Total combined results, calculated on page 1 of a paginated walk.
	 */
	private int $combined_total = 0;

	/**
	 * Running count of parent products returned across paginated calls.
	 */
	private int $parent_returned = 0;

	/**
	 * Running count of custom products returned across paginated calls for each custom query.
	 *
	 * @var array<int|string, int>
	 */
	private array $custom_returned = [];

	/**
	 * Combined `max_num_pages`, calculated on page 1 of a paginated walk.
	 */
	private int $combined_max_pages = 0;

	/**
	 * Keys of custom queries where all results have been returned.
	 *
	 * @var array<int|string>
	 */
	private array $exhausted_custom_query_keys = [];

	/**
	 * Whether the paginated state has been initialized via a `page === 1` call.
	 */
	private bool $state_initialized = false;

	/**
	 * Get combined products from the parent and custom queries.
	 *
	 * @param array $args Standard `wc_get_products()` args plus the optional
	 *                    {@see self::CUSTOM_QUERIES_KEY} for custom query args.
	 * @return array|\stdClass Array of products when `paginate` is not true; a
	 *                         stdClass with `products`, `total`, and
	 *                         `max_num_pages` when `paginate` is true.
	 */
	public function get_products( array $args ) {
		$custom_queries = [];
		if ( array_key_exists( self::CUSTOM_QUERIES_KEY, $args ) ) {
			$custom_query_candidates = $args[ self::CUSTOM_QUERIES_KEY ];

			if ( is_array( $custom_query_candidates ) ) {
				foreach ( $custom_query_candidates as $custom_query_key => $custom_query_candidate ) {
					if ( is_array( $custom_query_candidate ) && [] !== $custom_query_candidate ) {
						$custom_queries[ $custom_query_key ] = $custom_query_candidate;
					}
				}
			}

			unset( $args[ self::CUSTOM_QUERIES_KEY ] );
		}

		$paginate = true === ( $args['paginate'] ?? false );
		$limit    = (int) ( $args['limit'] ?? -1 );
		$page     = (int) ( $args['page'] ?? 1 );

		if ( ! $paginate ) {
			$parent_products = parent::get_products( $args );

			$parent_products = is_array( $parent_products ) ? $parent_products : [];

			foreach ( $custom_queries as $custom_query_key => $custom_query ) {
				// Ensure we have matching pagination parameters for the custom queries.
				$custom_args     = array_merge(
					$custom_query,
					[
						'page'     => 1,
						'limit'    => $limit,
						'paginate' => false,
					]
				);
				$custom_products = wc_get_products( $custom_args );

				if ( is_array( $custom_products ) ) {
					$parent_products = array_merge( $parent_products, $custom_products );
				}
			}

			return $parent_products;
		}

		if ( 1 === $page ) {
			return $this->get_first_page( $args, $custom_queries, $limit );
		}

		if ( ! $this->state_initialized ) {
			// Caller skipped page 1 - in this state return the parent results only.
			return parent::get_products( $args );
		}

		return $this->get_subsequent_page( $args, $custom_queries, $limit );
	}

	/**
	 * Get first page of results and initialize all page-related state.
	 *
	 * @param array   $args           Primary query args.
	 * @param array[] $custom_queries Array of custom query args.
	 * @param int     $limit          Page size.
	 * @return \stdClass
	 */
	private function get_first_page( array $args, array $custom_queries, int $limit ): \stdClass {
		$this->reset_state();

		$parent_result = parent::get_products( $args );

		$this->parent_total   = (int) $parent_result->total;
		$this->combined_total = $this->parent_total;

		if ( is_array( $parent_result->products ) ) {
			$products = $parent_result->products;
		} else {
			$products = [];
		}

		$this->parent_returned = count( $products );
		$returned              = $this->parent_returned;

		foreach ( $custom_queries as $custom_query_key => $custom_query ) {
			$custom_query_args = array_merge(
				$custom_query,
				[
					'paginate' => true,
					'page'     => 1,
					'limit'    => $limit,
				]
			);

			$custom_result = wc_get_products( $custom_query_args );

			$this->custom_totals[ $custom_query_key ]   = (int) $custom_result->total;
			$this->custom_returned[ $custom_query_key ] = 0;

			$this->combined_total += $this->custom_totals[ $custom_query_key ];

			// Check if we need to include these results in the first page of data.
			if ( $limit > 0 && is_array( $custom_result->products ) && $returned < $limit ) {
				$needed       = $limit - $returned;
				$custom_slice = array_slice( $custom_result->products, 0, $needed );
				$products     = array_merge( $products, $custom_slice );
				$added_count  = count( $custom_slice );

				$this->custom_returned[ $custom_query_key ] = $added_count;

				if ( $this->custom_returned[ $custom_query_key ] >= $this->custom_totals[ $custom_query_key ] ) {
					$this->exhausted_custom_query_keys[] = $custom_query_key;
				}

				$returned += $added_count;
			}
		}

		$this->combined_max_pages = $limit > 0
			? (int) ceil( $this->combined_total / $limit )
			: 1;

		$this->state_initialized = true;

		return $this->build_result( $products );
	}

	/**
	 * Get subsequent page of results.
	 *
	 * @param array   $args           Primary query args.
	 * @param array[] $custom_queries Custom query args.
	 * @param int     $limit          Page size.
	 * @return \stdClass
	 */
	private function get_subsequent_page( array $args, array $custom_queries, int $limit ): \stdClass {
		$products = [];

		if ( $this->parent_returned < $this->parent_total ) {
			$parent_result = parent::get_products( $args );
			if ( is_array( $parent_result->products ) ) {
				$products = $parent_result->products;

				$this->parent_returned += count( $products );
			}
		}

		$needed = 0;
		if ( $limit > 0 ) {
			$needed = $limit - count( $products );
		}

		if ( $needed > 0 ) {
			$non_exhausted_custom_query_keys = array_diff( array_keys( $this->custom_totals ), $this->exhausted_custom_query_keys );

			foreach ( $non_exhausted_custom_query_keys as $custom_query_key ) {
				$custom_query = $custom_queries[ $custom_query_key ];
				$custom_slice = $this->fetch_custom_slice( $custom_query, $needed, $this->custom_returned[ $custom_query_key ] );
				$products     = array_merge( $products, $custom_slice );
				$added_count  = count( $custom_slice );

				$this->custom_returned[ $custom_query_key ] += $added_count;
				if ( $this->custom_returned[ $custom_query_key ] >= $this->custom_totals[ $custom_query_key ] ) {
					$this->exhausted_custom_query_keys[] = $custom_query_key;
				}

				$needed -= $added_count;
				if ( $needed <= 0 ) {
					break;
				}
			}
		}

		return $this->build_result( $products );
	}

	/**
	 * Fetch a contiguous slice from the custom query.
	 *
	 * Uses `paginate => false` so `offset` is honored without conflicting with
	 * `paged` inside WP_Query.
	 *
	 * @param array $custom_filter Secondary query args.
	 * @param int   $limit         Number of products to fetch.
	 * @param int   $offset        Offset into the custom result set.
	 * @return array<int, \WC_Product>
	 */
	private function fetch_custom_slice( array $custom_filter, int $limit, int $offset ): array {
		$args = array_merge(
			$custom_filter,
			[
				'paginate' => false,
				'limit'    => $limit,
				'offset'   => $offset,
			]
		);

		$result = wc_get_products( $args );

		return is_array( $result ) ? $result : [];
	}

	/**
	 * Build the spliced stdClass result.
	 *
	 * @param array $products       Spliced product list.
	 * @return \stdClass
	 */
	private function build_result( array $products ): \stdClass {
		$result                = new \stdClass();
		$result->products      = $products;
		$result->total         = $this->combined_total;
		$result->max_num_pages = $this->combined_max_pages;

		return $result;
	}

	/**
	 * Reset paginated state at the start of a new walk.
	 */
	private function reset_state(): void {
		$this->parent_total                = 0;
		$this->custom_totals               = [];
		$this->combined_total              = 0;
		$this->parent_returned             = 0;
		$this->custom_returned             = [];
		$this->combined_max_pages          = 0;
		$this->state_initialized           = false;
		$this->exhausted_custom_query_keys = [];
	}
}
