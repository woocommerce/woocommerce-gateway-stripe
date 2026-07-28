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
	 * Resolves a WC product ID from a Stripe external reference.
	 *
	 * Tries SKU first, then a numeric product-ID fallback so catalogs synced
	 * under the legacy contract (or SKU-less products) still resolve.
	 * Returns 0 on miss.
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
