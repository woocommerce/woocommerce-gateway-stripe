<?php
/**
 * Per-product "Agentic Commerce" exclude meta box.
 *
 * Adds an Inventory-tab checkbox to opt a product out of the Stripe Agentic
 * Commerce sync. Hidden when WC AI Storefront is active, since that plugin owns
 * product selection through the same `wc_stripe_agentic_commerce_should_sync_product` filter.
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
	 * Hidden post meta key for the exclude flag ('yes' = excluded; 'no'/unset = synced).
	 */
	public const META_KEY = '_wc_stripe_agentic_commerce_exclude';

	/**
	 * Register hooks. The filter runs in every context (cron/CLI included) so
	 * sync honors the flag; the editor UI hooks are admin-only.
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
	 * Whether the product is excluded from Agentic Commerce sync. The parent's
	 * flag is authoritative for variations (the checkbox lives on the parent).
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
	 * Filter callback: vote false when the product is excluded, without
	 * resurrecting one another callback already excluded.
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
	 * Product types the feed walks, and the only types the toggle renders for.
	 * The feed syncs `simple` and `variation`; a variation's parent is `variable`.
	 */
	private const SUPPORTED_TYPES = [ 'simple', 'variable' ];

	/**
	 * Render the exclude checkbox. Gated on the merchant having Agentic Commerce
	 * enabled and the product being a supported type, so it never dangles.
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
	 * Persist the checkbox state on product save. Re-verifies the nonce since
	 * `woocommerce_process_product_meta` is public and callable directly.
	 *
	 * @param int $product_id Product post ID.
	 */
	public function save_meta( int $product_id ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		// Feature off → checkbox isn't rendered or posted; bail so its absence
		// doesn't clobber a stored 'yes'.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_verify_nonce handles both.
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

		// Mirror render_checkbox()'s type gate so a type switch (e.g. simple →
		// grouped) doesn't clobber a stored 'yes' or trigger a needless resync.
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || ! in_array( $product->get_type(), self::SUPPORTED_TYPES, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$posted_value = isset( $_POST[ self::META_KEY ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';
		$value        = 'yes' === $posted_value ? 'yes' : 'no';

		// Treat missing meta as 'no' so the first opt-in counts as a change.
		$previous_value = get_post_meta( $product_id, self::META_KEY, true );
		if ( '' === $previous_value ) {
			$previous_value = 'no';
		}

		update_post_meta( $product_id, self::META_KEY, $value );

		// Converge Stripe now instead of waiting for the next scheduled sync.
		if ( $previous_value !== $value ) {
			do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );
		}
	}
}
