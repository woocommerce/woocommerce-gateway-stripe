<?php
/**
 * Per-product "Agentic Commerce" exclude meta box.
 *
 * Adds a checkbox to the WC product editor's Inventory tab that lets
 * merchants opt individual products out of the Stripe Agentic Commerce
 * catalog feed, inventory updates, and archive events. The checkbox only
 * appears when the merchant has Agentic Commerce enabled in settings and
 * the product is a type the feed walks (simple or variable).
 *
 * Hidden when WC AI Storefront is active: that plugin owns the
 * merchant-facing product selection UI (mode-driven catalog scoping)
 * and exposes it through the same `wc_stripe_agentic_commerce_should_sync_product`
 * filter. Showing both surfaces at once would let merchants set
 * conflicting expectations.
 *
 * @package WooCommerce_Stripe
 * @since 10.8.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-product exclude toggle for Agentic Commerce sync.
 *
 * @since 10.8.0
 */
class WC_Stripe_Agentic_Commerce_Product_Meta_Box {

	/**
	 * Hidden post meta key holding the per-product exclude flag.
	 *
	 * Underscore prefix keeps WC's Custom Fields panel from listing it
	 * alongside merchant-authored meta. Value semantics:
	 *   'yes' → product is excluded from any Agentic Commerce sync.
	 *   'no' / unset → product participates in sync as before.
	 */
	public const META_KEY = '_wc_stripe_agentic_commerce_exclude';

	/**
	 * Register hooks.
	 *
	 * Filter callback registers in every context (admin, frontend, cron,
	 * CLI) so the scheduled sync and inventory tracker honor the flag.
	 * The editor UI hooks register only in wp-admin since they have no
	 * frontend purpose.
	 */
	public function init(): void {
		add_filter( 'wc_stripe_agentic_commerce_should_sync_product', [ $this, 'filter_should_sync_product' ], 10, 2 );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'render_checkbox' ], 30 );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_meta' ], 10, 1 );
	}

	/**
	 * Whether the given product is excluded from Agentic Commerce sync.
	 *
	 * For variations the parent's flag is authoritative — the editor UI
	 * only surfaces the checkbox on the parent product, so reading the
	 * variation's own meta would always return "not excluded" and let
	 * variants leak past the parent's setting.
	 *
	 * @since 10.8.0
	 * @param int $product_id Product post ID.
	 * @return bool
	 */
	public static function is_excluded( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		$effective_id = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();

		return 'yes' === get_post_meta( $effective_id, self::META_KEY, true );
	}

	/**
	 * Filter callback: vote false when the merchant has excluded the product.
	 *
	 * Other callbacks may have already voted false; respect that and don't
	 * resurrect an excluded product.
	 *
	 * @since 10.8.0
	 * @param bool        $should_sync Whether to include the product.
	 * @param \WC_Product $product     Product being evaluated.
	 * @return bool
	 */
	public function filter_should_sync_product( bool $should_sync, \WC_Product $product ): bool {
		if ( ! $should_sync ) {
			return false;
		}

		return ! self::is_excluded( $product->get_id() );
	}

	/**
	 * Product types the feed walks. The toggle is meaningless on anything
	 * else, so it only renders for these.
	 *
	 * `get_product_feed_query_args()` syncs `simple` and `variation`; the
	 * parent of a `variation` is `variable`, and the checkbox lives on the
	 * parent — so the editor-facing set is `simple` + `variable`.
	 */
	private const SUPPORTED_TYPES = [ 'simple', 'variable' ];

	/**
	 * Render the "Agentic Commerce" exclude checkbox.
	 *
	 * Uses WC's `woocommerce_wp_checkbox()` helper so the visual treatment
	 * matches the surrounding Inventory checkboxes (Manage stock, Sold
	 * individually, etc.). The description renders inline next to the
	 * checkbox (no tooltip) so the opt-out behavior is visible at a glance.
	 *
	 * Two gates keep the toggle from dangling with nothing behind it: the
	 * merchant must have switched Agentic Commerce on in settings, and the
	 * product must be a type the feed actually walks.
	 */
	public function render_checkbox(): void {
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			return;
		}

		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		global $product_object;
		$type = $product_object instanceof WC_Product ? $product_object->get_type() : '';
		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return;
		}

		woocommerce_wp_checkbox(
			[
				'id'          => self::META_KEY,
				'label'       => __( 'Agentic Commerce', 'woocommerce-gateway-stripe' ),
				'description' => __( 'Exclude from the Stripe Agentic Commerce catalog sync', 'woocommerce-gateway-stripe' ),
				'desc_tip'    => false,
			]
		);
	}

	/**
	 * Persist the checkbox state on product save.
	 *
	 * WC core verifies `woocommerce_meta_nonce` in its own save handler
	 * before firing this action, but the hook is public and any plugin
	 * or CLI call can fire it directly — so we re-verify here.
	 *
	 * @param int $product_id Product post ID.
	 */
	public function save_meta( int $product_id ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		// The toggle is hidden while the merchant has the feature off, so the
		// unchecked checkbox isn't posted — bail before that absence clobbers a
		// stored 'yes' that should hold until they turn it back on.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_verify_nonce handles both.
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

		// Mirror render_checkbox()'s type gate. The toggle only renders for types
		// the feed walks, so a save for any other type (e.g. after a simple →
		// grouped switch) won't post the checkbox — bail before that absence
		// clobbers a stored 'yes' or schedules a needless resync.
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || ! in_array( $product->get_type(), self::SUPPORTED_TYPES, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$posted_value = isset( $_POST[ self::META_KEY ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';
		$value        = 'yes' === $posted_value ? 'yes' : 'no';

		$previous_value = get_post_meta( $product_id, self::META_KEY, true );
		if ( '' === $previous_value ) {
			// First-ever save defaults to "not excluded" — treat missing meta as 'no'
			// so flipping the box on for the first time still counts as a change and
			// schedules a resync.
			$previous_value = 'no';
		}

		update_post_meta( $product_id, self::META_KEY, $value );

		// On a real change, kick the catalog so Stripe converges immediately rather
		// than waiting for the next scheduled full sync to drop / re-add this product.
		if ( $previous_value !== $value ) {
			do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );
		}
	}
}
