<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Price_Availability_Responder
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Enums\ProductStockStatus;

/**
 * Builds the response for the product price and availability hook.
 *
 * Reports the same data the catalog feed publishes — regular price,
 * separate sale price, and stock status — but in real time for a single
 * SKU, so agents don't act on stale feed data. Products the feed would
 * not include (unresolvable, unpublished, or excluded from sync) are
 * reported as deleted so agents stop offering them.
 *
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Price_Availability_Responder {

	/**
	 * Builds the hook response for the requested SKU.
	 *
	 * @since 10.9.0
	 * @param WC_Stripe_Agentic_Price_Availability_Event $event The price/availability request event.
	 * @return array Response payload for Stripe.
	 */
	public function respond( WC_Stripe_Agentic_Price_Availability_Event $event ): array {
		$response = [
			'sku_id'      => $event->get_sku_id(),
			'merchant_id' => $event->get_merchant_id(),
		];

		$product = $this->find_catalog_product( $event->get_sku_id() );

		if ( null === $product ) {
			$response['deleted'] = true;
			return $response;
		}

		$response['availability'] = $this->get_availability( $product );

		$currency = get_woocommerce_currency();

		// The feed publishes the regular price as `price` and the sale price
		// separately; mirror that split so the real-time answer matches the
		// catalog. Delegated checkout applies no WooCommerce cart discounts,
		// so catalog prices are the authoritative selling prices here.
		// Price getters run through product filters, so the value is untrusted:
		// a filter returning false/non-numeric would otherwise be cast to 0.00
		// and quote the product as free. Omit the price instead.
		$regular_price = $product->get_regular_price();
		if ( is_numeric( $regular_price ) && 0 <= (float) $regular_price ) {
			$response['price'] = [
				'unit_amount' => WC_Stripe_Helper::get_stripe_amount( (float) $regular_price, $currency ),
				'currency'    => strtolower( $currency ),
			];
		}

		$sale_price = $this->get_sale_price( $product, $currency );
		if ( null !== $sale_price ) {
			$response['sale_price'] = $sale_price;
		}

		$response['as_of'] = time();

		return $response;
	}

	/**
	 * Resolves the SKU to a product the catalog feed would include.
	 *
	 * @since 10.9.0
	 * @param string $sku_id The SKU identifier from the request (product SKU or numeric product ID).
	 * @return WC_Product|null The product, or null when it is absent from the catalog.
	 */
	private function find_catalog_product( string $sku_id ): ?WC_Product {
		$product_id = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( $sku_id );

		if ( 0 === $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product || ! $product->exists() ) {
			return null;
		}

		// The feed only exports published products (a disabled variation is
		// `private`, so this covers it too), and honors per-product sync
		// exclusions. Anything outside that set is not in Stripe's catalog,
		// so report it as deleted rather than quoting a price for it.
		if ( 'publish' !== $product->get_status() ) {
			return null;
		}

		if ( ! WC_Stripe_Agentic_Commerce_Product_Mapper::should_sync_product( $product ) ) {
			return null;
		}

		return $product;
	}

	/**
	 * Maps the product's stock state to the hook's availability object.
	 *
	 * Uses the same stock-status mapping as the catalog feed. WooCommerce has
	 * no preorder concept, so that status is never reported.
	 *
	 * @since 10.9.0
	 * @param WC_Product $product The resolved product.
	 * @return array Availability payload with `status` and optional `quantity`.
	 */
	private function get_availability( WC_Product $product ): array {
		switch ( $product->get_stock_status() ) {
			case ProductStockStatus::IN_STOCK:
				$status = 'in_stock';
				break;
			case ProductStockStatus::ON_BACKORDER:
				$status = 'backorder';
				break;
			case ProductStockStatus::OUT_OF_STOCK:
			default:
				$status = 'out_of_stock';
				break;
		}

		$availability = [ 'status' => $status ];

		// The quantity getter is filterable, so guard against non-numeric
		// values rather than casting them to 0 (which would read as sold out).
		$quantity = $product->get_stock_quantity();
		if ( $product->managing_stock() && is_numeric( $quantity ) ) {
			$availability['quantity'] = (int) $quantity;
		}

		return $availability;
	}

	/**
	 * Builds the sale price payload, when the product has one.
	 *
	 * @since 10.9.0
	 * @param WC_Product $product  The resolved product.
	 * @param string     $currency Store currency code.
	 * @return array|null Sale price payload, or null when the product has no sale price.
	 */
	private function get_sale_price( WC_Product $product, string $currency ): ?array {
		// Same untrusted-filter guard as the regular price: treat a
		// false/non-numeric filtered value as "no sale" instead of 0.00.
		$sale_price = $product->get_sale_price();

		if ( ! is_numeric( $sale_price ) || 0 > (float) $sale_price ) {
			return null;
		}

		// Stripe requires a start and end date with every sale price. Mirror
		// the feed's defaults for open-ended WooCommerce sales: start now,
		// and end 30 days after the later of the start date and today.
		$sale_from = $product->get_date_on_sale_from();
		$sale_to   = $product->get_date_on_sale_to();

		if ( ! $sale_from ) {
			$sale_from = new WC_DateTime();
		}

		if ( ! $sale_to ) {
			$sale_to = clone max( $sale_from, new WC_DateTime() );
			$sale_to->modify( '+30 days' );
		}

		return [
			'unit_amount' => WC_Stripe_Helper::get_stripe_amount( (float) $sale_price, $currency ),
			'currency'    => strtolower( $currency ),
			'start_date'  => $sale_from->date_i18n( 'Y-m-d' ),
			'end_date'    => $sale_to->date_i18n( 'Y-m-d' ),
		];
	}
}
