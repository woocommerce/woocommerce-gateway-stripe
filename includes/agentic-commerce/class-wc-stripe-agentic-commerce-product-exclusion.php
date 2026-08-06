<?php
/**
 * Per-product Agentic Commerce sync exclusion flag.
 *
 * Owns storage and retrieval of the per-product exclude flag, and ensures the exclusion flag
 * is applied through the `woocommerce_agentic_commerce_should_sync_product` filter.
 *
 * @internal Not part of the plugin's public API; may change without notice.
 * @package WooCommerce_Stripe
 * @since 10.9.0
 */

declare(strict_types=1);

use Automattic\WooCommerce\Enums\ProductType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the per-product exclude flag and filters Agentic Commerce sync eligibility.
 *
 * @internal
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Product_Exclusion {

	/**
	 * Hidden post meta key for the exclude flag ('yes' = excluded; 'no'/unset = synced).
	 */
	protected const META_KEY = '_wc_stripe_agentic_commerce_exclude';

	/**
	 * Product types the exclude flag applies to. The feed syncs `simple` and
	 * `variation`; a variation's parent is `variable`, which is where the flag
	 * lives (see {@see self::is_excluded()}).
	 */
	protected const SUPPORTED_TYPES = [ ProductType::SIMPLE, ProductType::VARIABLE ];

	/**
	 * The exclude-flag meta key.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public static function get_meta_key(): string {
		return self::META_KEY;
	}

	/**
	 * Product types the exclude flag applies to. Editor surfaces (meta box,
	 * list table) must share this list so a save through one surface can't
	 * clobber a flag another surface would have refused to touch.
	 *
	 * @since 10.9.0
	 * @return string[]
	 */
	public static function get_supported_types(): array {
		return self::SUPPORTED_TYPES;
	}

	/**
	 * Register the should-sync filter. Runs in every context (cron/CLI included)
	 * so sync honors the flag regardless of how it was triggered.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_agentic_commerce_should_sync_product', [ $this, 'filter_should_sync_product' ], 10, 2 );
	}

	/**
	 * Whether the product is excluded from Agentic Commerce sync. The parent's
	 * flag is authoritative for variations (the checkbox lives on the parent).
	 *
	 * Accepts an already-loaded product to avoid a redundant `wc_get_product()`
	 * when the caller (e.g. the feed's should-sync filter) holds the instance.
	 *
	 * @since 10.9.0
	 * @param WC_Product|int $product Product instance or post ID.
	 * @return bool
	 */
	public static function is_excluded( $product ): bool {
		if ( ! $product instanceof WC_Product ) {
			$product_id = (int) $product;
			if ( $product_id <= 0 ) {
				return false;
			}
			$product = wc_get_product( $product_id );
		}

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		$effective_id = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();

		return 'yes' === get_post_meta( $effective_id, self::META_KEY, true );
	}

	/**
	 * Persist the exclude flag for a product.
	 *
	 * @since 10.9.0
	 * @param int  $product_id Product post ID.
	 * @param bool $excluded   Whether the product should be excluded from sync.
	 * @return bool True when the stored value changed, false on a no-op (or invalid ID).
	 */
	public static function set_excluded( int $product_id, bool $excluded ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		$value = $excluded ? 'yes' : 'no';

		// Treat missing meta as 'no' so the first opt-in counts as a change.
		$previous_value = get_post_meta( $product_id, self::META_KEY, true );
		if ( '' === $previous_value ) {
			$previous_value = 'no';
		}

		update_post_meta( $product_id, self::META_KEY, $value );

		return $previous_value !== $value;
	}

	/**
	 * Filter callback: vote false when the product is excluded, without
	 * resurrecting one another callback already excluded.
	 *
	 * @since 10.9.0
	 * @param bool       $should_sync Whether to include the product.
	 * @param WC_Product $product     Product being evaluated.
	 * @return bool
	 */
	public function filter_should_sync_product( $should_sync, $product ): bool {
		// Use broad check for $should_sync to handle other filter outputs.
		$should_sync = wp_validate_boolean( $should_sync );
		if ( ! $should_sync ) {
			return false;
		}

		if ( ! $product instanceof WC_Product ) {
			return true;
		}

		// Pass the loaded instance through so is_excluded() doesn't re-fetch it.
		return ! self::is_excluded( $product );
	}
}
