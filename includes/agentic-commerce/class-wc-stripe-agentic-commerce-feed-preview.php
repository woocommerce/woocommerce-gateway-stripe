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
	 * @return array{
	 *     total_count: int,
	 *     included_count: int,
	 *     excluded_count: int,
	 *     invalid_count: int,
	 *     validation_errors: array<int, array{product_id:int, product_name:string, edit_link:string, errors:string[]}>,
	 *     truncated: int
	 * }
	 */
	public function generate( int $detail_limit = self::DEFAULT_DETAIL_LIMIT ): array {
		$detail_limit = max( 0, $detail_limit );

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

		$total_count       = 0;
		$included_count    = 0;
		$excluded_count    = 0;
		$invalid_count     = 0;
		$truncated         = 0;
		$validation_errors = [];

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

				if ( empty( $errors ) ) {
					++$included_count;
					continue;
				}

				// map_product() returns an empty row only for products the
				// visibility filter excluded — a merchant choice, not a defect.
				// Everything else with errors is a genuine validation failure.
				if ( empty( $row ) ) {
					++$excluded_count;
					continue;
				}

				++$invalid_count;
				$this->collect_error( $validation_errors, $truncated, $detail_limit, $product, $errors );
			}

			++$page;
		} while ( self::PER_PAGE === $batch_iterated && $page <= $max_num_pages );

		return [
			'total_count'       => $total_count,
			'included_count'    => $included_count,
			'excluded_count'    => $excluded_count,
			'invalid_count'     => $invalid_count,
			'validation_errors' => $validation_errors,
			'truncated'         => $truncated,
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
}
