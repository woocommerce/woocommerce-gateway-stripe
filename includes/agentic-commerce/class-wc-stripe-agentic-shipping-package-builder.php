<?php
/**
 * Class WC_Stripe_Agentic_Shipping_Package_Builder
 *
 * Builds WC shipping packages with resolved product contents for agentic checkout.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   x.x.x
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
 * @since x.x.x
 */
class WC_Stripe_Agentic_Shipping_Package_Builder {

	/**
	 * Builds a WC shipping package array from contents entries and a destination.
	 *
	 * `contents_cost` and `cart_subtotal` are derived from the entries' line
	 * totals, matching how WC_Cart::get_shipping_packages() sums shippable items.
	 *
	 * @since x.x.x
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
	 * Applies the agentic package-split filter to a built package.
	 *
	 * The agentic flow has no cart, so `woocommerce_cart_shipping_packages`,
	 * the filter extensions use to split a cart into multiple shipments, never
	 * fires. This filter is its equivalent here: callbacks receive the single
	 * built package and may split it into several before rates are calculated.
	 * Non-array entries are dropped because callbacks are untrusted.
	 *
	 * Both agentic call sites (rate quoting and order mapping) must run this
	 * filter, so a rate quoted from split packages can be matched again when
	 * the order is created.
	 *
	 * @since x.x.x
	 * @param array $package The package built by build_package().
	 * @return array The packages to pass to WC_Shipping::calculate_shipping().
	 */
	public static function get_filtered_packages( array $package ): array {
		/**
		 * Filters the shipping packages used for agentic checkout rate calculation.
		 *
		 * Callbacks may split the single built package into several. Rates
		 * offered to the agent are those available for every package, with
		 * costs summed across packages.
		 *
		 * @since x.x.x
		 * @param array $packages Array containing the single built package.
		 */
		$packages = apply_filters( 'wc_stripe_agentic_shipping_packages', [ $package ] );

		return array_values( array_filter( (array) $packages, 'is_array' ) );
	}

	/**
	 * Combines calculated per-package rates into one flat, rate-ID-keyed list.
	 *
	 * The agentic response format supports a single shipping choice, while WC
	 * prices split packages independently. A rate is offered only when every
	 * package can ship with it (same rate ID), and its cost is the sum across
	 * packages; rates missing from any package are dropped, as are entries
	 * that are not WC_Shipping_Rate objects (packages pass through filters).
	 * When packages were split, combined rates are clones, so the objects held
	 * by WC_Shipping stay untouched.
	 *
	 * @since x.x.x
	 * @param array $packages The packages returned by WC_Shipping::get_packages() after calculation.
	 * @return array<string, WC_Shipping_Rate> Rates keyed by rate ID.
	 */
	public static function combine_package_rates( array $packages ): array {
		$packages = array_values( $packages );
		$is_split = count( $packages ) > 1;

		$combined    = [];
		$first_rates = $packages[0]['rates'] ?? [];
		foreach ( is_array( $first_rates ) ? $first_rates : [] as $rate_id => $rate ) {
			if ( $rate instanceof WC_Shipping_Rate ) {
				$combined[ (string) $rate_id ] = $is_split ? clone $rate : $rate;
			}
		}

		foreach ( array_slice( $packages, 1 ) as $package ) {
			$package_rates = is_array( $package['rates'] ?? null ) ? $package['rates'] : [];

			foreach ( $combined as $rate_id => $rate ) {
				$package_rate = $package_rates[ $rate_id ] ?? null;

				if ( ! $package_rate instanceof WC_Shipping_Rate ) {
					unset( $combined[ $rate_id ] );
					continue;
				}

				$rate->set_cost( (string) ( (float) $rate->get_cost() + (float) $package_rate->get_cost() ) );
			}
		}

		return $combined;
	}

	/**
	 * Builds package contents from a customize_checkout event's line items.
	 *
	 * Resolves each line item's sku_id to a WooCommerce product and keeps only
	 * shippable products. Line totals come from the event's unit_amount when
	 * present, falling back to the catalog price otherwise.
	 *
	 * @since x.x.x
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event                    The customization hook event.
	 * @param string                                     $currency                 The three-letter currency code.
	 * @param array<string,int>                          $product_ids_by_line_item Product IDs already resolved for the event's line items, keyed by line item ID; a supplied ID is trusted so the SKU lookup is not repeated.
	 * @return array Cart-item-format entries keyed by line item ID.
	 * @throws Exception When a line item's sku_id cannot be resolved to a product, its quantity or unit_amount is out of bounds, or the product has no catalog price to fall back on.
	 */
	public static function build_contents_from_event( WC_Stripe_Agentic_Customize_Checkout_Event $event, string $currency, array $product_ids_by_line_item = [] ): array {
		$contents = [];

		foreach ( $event->get_line_items() as $line_item ) {
			$product_id = (int) ( $product_ids_by_line_item[ $line_item->get_id() ]
				?? WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( $line_item->get_sku_id() ) );

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

			// The event getters only cast to int, so a malformed payload can
			// carry a zero/negative quantity or a negative unit_amount; either
			// would corrupt line totals, so fail the request instead.
			if ( $quantity < 1 || ( null !== $unit_amount && $unit_amount < 0 ) ) {
				throw new Exception(
					sprintf(
						'Shipping package builder: invalid quantity or unit_amount for line item %s (quantity %d, unit_amount %s).',
						$line_item->get_id(),
						$quantity,
						null === $unit_amount ? 'null' : (string) $unit_amount
					)
				);
			}

			if ( null !== $unit_amount ) {
				$line_total = WC_Stripe_Helper::convert_from_stripe_amount( $unit_amount * $quantity, $currency );
			} else {
				// A product with no catalog price must fail the request rather
				// than silently quote content-cost rates against a 0.00 package.
				// Newer WC casts the missing price to 0.0 inside
				// wc_get_price_excluding_tax() — indistinguishable from a
				// legitimately free product — so check the raw price first;
				// older WC returns '' from the helper, which the is_numeric()
				// guard catches.
				$price = '' !== $product->get_price()
					? wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] )
					: '';

				if ( ! is_numeric( $price ) ) {
					throw new Exception(
						sprintf(
							'Shipping package builder: no catalog price for line item %s (product ID %d).',
							$line_item->get_id(),
							$product->get_id()
						)
					);
				}

				$line_total = (float) $price;
			}

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
	 * @since x.x.x
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
	 * @since x.x.x
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
