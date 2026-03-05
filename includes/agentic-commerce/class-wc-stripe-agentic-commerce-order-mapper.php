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

			// Coming soon: Add logic for taxes and shipping based on the saved order.
			// $this->map_shipping();
			// $this->map_taxes();
			// $order->save(); // When we add shipping and taxes.

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

			$product = $this->resolve_product( $product_id, $line_item );

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
	 * Resolves a WooCommerce product from a line item's external_reference.
	 *
	 * @since 10.6.0
	 * @param int                          $product_id The parsed product ID.
	 * @param WC_Stripe_Agentic_Line_Item  $line_item  The line item (for error context).
	 * @return WC_Product The product.
	 * @throws Exception When no matching product exists.
	 */
	private function resolve_product( int $product_id, WC_Stripe_Agentic_Line_Item $line_item ): WC_Product {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->exists() ) {
			throw new Exception(
				sprintf(
					'Product not found for lookup_key "%d" (line item: %s).',
					$product_id,
					$line_item->get_description()
				)
			);
		}

		return $product;
	}

	/**
	 * Maps an address from a Stripe address object to the order.
	 *
	 * @since 10.6.0
	 * @param WC_Order $order   The WooCommerce order.
	 * @param object   $address The Stripe address object.
	 * @param string   $name    The name of the address to map.
	 * @param string   $phone   The phone number of the address to map.
	 * @param string   $type    The type of address to map ('billing' or 'shipping').
	 */
	private function map_address(
		WC_Order $order,
		object $address,
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

		$map = [
			'city'        => 'city',
			'country'     => 'country',
			'line1'       => 'address_1',
			'line2'       => 'address_2',
			'postal_code' => 'postcode',
			'state'       => 'state',
		];

		foreach ( $map as $received_key => $order_key ) {
			$method_name = sprintf( 'set_%s_%s', $type, $order_key );
			$order->$method_name( $address->$received_key ?? '' );
		}
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
		if ( ! $billing_address ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has no billing address.',
					$session->get_id()
				)
			);
		}

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
		$order->calculate_totals( false );

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
