<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Product_Resolver.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves WooCommerce products from Stripe external IDs.
 *
 * This is a simple utility class with a single static method. It is tested
 * indirectly through the order mapper and tax calculator tests.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Commerce_Product_Resolver {
	/**
	 * Resolves a WooCommerce product from an external reference.
	 *
	 * @since 10.6.0
	 * @param int $product_id The parsed product ID.
	 * @return WC_Product The product.
	 * @throws Exception When no matching product exists.
	 */
	public static function resolve_product( int $product_id ): WC_Product {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->exists() ) {
			throw new Exception(
				sprintf(
					'Product not found for lookup_key "%d".',
					$product_id
				)
			);
		}

		return $product;
	}

	/**
	 * Resolves the WooCommerce product ID for a Stripe-supplied external
	 * reference (`price.external_reference` on the checkout session, or
	 * `sku_id` on customize_checkout line items — both halves of the
	 * Agentic Commerce sync contract).
	 *
	 * Tries the merchant SKU first (the current sync contract) and falls
	 * back to a numeric product-ID lookup so catalogs synced under the
	 * legacy "external_reference = product_id" contract — and SKU-less
	 * products that fall through to the product-ID path on sync — keep
	 * resolving instead of failing the checkout. Returns 0 when neither
	 * path matches a real product.
	 *
	 * @since 10.7.0
	 * @param string $external_reference The reference Stripe sent back.
	 * @return int Resolved product ID, or 0 if no match.
	 */
	public static function resolve_product_id_by_external_reference( string $external_reference ): int {
		if ( '' === $external_reference ) {
			return 0;
		}

		$product_id = wc_get_product_id_by_sku( $external_reference );
		if ( $product_id ) {
			return (int) $product_id;
		}

		if ( ctype_digit( $external_reference ) ) {
			$candidate = wc_get_product( (int) $external_reference );
			if ( $candidate instanceof WC_Product ) {
				return (int) $candidate->get_id();
			}
		}

		return 0;
	}
}
