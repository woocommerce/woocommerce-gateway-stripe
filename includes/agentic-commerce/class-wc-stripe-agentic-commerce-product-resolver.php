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
}
