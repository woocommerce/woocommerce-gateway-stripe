<?php
/**
 * Class WC_Stripe_Agentic_Shipping_Package_Builder
 *
 * Builds WC shipping packages with resolved product contents for agentic checkout.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds WooCommerce shipping packages for agentic commerce flows.
 *
 * The agentic checkout flow has no WC_Cart, so packages handed to
 * WC_Shipping::calculate_shipping() must be assembled by hand. Content-dependent
 * shipping methods (table rate, weight-based, per-item) read the package's
 * `contents` entries — product data, quantities, and line totals — so the
 * entries mirror WC_Cart's cart item format.
 *
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Shipping_Package_Builder {

	/**
	 * Builds a WC shipping package array from contents entries and a destination.
	 *
	 * `contents_cost` and `cart_subtotal` are derived from the entries' line
	 * totals, matching how WC_Cart::get_shipping_packages() sums shippable items.
	 *
	 * @since 10.9.0
	 * @param array                 $contents Cart-item-format entries from the build_contents_* methods.
	 * @param WC_Stripe_API_Address $address  The destination address.
	 * @param int                   $user_id  The WordPress user ID, or 0 for guests.
	 * @return array The shipping package in WC_Shipping::calculate_shipping() format.
	 */
	public static function build_package( array $contents, WC_Stripe_API_Address $address, int $user_id ): array {
		$contents_cost = 0.0;
		foreach ( $contents as $entry ) {
			$contents_cost += (float) ( $entry['line_total'] ?? 0 );
		}

		return [
			'contents'        => $contents,
			'contents_cost'   => $contents_cost,
			'applied_coupons' => [],
			'user'            => [ 'ID' => $user_id ],
			'destination'     => [
				'country'  => $address->get_country() ?? '',
				'state'    => $address->get_state() ?? '',
				'postcode' => $address->get_postal_code() ?? '',
				'city'     => $address->get_city() ?? '',
				'address'  => '',
			],
			'cart_subtotal'   => $contents_cost,
		];
	}

	/**
	 * Builds package contents from a customize_checkout event's line items.
	 *
	 * Resolves each line item's sku_id to a WooCommerce product and keeps only
	 * shippable products. Line totals come from the event's unit_amount when
	 * present, falling back to the catalog price otherwise.
	 *
	 * @since 10.9.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event    The customization hook event.
	 * @param string                                     $currency The three-letter currency code.
	 * @return array Cart-item-format entries keyed by line item ID.
	 * @throws Exception When a line item's sku_id cannot be resolved to a product.
	 */
	public static function build_contents_from_event( WC_Stripe_Agentic_Customize_Checkout_Event $event, string $currency ): array {
		$contents = [];

		foreach ( $event->get_line_items() as $line_item ) {
			$product_id = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( $line_item->get_sku_id() );

			if ( ! $product_id ) {
				throw new Exception(
					sprintf(
						'Shipping package builder: product not found for line item %s with sku_id "%s".',
						$line_item->get_id(),
						$line_item->get_sku_id()
					)
				);
			}

			$product = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product( $product_id );

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$quantity    = $line_item->get_quantity();
			$unit_amount = $line_item->get_unit_amount();
			$line_total  = null !== $unit_amount
				? WC_Stripe_Helper::convert_from_stripe_amount( $unit_amount * $quantity, $currency )
				: (float) wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] );

			$contents[ $line_item->get_id() ] = self::build_contents_entry( $product, $quantity, $line_total, $line_total );
		}

		return $contents;
	}

	/**
	 * Builds package contents from a mapped order's product line items.
	 *
	 * Used by the order mapper, where line items (and their totals) have
	 * already been resolved and added to the order. Keeps only shippable
	 * products.
	 *
	 * @since 10.9.0
	 * @param WC_Order $order The order with mapped product line items.
	 * @return array Cart-item-format entries keyed by order item ID.
	 */
	public static function build_contents_from_order( WC_Order $order ): array {
		$contents = [];

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! $product instanceof WC_Product || ! $product->needs_shipping() ) {
				continue;
			}

			$contents[ $item_id ] = self::build_contents_entry(
				$product,
				$item->get_quantity(),
				(float) $item->get_total(),
				(float) $item->get_subtotal()
			);
		}

		return $contents;
	}

	/**
	 * Builds a single contents entry in WC_Cart's cart item format.
	 *
	 * Shipping methods read `data` (for weight/shipping class), `quantity`,
	 * `line_total`, and `line_subtotal`; tax fields are zeroed because tax is
	 * calculated separately in both agentic flows.
	 *
	 * @since 10.9.0
	 * @param WC_Product $product       The resolved product (simple or variation).
	 * @param int        $quantity      The line item quantity.
	 * @param float      $line_total    The line total after discounts, excluding tax.
	 * @param float      $line_subtotal The line subtotal before discounts, excluding tax.
	 * @return array The cart-item-format entry.
	 */
	private static function build_contents_entry( WC_Product $product, int $quantity, float $line_total, float $line_subtotal ): array {
		$is_variation = $product->is_type( 'variation' );

		return [
			'product_id'        => $is_variation ? $product->get_parent_id() : $product->get_id(),
			'variation_id'      => $is_variation ? $product->get_id() : 0,
			'variation'         => [],
			'quantity'          => $quantity,
			'data'              => $product,
			'data_hash'         => wc_get_cart_item_data_hash( $product ),
			'line_total'        => $line_total,
			'line_subtotal'     => $line_subtotal,
			'line_tax'          => 0,
			'line_subtotal_tax' => 0,
			'line_tax_data'     => [
				'total'    => [],
				'subtotal' => [],
			],
		];
	}
}
