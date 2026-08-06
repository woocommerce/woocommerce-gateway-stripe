<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Order_Mapper
 *
 * Maps Stripe checkout session data to WooCommerce orders.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates WooCommerce orders from Stripe agentic checkout session data.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Commerce_Order_Mapper {

	private const ADDRESS_TYPE_BILLING  = 'billing';
	private const ADDRESS_TYPE_SHIPPING = 'shipping';

	/**
	 * `created_via` value stamped on orders produced by the agentic checkout flow.
	 *
	 * WooCommerce 10.8+ blocks `payment_complete()` on orders that lack
	 * checkout evidence. The integration registers this value with the
	 * `woocommerce_payment_complete_allowed_created_via_values` filter so
	 * agentic orders can complete payment.
	 */
	public const CREATED_VIA = 'stripe-agentic-commerce';

	/**
	 * Minutes to hold reserved stock while payment completes. Passed explicitly
	 * so a blank `woocommerce_hold_stock_minutes` cannot disable the guard;
	 * payment_complete()'s stock reduction releases the hold.
	 *
	 * @var int
	 */
	private const STOCK_HOLD_MINUTES = 10;

	/**
	 * Creates a WooCommerce order from a Stripe checkout session.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @return WC_Order The created order.
	 * @throws Exception When the order cannot be created.
	 */
	public function create_order_from_checkout_session( WC_Stripe_Agentic_Checkout_Session $session ): WC_Order {
		$this->validate_checkout_session( $session );

		WC_Stripe_Logger::info(
			'Agentic order mapper: starting order creation.',
			[
				'session_id' => $session->get_id(),
				'currency'   => $session->get_currency(),
			]
		);

		$order = $this->create_order( $session );

		try {
			// Map basic data first.
			$this->map_customer( $order, $session );
			$this->map_line_items( $order, $session );
			$this->map_addresses( $order, $session );
			$this->store_stripe_metadata( $order, $session );

			// Save everything we've got so far.
			$order->save();

			// Must run after map_line_items(): map_shipping() builds the shipping
			// package from the order's resolved line items, so the items have to be
			// on the order before shipping is calculated.
			$this->map_shipping( $order, $session );

			// Confirm everything is right.
			$this->verify_order_total( $order, $session );
		} catch ( Throwable $e ) {
			$order->delete( true );
			throw $e;
		}

		// The only point that serializes concurrent agentic sessions on stock —
		// finalize_checkout validates without reserving, so N sessions can all
		// pass for the same last units. ReserveStock either claims them or throws.
		// Throwable, not just ReserveStockException: third-party callbacks can
		// throw anything from inside the reservation, and payment is already
		// captured — every failure must land on the parked-order path rather
		// than escape with neither payment_complete() nor the fallback run.
		try {
			$this->reserve_stock( $order );
		} catch ( \Throwable $e ) {
			$this->hold_order_for_insufficient_stock( $order, $session, $e );
			return $order;
		}

		// Complete payment outside the delete-on-failure block, since
		// payment_complete() fires hooks/emails that cannot be rolled back.
		$order->payment_complete( $session->get_payment_intent_id() ?? '' );

		WC_Stripe_Logger::info(
			'Agentic order mapper: order created successfully.',
			[
				'session_id' => $session->get_id(),
				'order_id'   => $order->get_id(),
				'total'      => $order->get_total(),
			]
		);

		return $order;
	}

	/**
	 * Validates that the checkout session has all required fields.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When required fields are missing or invalid.
	 */
	private function validate_checkout_session( WC_Stripe_Agentic_Checkout_Session $session ): void {
		if ( null === $session->get_id() ) {
			throw new Exception( 'Checkout session is missing the id field.' );
		}

		if ( null === $session->get_payment_intent_id() ) {
			throw new Exception(
				sprintf( 'Checkout session %s is missing the payment_intent id.', $session->get_id() )
			);
		}

		if ( null === $session->get_currency() ) {
			throw new Exception(
				sprintf( 'Checkout session %s is missing the currency field.', $session->get_id() )
			);
		}

		$currency             = $session->get_currency();
		$supported_currencies = array_keys( get_woocommerce_currencies() );
		if ( ! in_array( $currency, $supported_currencies, true ) ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has unsupported currency: %s.',
					$session->get_id(),
					$currency
				)
			);
		}

		// WooCommerce coupons don't participate in delegated checkout, so a
		// Stripe-side discount can't be represented on the order — WC
		// recalculates full catalog prices, and the total verification would
		// reject the order anyway after it was built. Fail fast with an
		// explicit reason instead of an opaque total mismatch.
		if ( $session->get_amount_discount() > 0 ) {
			throw new Exception(
				sprintf(
					'Checkout session %s includes a discount (%d): discounts are not supported for agentic checkout orders.',
					$session->get_id(),
					$session->get_amount_discount()
				)
			);
		}
	}

	/**
	 * Creates the WooCommerce order with basic settings.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @return WC_Order The created order.
	 * @throws Exception When wc_create_order fails.
	 */
	private function create_order( WC_Stripe_Agentic_Checkout_Session $session ): WC_Order {
		$order = wc_create_order( [ 'status' => 'pending' ] );

		if ( is_wp_error( $order ) ) {
			throw new Exception(
				sprintf(
					'Failed to create WooCommerce order for session %s: %s',
					$session->get_id(),
					$order->get_error_message()
				)
			);
		}

		if ( ! $order instanceof WC_Order ) {
			throw new Exception(
				sprintf(
					'wc_create_order() returned an unexpected type for session %s.',
					$session->get_id()
				)
			);
		}

		$order->set_currency( $session->get_currency() ?? '' );
		$order->set_payment_method( 'stripe' );
		$order->set_payment_method_title( __( 'Stripe (Agentic Checkout)', 'woocommerce-gateway-stripe' ) );
		$order->set_created_via( self::CREATED_VIA );
		$order->add_order_note(
			__( 'Order created from Stripe agentic commerce checkout session.', 'woocommerce-gateway-stripe' )
		);

		return $order;
	}

	/**
	 * Validates the customer email and links existing WordPress users.
	 *
	 * If no matching user is found, the order is created as a guest order.
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When the email is not present or invalid.
	 */
	private function map_customer( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$email = $session->get_customer_email() ?? '';

		if ( ! is_email( $email ) ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has no customer email.',
					$session->get_id(),
				)
			);
		}

		$order->set_billing_email( $email );
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$order->set_customer_id( $user->ID );
		}
	}

	/**
	 * Maps line items from the checkout session to order products.
	 *
	 * Resolves the price's external_reference to a WooCommerce product (SKU
	 * first, falling back to product ID for catalogs synced under the legacy
	 * contract). Throws if neither lookup matches a product.
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When a product cannot be found for a line item, or a line item has a non-positive quantity.
	 */
	private function map_line_items( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$currency   = $session->get_currency() ?? '';
		$line_items = $session->get_line_items();

		if ( empty( $line_items ) ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has no line items.',
					$session->get_id()
				)
			);
		}

		foreach ( $line_items as $line_item ) {
			$product_id = $line_item->get_product_id();
			if ( 0 === $product_id ) {
				throw new Exception(
					sprintf(
						'Line item %s has no external_reference that resolves to a WooCommerce product (SKU or legacy product-ID).',
						$line_item->get_id()
					)
				);
			}

			$product = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product( $product_id );

			$quantity = $line_item->get_quantity();

			// The getter only casts to int, and the line-total reconciliation
			// below can't catch a payload that is internally consistent with a
			// non-positive quantity (e.g. quantity 0 with amount_total 0).
			if ( $quantity < 1 ) {
				throw new Exception(
					sprintf(
						'Line item %s has an invalid quantity (%d).',
						$line_item->get_id(),
						$quantity
					)
				);
			}

			$line_total = WC_Stripe_Helper::convert_from_stripe_amount(
				$line_item->get_amount_total() - $line_item->get_amount_tax(),
				$currency
			);

			// Let WooCommerce calculate totals from product price × quantity.
			$item = $this->add_product_to_order( $order, $product, $quantity, $session->get_id() ?? '' );

			// Verify WC-calculated total matches Stripe's pre-tax line total.
			$wc_line_total = (float) $item->get_total();
			if ( abs( $wc_line_total - $line_total ) > 0.001 ) {
				throw new Exception(
					sprintf(
						'Line item price mismatch for product %d: WC calculated %s, Stripe expected %s.',
						$product_id,
						wc_format_decimal( $wc_line_total ),
						wc_format_decimal( $line_total )
					)
				);
			}
		}
	}

	/**
	 * Adds a product to the order and returns the item.
	 *
	 * @since 10.6.0
	 * @param WC_Order   $order    The WooCommerce order.
	 * @param WC_Product $product  The product to add.
	 * @param int        $quantity The quantity of the product to add.
	 * @param string     $session_id The ID of the checkout session.
	 * @return WC_Order_Item_Product The added item.
	 * @throws Exception When the product cannot be added to the order.
	 */
	private function add_product_to_order( WC_Order $order, WC_Product $product, int $quantity, string $session_id ): WC_Order_Item_Product {
		$item_id = $order->add_product( $product, $quantity );
		if ( ! $item_id ) {
			throw new Exception(
				sprintf(
					'Failed to add product %d to order for session %s.',
					$product->get_id(),
					$session_id
				)
			);
		}

		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			throw new Exception(
				sprintf(
					'Line item %s is not a product.',
					$item_id
				)
			);
		}

		return $item;
	}

	/**
	 * Places an atomic stock hold for the order's managed-stock items;
	 * ReserveStock itself skips backorder-enabled products.
	 *
	 * @since 10.9.0
	 * @param WC_Order $order The order with mapped line items.
	 * @throws \Automattic\WooCommerce\Checkout\Helpers\ReserveStockException When stock cannot be secured.
	 */
	private function reserve_stock( WC_Order $order ): void {
		$reserve_stock = new \Automattic\WooCommerce\Checkout\Helpers\ReserveStock();
		$reserve_stock->reserve_stock_for_order( $order, self::STOCK_HOLD_MINUTES );
	}

	/**
	 * Parks a paid order that lost the stock race instead of overselling.
	 *
	 * Payment is already captured, so the order can't be declined or deleted:
	 * keep it on-hold with the transaction id and suppress the on-hold stock
	 * reduction that would drive the oversold product negative.
	 *
	 * @since 10.9.0
	 * @param WC_Order                           $order   The created order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @param Throwable                          $e       The reservation failure.
	 */
	private function hold_order_for_insufficient_stock( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session, Throwable $e ): void {
		$order->set_transaction_id( $session->get_payment_intent_id() ?? '' );

		// Not woocommerce_can_reduce_order_stock: that still marks the order
		// stock-reduced, so a later cancel/refund would restock units never taken.
		$order_id        = $order->get_id();
		$block_reduction = static function ( $trigger_reduce, $target_order_id ) use ( $order_id ) {
			return (int) $target_order_id === $order_id ? false : $trigger_reduce;
		};
		add_filter( 'woocommerce_payment_complete_reduce_order_stock', $block_reduction, 10, 2 );

		try {
			$order->update_status(
				OrderStatus::ON_HOLD,
				sprintf(
					/* translators: %s: reason stock could not be secured */
					__( 'Stripe captured the payment, but stock could not be secured for every item. Review stock, then process or refund this order manually. Reason: %s', 'woocommerce-gateway-stripe' ),
					$e->getMessage()
				)
			);
		} finally {
			remove_filter( 'woocommerce_payment_complete_reduce_order_stock', $block_reduction );
		}

		WC_Stripe_Logger::error(
			'Agentic order mapper: stock could not be secured at completion; order placed on hold.',
			[
				'session_id' => $session->get_id(),
				'order_id'   => $order_id,
				'reason'     => $e->getMessage(),
			]
		);
	}

	/**
	 * Maps an address from a Stripe address object to the order.
	 *
	 * @since 10.6.0
	 * @param WC_Order              $order   The WooCommerce order.
	 * @param WC_Stripe_API_Address $address The Stripe address object.
	 * @param string                $name    The name of the address to map.
	 * @param string                $phone   The phone number of the address to map.
	 * @param string                $type    The type of address to map ('billing' or 'shipping').
	 */
	private function map_address(
		WC_Order $order,
		WC_Stripe_API_Address $address,
		string $name,
		string $phone,
		string $type = self::ADDRESS_TYPE_BILLING
	): void {
		$name = self::split_full_name( $name );

		$set_first_name = "set_{$type}_first_name";
		$order->$set_first_name( $name['first'] );

		$set_last_name = "set_{$type}_last_name";
		$order->$set_last_name( $name['last'] );

		$set_phone = "set_{$type}_phone";
		$order->$set_phone( $phone );

		$set_city     = "set_{$type}_city";
		$set_country  = "set_{$type}_country";
		$set_address1 = "set_{$type}_address_1";
		$set_address2 = "set_{$type}_address_2";
		$set_postcode = "set_{$type}_postcode";
		$set_state    = "set_{$type}_state";

		$order->$set_city( $address->get_city() ?? '' );
		$order->$set_country( $address->get_country() ?? '' );
		$order->$set_address1( $address->get_line1() ?? '' );
		$order->$set_address2( $address->get_line2() ?? '' );
		$order->$set_postcode( $address->get_postal_code() ?? '' );
		$order->$set_state( $address->get_state() ?? '' );
	}

	/**
	 * Maps billing and shipping contact details from the checkout session.
	 *
	 * Sets name, phone, and address fields for both billing and shipping.
	 * Stripe provides a single full name field which is split into
	 * first name and last name for WooCommerce.
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 */
	private function map_addresses( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$billing_address = $session->get_billing_address();

		$this->map_address(
			$order,
			$billing_address,
			$session->get_customer_name() ?? '',
			$session->get_billing_phone() ?? '',
			self::ADDRESS_TYPE_BILLING
		);

		// Shipping name, phone, and address (optional — not collected for digital goods).
		$shipping_address = $session->get_shipping_address();
		if ( ! $session->get_shipping_details() || ! $shipping_address ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$product = $item->get_product();
				if ( $product instanceof WC_Product && $product->needs_shipping() ) {
					$order->add_order_note(
						__( 'Order contains shippable items but no shipping address was provided in the checkout session.', 'woocommerce-gateway-stripe' )
					);
					break;
				}
			}
			return;
		}

		$this->map_address(
			$order,
			$shipping_address,
			$session->get_shipping_name() ?? '',
			$session->get_shipping_phone() ?? '',
			self::ADDRESS_TYPE_SHIPPING
		);
	}

	/**
	 * Stores Stripe-specific metadata on the order.
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 */
	private function store_stripe_metadata( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Store payment intent ID (also adds an order note).
		$order_helper->add_payment_intent_to_order( $session->get_payment_intent_id() ?? '', $order );

		// Store Stripe customer ID.
		$customer_id = $session->get_customer_id();
		if ( null !== $customer_id ) {
			$order_helper->update_stripe_customer_id( $order, $customer_id );
		}

		// Store Stripe currency.
		$order_helper->update_stripe_currency( $order, $session->get_currency_lowercase() ?? '' );

		// Store checkout session ID for traceability.
		$order->update_meta_data( '_stripe_checkout_session_id', $session->get_id() ?? '' );
	}

	/**
	 * Maps the chosen shipping rate from the checkout session to the order.
	 *
	 * Re-runs WooCommerce shipping calculation for the order's destination and
	 * resolves the chosen rate using the following priority:
	 *   1. By WC rate ID from the Stripe shipping rate metadata (wc_rate_id).
	 *   2. If exactly one rate is available, accept it unconditionally.
	 *   3. By display name match as a last resort.
	 *   4. If no WC rate matches (or WC shipping calculation fails), fall back
	 *      to a free-form WC_Order_Item_Shipping built from
	 *      shipping_rate.display_name and total_details.amount_shipping.
	 *
	 * Does nothing when no shipping rate was chosen (digital goods or not applicable).
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When WooCommerce shipping is unavailable (WC()->shipping() is not a WC_Shipping).
	 */
	private function map_shipping( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$display_name = $session->get_chosen_shipping_rate_display_name();

		if ( null === $display_name ) {
			return;
		}

		$address = $session->get_shipping_address() ?? $session->get_billing_address();

		$package = WC_Stripe_Agentic_Shipping_Package_Builder::build_package(
			WC_Stripe_Agentic_Shipping_Package_Builder::build_contents_from_order( $order ),
			$address,
			$order->get_customer_id()
		);

		$wc_shipping = WC()->shipping();

		if ( ! $wc_shipping instanceof WC_Shipping ) {
			throw new Exception(
				sprintf( 'WooCommerce shipping is unavailable for session %s.', $session->get_id() )
			);
		}

		// Catch Throwable: the outer handler only catches Exception, so a broken
		// shipping method or null-session Error here would bypass the fallback.
		$rates = [];
		try {
			// Action Scheduler / WP Cron has no HTTP request to bootstrap
			// WC()->session, which calculate_shipping_for_package reads from.
			if ( null === WC()->session ) {
				WC()->initialize_session();
			}

			$wc_shipping->calculate_shipping( [ $package ] );
			$packages = $wc_shipping->get_packages();
			$rates    = $packages[0]['rates'] ?? [];
		} catch ( Throwable $e ) {
			WC_Stripe_Logger::warning(
				'Agentic order mapper: WC shipping calculation failed; will use free-form shipping line.',
				[
					'session_id' => $session->get_id(),
					'error'      => $e->getMessage(),
					'exception'  => get_class( $e ),
					'file'       => $e->getFile(),
					'line'       => $e->getLine(),
				]
			);
		}

		// 1. Match by WC rate ID stored in Stripe shipping rate metadata.
		$wc_rate_id   = $session->get_chosen_shipping_rate_wc_id();
		$matched_rate = null;

		if ( null !== $wc_rate_id && isset( $rates[ $wc_rate_id ] ) ) {
			$matched_rate = $rates[ $wc_rate_id ];
		}

		// 2. If exactly one rate is available, accept it unconditionally.
		if ( null === $matched_rate && 1 === count( $rates ) ) {
			$matched_rate = reset( $rates );
		}

		// 3. Fall back to matching by display name.
		if ( null === $matched_rate ) {
			foreach ( $rates as $rate ) {
				if ( $rate->get_label() === $display_name ) {
					$matched_rate = $rate;
					break;
				}
			}
		}

		if ( null !== $matched_rate ) {
			$shipping_item = new WC_Order_Item_Shipping();
			$shipping_item->set_method_title( $matched_rate->get_label() );
			$shipping_item->set_method_id( $matched_rate->get_method_id() );
			$shipping_item->set_instance_id( $matched_rate->get_instance_id() );
			$shipping_item->set_total( $matched_rate->get_cost() );
			$order->add_item( $shipping_item );
			return;
		}

		// No WC rate matched. This can happen when Stripe/the agent supplies a
		// shipping rate that does not include matching wc_rate_id metadata and the display name
		// does not match any configured WC shipping method.
		// When this occurs, we create the order and use `stripe_agentic` as the shipping method.
		$stripe_amount = $session->get_shipping_amount();
		$currency      = $session->get_currency() ?? '';
		$total         = null !== $stripe_amount
			? WC_Stripe_Helper::convert_from_stripe_amount( $stripe_amount, $currency )
			: 0;

		WC_Stripe_Logger::error(
			'Agentic order mapper: chosen shipping rate did not match any WC rate; using Stripe rate as free-form shipping line.',
			[
				'session_id'          => $session->get_id(),
				'stripe_display_name' => $display_name,
				'stripe_wc_rate_hint' => $session->get_chosen_shipping_rate_wc_id(),
				'stripe_amount'       => $total,
				'available_wc_rates'  => array_map(
					static function ( $rate ) {
						return [
							'id'    => $rate->get_id(),
							'label' => $rate->get_label(),
							'cost'  => $rate->get_cost(),
						];
					},
					$rates
				),
			]
		);

		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( $display_name );
		$shipping_item->set_method_id( 'stripe_agentic' );
		$shipping_item->set_total( (string) $total );
		$order->add_item( $shipping_item );

		$order->add_order_note(
			sprintf(
				/* translators: 1: shipping rate label from Stripe, 2: formatted shipping amount */
				__( 'Stripe Agentic Commerce: chosen shipping rate "%1$s" (%2$s) did not match any configured WooCommerce shipping method. Recorded as a free-form shipping line.', 'woocommerce-gateway-stripe' ),
				$display_name,
				wc_price( $total, [ 'currency' => $currency ] )
			)
		);
	}

	/**
	 * Verifies that the WC order total matches the Stripe session total.
	 *
	 * Called after all components (line items, shipping, tax) are mapped
	 * so the comparison covers the full order amount.
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When the totals diverge beyond rounding tolerance.
	 */
	private function verify_order_total( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$order->calculate_totals();

		$expected_total = WC_Stripe_Helper::convert_from_stripe_amount(
			$session->get_amount_total() ?? 0,
			$session->get_currency() ?? ''
		);
		$order_total    = (float) $order->get_total();

		if ( abs( $order_total - $expected_total ) > 0.001 ) {
			throw new Exception(
				sprintf(
					'Order total mismatch for session %s: WC total %s, Stripe total %s.',
					$session->get_id(),
					wc_format_decimal( $order_total ),
					wc_format_decimal( $expected_total )
				)
			);
		}
	}

	/**
	 * Splits a full name into first and last name components.
	 *
	 * @since 10.6.0
	 * @param string $full_name The full name to split.
	 * @return array{first: string, last: string} The split name.
	 */
	private static function split_full_name( string $full_name ): array {
		$parts = explode( ' ', trim( $full_name ), 2 );

		return [
			'first' => $parts[0] ?? '',
			'last'  => $parts[1] ?? '',
		];
	}
}
