<?php
/**
 * EXPLORATION SPIKE — NOT LOADED, NOT PRODUCTION CODE (STRIPE-968).
 *
 * FALLBACK approach only. The recommended path is Approach F — see
 * stripe-968-spike-claim-by-sync-hook.php. This file is kept for the case where F is rejected.
 *
 * Illustrates Approach A from STRIPE-968-site-identification.md: namespacing the catalog
 * `external_reference` with a stable per-site token so a delegated-checkout event can be
 * attributed to the site that produced the feed.
 *
 * This file lives under docs/explorations/ and is intentionally NOT autoloaded or registered
 * anywhere. It is a sketch for review/discussion only. Do not wire it into the feed builder,
 * product resolver, or webhook handler without a properly scoped implementation issue, tests,
 * and a back-compat plan for catalogs already keyed by bare SKU.
 *
 * @package WooCommerce_Stripe/Explorations
 */

// phpcs:ignoreFile -- exploration sketch, not held to production standards.

/**
 * Sketch of the helpers an implementation would need. Names/shapes are illustrative.
 */
class Spike_Stripe_968_Site_Token {

	/**
	 * Delimiter between the site token and the SKU. Must not appear in valid SKUs.
	 *
	 * ⚠️ AT RISK (see STRIPE-968-site-identification.md, Q3): Stripe documents the catalog `id`
	 * / `external_reference` as type "String (alphanumeric)". A non-alphanumeric delimiter like
	 * `~` (or `_` / `:`) may be rejected or stripped on the round-trip. If Stripe confirms only
	 * alphanumerics are allowed, switch to a FIXED-WIDTH alphanumeric token prefix (recover the
	 * SKU by offset, not by splitting on a separator). Confirm with Stripe before relying on this.
	 */
	const DELIMITER = '~';

	/** Option that would store a stable, URL-independent site token, set once at onboarding. */
	const TOKEN_OPTION = 'wc_stripe_agentic_site_token';

	/**
	 * Returns (creating once) a stable, opaque, URL-independent site token.
	 *
	 * Stored rather than derived from home_url so it survives domain migration AND so a cloned
	 * site (staging) gets its own token instead of inheriting prod's — see Open Question #5.
	 * A clone-detection step (compare stored home_url snapshot) would force regeneration.
	 */
	public static function site_token(): string {
		$token = get_option( self::TOKEN_OPTION, '' );
		if ( '' === $token ) {
			// wp_generate_password( 12, false ) in real code; literal here to avoid runtime deps.
			$token = substr( wp_hash( home_url( '/' ) ), 0, 12 );
			update_option( self::TOKEN_OPTION, $token, false );
		}
		return (string) $token;
	}

	/**
	 * Feed side: build the namespaced catalog id Stripe will echo back as external_reference.
	 * Would replace/augment WC_Stripe_Agentic_Commerce_Product_Mapper::get_id().
	 */
	public static function build_external_id( string $sku_or_product_id ): string {
		return self::site_token() . self::DELIMITER . $sku_or_product_id;
	}

	/**
	 * Webhook side: returns the SKU/product-id IFF the namespaced reference belongs to this
	 * site, or null when it belongs to another site (caller skips/logs — no order side effects).
	 *
	 * Back-compat: a bare reference (no delimiter) is treated as legacy and allowed through so
	 * in-flight catalogs keyed by bare SKU keep resolving during a transition window.
	 */
	public static function resolve_for_this_site( string $external_reference ): ?string {
		if ( false === strpos( $external_reference, self::DELIMITER ) ) {
			return $external_reference; // Legacy bare SKU — allow (transition only).
		}

		[ $token, $sku ] = explode( self::DELIMITER, $external_reference, 2 );

		if ( ! hash_equals( self::site_token(), $token ) ) {
			return null; // Belongs to another site on the same Stripe account → skip.
		}

		return $sku;
	}
}

/*
 * Illustrative flow (pseudocode, not executed):
 *
 *   // Feed build:
 *   $external_id = Spike_Stripe_968_Site_Token::build_external_id( $product->get_sku() );
 *
 *   // Webhook resolution (customize / finalize / order mapper):
 *   $sku = Spike_Stripe_968_Site_Token::resolve_for_this_site( $line_item_external_reference );
 *   if ( null === $sku ) {
 *       WC_Stripe_Logger::info( 'Agentic event ignored: external_reference belongs to another site on this account.' );
 *       return; // no order/data side effects
 *   }
 *   $product_id = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( $sku );
 */
