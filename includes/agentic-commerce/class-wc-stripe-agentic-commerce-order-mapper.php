<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Order_Mapper
 *
 * Maps Stripe checkout session data to WooCommerce orders.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

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

			// Map shipping data and save again.
			$this->map_shipping( $order, $session );

			// Confirm everything is right.
			$this->verify_order_total( $order, $session );
		} catch ( Exception $e ) {
			$order->delete( true );
			throw $e;
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
	 * Uses the price external_reference to find matching WooCommerce products.
	 * Throws if a line item has an external_reference that does not resolve to a valid product.
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When a product cannot be found for a line item.
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
						'Line item %s has no integer (product ID) lookup_key.',
						$line_item->get_id()
					)
				);
			}

			$product = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product( $product_id );

			$quantity   = $line_item->get_quantity();
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
	 *
	 * Does nothing when no shipping rate was chosen (digital goods or not applicable).
	 *
	 * @since 10.6.0
	 * @param WC_Order                           $order   The WooCommerce order.
	 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
	 * @throws Exception When no matching WC rate can be found.
	 */
	private function map_shipping( WC_Order $order, WC_Stripe_Agentic_Checkout_Session $session ): void {
		$display_name = $session->get_chosen_shipping_rate_display_name();

		if ( null === $display_name ) {
			return;
		}

		$address = $session->get_shipping_address() ?? $session->get_billing_address();

		// Populate contents with resolved products for content-dependent
		// shipping methods (table rate, weight-based). See STRIPE-986.
		$package = [
			'contents'        => [],
			'contents_cost'   => 0,
			'applied_coupons' => [],
			'user'            => [ 'ID' => 0 ],
			'destination'     => [
				'country'  => $address->get_country() ?? '',
				'state'    => $address->get_state() ?? '',
				'postcode' => $address->get_postal_code() ?? '',
				'city'     => $address->get_city() ?? '',
				'address'  => '',
			],
			'cart_subtotal'   => 0,
		];

		$wc_shipping = WC()->shipping();

		if ( ! $wc_shipping instanceof WC_Shipping ) {
			throw new Exception(
				sprintf( 'WooCommerce shipping is unavailable for session %s.', $session->get_id() )
			);
		}

		$wc_shipping->calculate_shipping( [ $package ] );
		$packages = $wc_shipping->get_packages();
		$rates    = $packages[0]['rates'] ?? [];

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

		if ( null === $matched_rate ) {
			throw new Exception(
				sprintf(
					'Shipping rate "%s" not available for session %s.',
					$display_name,
					$session->get_id()
				)
			);
		}

		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( $matched_rate->get_label() );
		$shipping_item->set_method_id( $matched_rate->get_method_id() );
		$shipping_item->set_instance_id( $matched_rate->get_instance_id() );
		$shipping_item->set_total( $matched_rate->get_cost() );
		$order->add_item( $shipping_item );
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
