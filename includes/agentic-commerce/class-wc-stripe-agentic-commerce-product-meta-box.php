<?php
/**
 * Per-product "Agentic Commerce" exclude meta box.
 *
 * Adds an Inventory-tab checkbox to opt a product out of the Stripe Agentic
 * Commerce sync.
 *
 * Admin UI only: the flag's read/write contract lives in
 * {@see WC_Stripe_Agentic_Commerce_Product_Exclusion}.
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
 * Per-product exclude toggle for Agentic Commerce sync (editor UI).
 *
 * @internal
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Product_Meta_Box {

	/**
	 * Product types the toggle renders for, and the only types `save_meta()`
	 * persists. The feed syncs `simple` and `variation`; a variation's parent is
	 * `variable`, which is where the checkbox lives.
	 */
	private const SUPPORTED_TYPES = [ ProductType::SIMPLE, ProductType::VARIABLE ];

	/**
	 * Register the editor UI hooks. Admin-only — the should-sync filter that
	 * enforces the flag during a feed run lives in the exclusion storage class.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Don't register the editor hooks at all when the merchant has Agentic
		// Commerce disabled — the toggle has nothing to act on in that state.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'render_checkbox' ], 30 );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_meta' ], 10, 1 );
	}

	/**
	 * Render the exclude checkbox. Gated on the merchant having Agentic Commerce
	 * enabled and the product being a supported type, so it never dangles.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function render_checkbox(): void {
		if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
			return;
		}

		// Defensive: init() only hooks this when the feature is on, but the
		// callback is public, so re-check before rendering.
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
				'id'          => WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(),
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
	 * @since 10.9.0
	 * @param int $product_id Product post ID.
	 * @return void
	 */
	public function save_meta( int $product_id ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		// Defensive: init() only hooks this when the feature is on, but the
		// callback is public, so re-check before an absent checkbox could clobber
		// a stored 'yes'.
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
		$meta_key     = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		$posted_value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';
		$excluded     = 'yes' === $posted_value;

		$changed = WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product_id, $excluded );

		// Request immediate sync with Stripe instead of waiting for the next scheduled sync.
		if ( $changed ) {
			( new WC_Stripe_Agentic_Commerce_Integration() )->schedule_full_resync_now();
		}
	}
}
