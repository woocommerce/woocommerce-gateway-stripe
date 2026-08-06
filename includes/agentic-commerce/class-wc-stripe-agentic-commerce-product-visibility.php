<?php
/**
 * Converges Stripe's catalog when a product crosses the sync-eligibility boundary.
 *
 * @internal Not part of the plugin's public API; may change without notice.
 * @package WooCommerce_Stripe
 * @since 10.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches product saves and schedules a full resync when a product's
 * `should_sync_product()` outcome flips.
 *
 * The filter only governs what is *sent*, so an already-exported product stays
 * live in agent surfaces until a full feed replacement. The inventory tracker
 * can't close that gap: it drops delta events for excluded products, and its
 * archive path bails on the same predicate, so a later trash never reaches
 * Stripe either.
 *
 * @internal
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Product_Visibility {

	/**
	 * Hidden post meta recording the last-exported eligibility ('yes' = excluded).
	 *
	 * Keyed on state rather than raw property changes so an ordinary price edit
	 * doesn't enqueue a full-catalog resync.
	 */
	protected const STATE_META_KEY = '_wc_stripe_agentic_commerce_sync_excluded';

	/**
	 * Register the save hooks.
	 *
	 * `post_updated` is covered alongside the CRUD hooks because a password
	 * written straight through `wp_update_post()` (Quick Edit, REST) never fires
	 * the product data store's own actions.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function init(): void {
		// `post_updated` fires for every product save on the site, so skip
		// registration outright rather than loading a product just to bail.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		add_action( 'woocommerce_update_product', [ $this, 'handle_product_save' ], 10, 2 );
		add_action( 'woocommerce_new_product', [ $this, 'handle_product_save' ], 10, 2 );
		add_action( 'post_updated', [ $this, 'handle_post_update' ], 10, 2 );
	}

	/**
	 * Re-evaluate eligibility after a product CRUD save.
	 *
	 * @since 10.9.0
	 * @param int             $product_id Saved product ID.
	 * @param WC_Product|null $product    Saved product, when the hook supplies it.
	 * @return void
	 */
	public function handle_product_save( $product_id, $product = null ): void {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( (int) $product_id );
		}

		$this->reconcile( $product );
	}

	/**
	 * Re-evaluate eligibility after a raw post update.
	 *
	 * @since 10.9.0
	 * @param int          $post_id    Updated post ID.
	 * @param WP_Post|null $post_after Post after the update.
	 * @return void
	 */
	public function handle_post_update( $post_id, $post_after = null ): void {
		if ( ! $post_after instanceof WP_Post || 'product' !== $post_after->post_type ) {
			return;
		}

		$this->reconcile( wc_get_product( (int) $post_id ) );
	}

	/**
	 * Compare current eligibility against the stored marker and schedule a full
	 * resync when it changed.
	 *
	 * Runs `should_sync_product()` rather than the predicates directly so a
	 * third-party filter flipping its verdict converges the same way.
	 *
	 * @since 10.9.0
	 * @param WC_Product|false|null $product Product to reconcile.
	 * @return bool True when a resync was scheduled.
	 */
	protected function reconcile( $product ): bool {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return false;
		}

		$is_excluded = ! WC_Stripe_Agentic_Commerce_Product_Mapper::should_sync_product( $product );
		$new_state   = $is_excluded ? 'yes' : 'no';
		$product_id  = $product->get_id();

		// Treat a missing marker as 'no': products predating this class were
		// exported under the older, more permissive defaults, so one that is
		// excluded now genuinely has to be pulled out of Stripe's catalog.
		$previous_state = get_post_meta( $product_id, self::STATE_META_KEY, true );
		if ( '' === $previous_state ) {
			$previous_state = 'no';
		}

		if ( $previous_state === $new_state ) {
			return false;
		}

		update_post_meta( $product_id, self::STATE_META_KEY, $new_state );

		// Fire the documented contract rather than calling the integration
		// directly, so this path behaves identically to an adapter's.
		do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );

		return true;
	}

	/**
	 * The eligibility-marker meta key.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public static function get_state_meta_key(): string {
		return self::STATE_META_KEY;
	}
}
