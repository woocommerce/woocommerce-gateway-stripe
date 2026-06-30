<?php
/**
 * Feed preview generator for Stripe Agentic Commerce.
 *
 * Produces an on-demand, upload-free snapshot of what the next product feed
 * sync would contain: how many products are included, how many are excluded by
 * the merchant's visibility filter, and which products would be rejected by the
 * feed validator (and why). Lets merchants diagnose "why isn't my product
 * showing up for agents?" inside WooCommerce instead of digging through Stripe
 * ImportSet error reports.
 *
 * @package WooCommerce_Stripe
 * @since 10.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the merchant-facing feed preview by running the same mapper + validator
 * pipeline the sync uses, without writing a CSV or uploading anything to Stripe.
 *
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Feed_Preview {

	/**
	 * Products loaded per page while walking the catalog. Matches the
	 * ProductWalker default so the preview streams in bounded batches rather
	 * than buffering the whole catalog — the same memory contract the sync
	 * walker honours.
	 *
	 * @var int
	 * @since 10.9.0
	 */
	private const PER_PAGE = 100;

	/**
	 * Default cap on the number of invalid products returned with full detail.
	 * Failures beyond the cap are summed into the `truncated` count so the
	 * response stays bounded regardless of catalog size.
	 *
	 * @var int
	 * @since 10.9.0
	 */
	public const DEFAULT_DETAIL_LIMIT = 50;

	/**
	 * Default cap on the number of products the preview walks.
	 *
	 * The preview runs synchronously inside a single REST request (the merchant
	 * clicks "Preview feed" and waits), unlike the real sync which streams the
	 * full catalog in background batches under a scheduled action. Walking an
	 * unbounded catalog one-shot would blow past `max_execution_time` and 504 on
	 * large stores, so the scan stops at this many products and flags the result
	 * as partial via `scan_limited`.
	 *
	 * @var int
	 * @since 10.9.0
	 */
	public const DEFAULT_SCAN_LIMIT = 2000;

	/**
	 * Integration whose query args, mapper, and validator drive the preview.
	 *
	 * @var WC_Stripe_Agentic_Commerce_Integration
	 */
	private WC_Stripe_Agentic_Commerce_Integration $integration;

	/**
	 * Constructor.
	 *
	 * @since 10.9.0
	 * @param WC_Stripe_Agentic_Commerce_Integration|null $integration Optional integration instance; one is created when omitted.
	 */
	public function __construct( ?WC_Stripe_Agentic_Commerce_Integration $integration = null ) {
		$this->integration = $integration ?? new WC_Stripe_Agentic_Commerce_Integration();
	}

	/**
	 * Walk the catalog and summarise what the next sync would include, exclude,
	 * and reject.
	 *
	 * The walk mirrors {@see \Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductWalker::from_integration()}
	 * — same base query args and the same `woocommerce_product_feed_args` filter —
	 * so the counts match the products a real sync would select. Each product is
	 * mapped and validated exactly as it would be during a sync; nothing is
	 * written to disk or uploaded.
	 *
	 * @since 10.9.0
	 * @param int $detail_limit Maximum number of invalid products to return with full detail.
	 * @param int $scan_limit   Maximum number of products to walk before stopping; the
	 *                          result is flagged `scan_limited` when the cap is hit.
	 * @return array{
	 *     total_count: int,
	 *     included_count: int,
	 *     excluded_count: int,
	 *     invalid_count: int,
	 *     validation_errors: array<int, array{product_id:int, product_name:string, edit_link:string, errors:string[]}>,
	 *     truncated: int,
	 *     scan_limited: bool,
	 *     advisories: array<int, array{product_id:int, product_name:string, edit_link:string, type:string, detail:string}>,
	 *     advisories_truncated: int,
	 *     shipping_warnings: string[]
	 * }
	 */
	public function generate( int $detail_limit = self::DEFAULT_DETAIL_LIMIT, int $scan_limit = self::DEFAULT_SCAN_LIMIT ): array {
		$detail_limit = max( 0, $detail_limit );
		$scan_limit   = max( 1, $scan_limit );

		$mapper    = $this->integration->get_product_mapper();
		$validator = $this->integration->get_feed_validator();

		$query_args = array_merge(
			[
				'status' => [ 'publish' ],
				'return' => 'objects',
			],
			$this->integration->get_product_feed_query_args()
		);

		/**
		 * Mirror the walker's filter so the preview selects the exact same set
		 * of products a real sync would. See ProductWalker::from_integration().
		 *
		 * @since 10.9.0
		 * @param array                                  $query_args  Arguments passed to wc_get_products().
		 * @param WC_Stripe_Agentic_Commerce_Integration $integration The integration the query belongs to.
		 */
		$query_args = apply_filters( 'woocommerce_product_feed_args', $query_args, $this->integration );

		$total_count          = 0;
		$included_count       = 0;
		$excluded_count       = 0;
		$invalid_count        = 0;
		$truncated            = 0;
		$scan_limited         = false;
		$validation_errors    = [];
		$advisories           = [];
		$advisories_truncated = 0;

		// Store-level shipping diagnostics: zones that contribute no flat-rate
		// shipping to the feed. Computed once, independent of the product walk.
		$shipping_warnings = [];
		if ( $mapper instanceof WC_Stripe_Agentic_Commerce_Product_Mapper ) {
			foreach ( $mapper->get_shipping_diagnostics()['zones_without_flat_rate'] as $zone_name ) {
				$shipping_warnings[] = sprintf(
					/* translators: %s: shipping zone name */
					__( 'Shipping zone "%s" has no flat-rate method, so the feed carries no shipping for it (live-rate / calculated methods price at checkout).', 'woocommerce-gateway-stripe' ),
					$zone_name
				);
			}
		}

		$page = 1;
		do {
			$result = wc_get_products(
				array_merge(
					$query_args,
					[
						'page'     => $page,
						'limit'    => self::PER_PAGE,
						'paginate' => true,
					]
				)
			);

			$products       = is_object( $result ) && isset( $result->products ) ? $result->products : [];
			$max_num_pages  = is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : $page;
			$batch_iterated = count( $products );

			foreach ( $products as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				// Stop once the scan cap is reached so a synchronous preview on a
				// large catalog can't run past the request time limit. break 2
				// exits the batch loop and the pagination loop together.
				if ( $total_count >= $scan_limit ) {
					$scan_limited = true;
					break 2;
				}

				++$total_count;

				try {
					$row = $mapper->map_product( $product );
				} catch ( Exception $e ) {
					// A product the mapper can't process (e.g. a variation whose
					// parent was deleted) would abort a real sync. Surface it as an
					// invalid product so the merchant can fix it, rather than
					// failing the whole preview.
					++$invalid_count;
					$this->collect_error( $validation_errors, $truncated, $detail_limit, $product, [ $e->getMessage() ] );
					continue;
				}

				$errors = $validator->validate_entry( $row, $product );

				// map_product() returns an empty row only for products the
				// visibility filter excluded — a merchant choice, not a defect.
				// Everything else with errors is a genuine validation failure.
				$excluded = empty( $row );

				if ( $excluded ) {
					++$excluded_count;
				} elseif ( empty( $errors ) ) {
					++$included_count;
				} else {
					++$invalid_count;
					$this->collect_error( $validation_errors, $truncated, $detail_limit, $product, $errors );
				}

				// Non-fatal advisories: why a product is excluded, whether it
				// redirects (and from which layer), and missing-SKU warnings.
				// Gated on the concrete mapper since these helpers are not part
				// of ProductMapperInterface.
				if ( $mapper instanceof WC_Stripe_Agentic_Commerce_Product_Mapper ) {
					$this->collect_advisories( $advisories, $advisories_truncated, $detail_limit, $product, $row, $mapper, $excluded );
				}
			}

			++$page;
		} while ( self::PER_PAGE === $batch_iterated && $page <= $max_num_pages );

		return [
			'total_count'          => $total_count,
			'included_count'       => $included_count,
			'excluded_count'       => $excluded_count,
			'invalid_count'        => $invalid_count,
			'validation_errors'    => $validation_errors,
			'truncated'            => $truncated,
			'scan_limited'         => $scan_limited,
			'advisories'           => $advisories,
			'advisories_truncated' => $advisories_truncated,
			'shipping_warnings'    => $shipping_warnings,
		];
	}

	/**
	 * Append a product's validation failure to the detail list, or bump the
	 * truncated count once the detail cap is reached.
	 *
	 * @since 10.9.0
	 * @param array       $validation_errors Detail list, modified in place.
	 * @param int         $truncated         Overflow counter, modified in place.
	 * @param int         $detail_limit      Maximum number of detailed entries to retain.
	 * @param \WC_Product $product           Product that failed.
	 * @param string[]    $errors            Validation messages for the product.
	 * @return void
	 */
	private function collect_error( array &$validation_errors, int &$truncated, int $detail_limit, \WC_Product $product, array $errors ): void {
		if ( count( $validation_errors ) >= $detail_limit ) {
			++$truncated;
			return;
		}

		// Variations are edited on their parent product's screen, so point the
		// "fix it" link at the parent when the failing row is a variation.
		$edit_id = ( \Automattic\WooCommerce\Enums\ProductType::VARIATION === $product->get_type() )
			? $product->get_parent_id()
			: $product->get_id();

		$edit_link = get_edit_post_link( $edit_id, 'raw' );

		$validation_errors[] = [
			'product_id'   => $product->get_id(),
			'product_name' => wp_strip_all_tags( $product->get_name() ),
			'edit_link'    => is_string( $edit_link ) ? $edit_link : '',
			'errors'       => array_values( $errors ),
		];
	}

	/**
	 * Collect the non-fatal advisories for a single product: why it is excluded,
	 * whether it redirects (and from which layer), and missing-SKU warnings.
	 *
	 * Excluded products are never in the feed, so their SKU / checkout state is
	 * moot — only the exclusion reason is recorded for them.
	 *
	 * @since 10.9.0
	 * @param array                                    $advisories Advisory list, modified in place.
	 * @param int                                      $truncated  Overflow counter, modified in place.
	 * @param int                                      $detail_limit Maximum advisories to retain.
	 * @param \WC_Product                              $product    Product being inspected.
	 * @param array                                    $row        Mapped feed row for the product.
	 * @param WC_Stripe_Agentic_Commerce_Product_Mapper $mapper    Concrete mapper for diagnostics.
	 * @param bool                                     $excluded   Whether the product was excluded from the feed.
	 * @return void
	 */
	private function collect_advisories( array &$advisories, int &$truncated, int $detail_limit, \WC_Product $product, array $row, WC_Stripe_Agentic_Commerce_Product_Mapper $mapper, bool $excluded ): void {
		if ( $excluded ) {
			$reason = WC_Stripe_Agentic_Commerce_Product_Mapper::get_sync_exclusion_reason( $product );
			if ( null !== $reason ) {
				$this->add_advisory( $advisories, $truncated, $detail_limit, $product, 'excluded', $reason );
			}
			return;
		}

		// Missing SKU: the product still syncs (id falls back to the product ID)
		// but loses the SKU-based identity ACP line-item resolution prefers.
		if ( '' === $product->get_sku() ) {
			$this->add_advisory( $advisories, $truncated, $detail_limit, $product, 'no_sku', '' );
		}

		// Redirect-only: surface which layer set disable_checkout. The mapped row
		// already carries the resolved boolean, so only re-resolve (re-applying
		// the filter) to read the source when the product actually redirects.
		if ( isset( $row['disable_checkout'] ) && 'true' === $row['disable_checkout'] ) {
			$source = $mapper->resolve_disable_checkout( $product )['source'];
			$this->add_advisory( $advisories, $truncated, $detail_limit, $product, 'disable_checkout', (string) $source );
		}
	}

	/**
	 * Append a single advisory to the list, or bump the overflow counter once the
	 * detail cap is reached.
	 *
	 * @since 10.9.0
	 * @param array       $advisories   Advisory list, modified in place.
	 * @param int         $truncated    Overflow counter, modified in place.
	 * @param int         $detail_limit Maximum advisories to retain.
	 * @param \WC_Product $product      Product the advisory is about.
	 * @param string      $type         Advisory type: 'excluded' | 'no_sku' | 'disable_checkout'.
	 * @param string      $detail       Machine-readable qualifier (exclusion reason or disable_checkout source).
	 * @return void
	 */
	private function add_advisory( array &$advisories, int &$truncated, int $detail_limit, \WC_Product $product, string $type, string $detail ): void {
		if ( count( $advisories ) >= $detail_limit ) {
			++$truncated;
			return;
		}

		// Variations are edited on their parent's screen.
		$edit_id = ( \Automattic\WooCommerce\Enums\ProductType::VARIATION === $product->get_type() )
			? $product->get_parent_id()
			: $product->get_id();

		$edit_link = get_edit_post_link( $edit_id, 'raw' );

		$advisories[] = [
			'product_id'   => $product->get_id(),
			'product_name' => wp_strip_all_tags( $product->get_name() ),
			'edit_link'    => is_string( $edit_link ) ? $edit_link : '',
			'type'         => $type,
			'detail'       => $detail,
		];
	}
}
