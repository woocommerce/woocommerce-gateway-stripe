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
 * Watches product saves and exclude-flag writes, and schedules a full resync
 * when a product's `should_sync_product()` outcome flips.
 *
 * Stripe processes imports in upsert mode, so an already-exported product only
 * leaves the catalog when a feed carries its `delete=true` row. The mapper
 * emits that row for every ineligible product still in the feed query; this
 * class exists so the sync carrying it runs promptly after the flip rather
 * than at the next scheduled interval.
 *
 * @internal
 * @since 10.9.0
 */
final class WC_Stripe_Agentic_Commerce_Product_Visibility {

	/**
	 * Last-exported eligibility ('yes' = excluded).
	 *
	 * Keyed on state, not raw property changes, so ordinary price edits don't
	 * enqueue a resync.
	 */
	private const STATE_META_KEY = '_wc_stripe_agentic_commerce_sync_excluded';

	/**
	 * Register the save hooks.
	 *
	 * `post_updated` is covered too: a password written through `wp_update_post()`
	 * (Quick Edit, REST) never fires the data store's own actions.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function init(): void {
		// `post_updated` fires for every product save, so skip registering at all
		// rather than loading a product just to bail.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		add_action( 'woocommerce_update_product', [ $this, 'handle_product_save' ] );
		add_action( 'woocommerce_new_product', [ $this, 'handle_product_save' ] );
		// Variations fire their own CRUD actions, never the product ones.
		add_action( 'woocommerce_update_product_variation', [ $this, 'handle_product_save' ] );
		add_action( 'woocommerce_new_product_variation', [ $this, 'handle_product_save' ] );
		add_action( 'post_updated', [ $this, 'handle_post_update' ], 10, 2 );

		// The save hooks all fire BEFORE the exclusion surfaces write the
		// per-product exclude flag (the meta box persists on
		// `woocommerce_process_product_meta`, after `$product->save()`; bulk edit
		// writes the meta with no product save at all). Watching the meta write
		// itself is the only ordering-proof way to keep the marker current — a
		// stale marker makes the next ordinary edit fire the redundant full
		// resync this class exists to prevent.
		add_action( 'added_post_meta', [ $this, 'handle_exclude_meta_change' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'handle_exclude_meta_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ $this, 'handle_exclude_meta_change' ], 10, 3 );
	}

	/**
	 * Re-evaluate eligibility after a product or variation CRUD save.
	 *
	 * Always re-reads by ID instead of using the object the hook supplies: on
	 * the variation creation hook that object can lack the parent-data snapshot
	 * `WC_Product_Variation::get_catalog_visibility()` reads, which only a
	 * data-store read hydrates.
	 *
	 * @since 10.9.0
	 * @param int $product_id Saved product or variation ID.
	 * @return void
	 */
	public function handle_product_save( $product_id ): void {
		$this->maybe_schedule_resync( wc_get_product( (int) $product_id ) );
	}

	/**
	 * Re-evaluate eligibility when the per-product exclude flag itself changes.
	 *
	 * No recursion risk: maybe_schedule_resync() writes only the state marker,
	 * whose key fails the guard here.
	 *
	 * @since 10.9.0
	 * @param int|int[] $meta_ids  Meta row ID(s); unused, present for the hook signature.
	 * @param int       $object_id Post the meta belongs to.
	 * @param string    $meta_key  Meta key that changed.
	 * @return void
	 */
	public function handle_exclude_meta_change( $meta_ids, $object_id, $meta_key ): void {
		if ( WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() !== $meta_key ) {
			return;
		}

		$this->maybe_schedule_resync( wc_get_product( (int) $object_id ) );
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
		if ( ! $post_after instanceof WP_Post || ! in_array( $post_after->post_type, [ 'product', 'product_variation' ], true ) ) {
			return;
		}

		$this->maybe_schedule_resync( wc_get_product( (int) $post_id ) );
	}

	/**
	 * Schedule a resync when eligibility differs from the stored marker.
	 *
	 * Runs `should_sync_product()` rather than the predicates directly so a
	 * third-party filter's verdict converges too.
	 *
	 * @since 10.9.0
	 * @param WC_Product|false|null $product Product to re-evaluate.
	 * @return void
	 */
	private function maybe_schedule_resync( $product ): void {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		$is_excluded = ! WC_Stripe_Agentic_Commerce_Product_Mapper::should_sync_product( $product );
		$new_state   = $is_excluded ? 'yes' : 'no';
		$product_id  = $product->get_id();

		// Missing marker means 'no': products predating this class were exported
		// under the older defaults, so one excluded now has to be pulled out.
		$previous_state = get_post_meta( $product_id, self::STATE_META_KEY, true );
		if ( '' === $previous_state ) {
			$previous_state = 'no';
		}

		if ( $previous_state === $new_state ) {
			return;
		}

		update_post_meta( $product_id, self::STATE_META_KEY, $new_state );

		// Variations inherit password protection and catalog visibility from
		// their parent, so a variable parent crossing the boundary flips their
		// eligibility too. Bring their markers current now — the resync
		// scheduled below already covers them, and a stale marker would make
		// the next unrelated variation save fire a redundant full resync.
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! $child instanceof WC_Product ) {
					continue;
				}

				$child_excluded = ! WC_Stripe_Agentic_Commerce_Product_Mapper::should_sync_product( $child );
				update_post_meta( $child_id, self::STATE_META_KEY, $child_excluded ? 'yes' : 'no' );
			}
		}

		// Fire the documented contract rather than calling the integration, so
		// this path behaves identically to an adapter's.
		do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );
	}
}
