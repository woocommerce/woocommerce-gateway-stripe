<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Order_Mapper
 *
 * Maps Stripe checkout session data to WooCommerce orders.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates WooCommerce orders from Stripe agentic checkout session data.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Order_Mapper {

	/**
	 * Returns the fields to expand while loading the checkout session.
	 *
	 * @since 10.5.0
	 * @return array The fields to expand.
	 */
	public function get_fields_to_expand(): array {
		return [
			'line_items.data.price.product',
		];
	}

	/**
	 * Creates a WooCommerce order from a Stripe checkout session.
	 *
	 * The session must be expanded with payment intent details, as
	 * well as the fields from `get_fields_to_expand()`.
	 *
	 * @since 10.5.0
	 * @param object $checkout_session The Stripe checkout session object.
	 * @return WC_Order The created order.
	 * @throws Exception When the order cannot be created.
	 */
	public function create_order_from_checkout_session( object $checkout_session ): WC_Order {
		$this->validate_checkout_session( $checkout_session );

		WC_Stripe_Logger::info(
			'Agentic order mapper: starting order creation.',
			[
				'session_id' => $checkout_session->id, // @phpstan-ignore property.notFound
				'currency'   => $checkout_session->currency, // @phpstan-ignore property.notFound
			]
		);

		$order = $this->create_order( $checkout_session );
		$this->map_customer( $order, $checkout_session );
		$this->map_line_items( $order, $checkout_session );
		$this->verify_line_item_totals( $order, $checkout_session );
		$this->map_addresses( $order, $checkout_session );
		$this->map_shipping( $order, $checkout_session );
		$this->store_stripe_metadata( $order, $checkout_session );
		$this->finalize_order( $order, $checkout_session );

		WC_Stripe_Logger::info(
			'Agentic order mapper: order created successfully.',
			[
				'session_id' => $checkout_session->id, // @phpstan-ignore property.notFound
				'order_id'   => $order->get_id(),
				'total'      => $order->get_total(),
			]
		);

		return $order;
	}

	/**
	 * Validates that the checkout session has all required fields.
	 *
	 * @since 10.5.0
	 * @param object $checkout_session The Stripe checkout session object.
	 * @throws Exception When required fields are missing or invalid.
	 */
	private function validate_checkout_session( object $checkout_session ): void {
		if ( empty( $checkout_session->id ) ) {
			throw new Exception( 'Checkout session is missing the id field.' );
		}

		if ( empty( $checkout_session->currency ) ) {
			throw new Exception(
				sprintf( 'Checkout session %s is missing the currency field.', $checkout_session->id )
			);
		}

		$this->validate_currency( $checkout_session );
	}

	/**
	 * Validates that the checkout session currency is supported by WooCommerce.
	 *
	 * @since 10.5.0
	 * @param object $checkout_session The Stripe checkout session object.
	 * @throws Exception When the currency is not supported.
	 */
	private function validate_currency( object $checkout_session ): void {
		$currency            = strtoupper( $checkout_session->currency ); // @phpstan-ignore property.notFound
		$supported_currencies = array_keys( get_woocommerce_currencies() );

		if ( ! in_array( $currency, $supported_currencies, true ) ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has unsupported currency: %s.',
					$checkout_session->id, // @phpstan-ignore property.notFound
					$currency
				)
			);
		}
	}

	/**
	 * Creates the WooCommerce order with basic settings.
	 *
	 * @since 10.5.0
	 * @param object $checkout_session The Stripe checkout session object.
	 * @return WC_Order The created order.
	 * @throws Exception When wc_create_order fails.
	 */
	private function create_order( object $checkout_session ): WC_Order {
		$order = wc_create_order( [ 'status' => 'pending' ] );

		if ( is_wp_error( $order ) ) {
			throw new Exception(
				sprintf(
					'Failed to create WooCommerce order for session %s: %s',
					$checkout_session->id, // @phpstan-ignore property.notFound
					$order->get_error_message()
				)
			);
		}

		$order->set_currency( strtoupper( $checkout_session->currency ) ); // @phpstan-ignore property.notFound

		return $order;
	}

	/**
	 * Validates the customer email and links existing WordPress users.
	 *
	 * If no matching user is found, the order is created as a guest order.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 * @throws Exception When the email is not present or invalid.
	 */
	private function map_customer( WC_Order $order, object $checkout_session ): void {
		// Start with the email.
		$email = $checkout_session->customer_details->email
			?? $checkout_session->customer_email
			?? '';

		if ( ! is_email( $email ) ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has no customer email.',
					$checkout_session->id, // @phpstan-ignore property.notFound
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
	 * Uses the price lookup_key to find matching WooCommerce products.
	 * Throws if a line item has a lookup_key that does not resolve to a valid product.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 * @throws Exception When a product cannot be found for a line item.
	 */
	private function map_line_items( WC_Order $order, object $checkout_session ): void {
		$currency = $checkout_session->currency; // @phpstan-ignore property.notFound

		foreach ( $checkout_session->line_items->data as $line_item ) { // @phpstan-ignore property.notFound
			$product_id = intval( $line_item->price->external_reference ?? '' );
			if ( 0 === $product_id ) {
				throw new Exception(
					sprintf(
						'Line item %s has no integer (product ID) lookup_key.',
						$line_item->id
					)
				);
			}

			$quantity        = (int) ( $line_item->quantity ?? 1 );
			$amount_total    = (int) ( $line_item->amount_total ?? 0 );
			$amount_tax      = (int) ( $line_item->amount_tax ?? 0 );
			$amount_subtotal = (int) ( $line_item->amount_subtotal ?? $amount_total );

			$line_total    = self::convert_from_stripe_amount( $amount_total - $amount_tax, $currency );
			$line_subtotal = self::convert_from_stripe_amount( $amount_subtotal - $amount_tax, $currency );
			$line_tax      = self::convert_from_stripe_amount( $amount_tax, $currency );

			$product = $this->resolve_product( $product_id, $line_item );

			// Let WooCommerce calculate totals from product price × quantity.
			$item_id = $order->add_product( $product, $quantity );

			if ( ! $item_id ) {
				throw new Exception(
					sprintf(
						'Failed to add product %d to order for session %s.',
						$product_id,
						$checkout_session->id // @phpstan-ignore property.notFound
					)
				);
			}

			$item = $order->get_item( $item_id );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				throw new Exception(
					sprintf(
						'Line item %s is not a product.',
						$line_item->id
					)
				);
			}

			// Verify WC-calculated total matches Stripe's pre-tax line total.
			$wc_line_total = (float) $item->get_total();

			if ( abs( $wc_line_total - $line_total ) > 0.01 ) {
				throw new Exception(
					sprintf(
						'Line item price mismatch for product %d: WC calculated %.2f, Stripe expected %.2f.',
						$product_id,
						$wc_line_total,
						$line_total
					)
				);
			}

			if ( $line_tax > 0 ) {
				$item->set_taxes(
					[
						'total'    => [ $line_tax ],
						'subtotal' => [ $line_tax ],
					]
				);
				$item->save();
			}
		}
	}

	/**
	 * Resolves a WooCommerce product from a line item's lookup_key.
	 *
	 * When a lookup_key is present, the product must exist — otherwise this
	 * method throws. When no lookup_key is provided (null), returns null to
	 * indicate the line item has no product association.
	 *
	 * @since 10.5.0
	 * @param int    $product_id The parsed product ID.
	 * @param object $line_item  The Stripe line item (for error context).
	 * @return WC_Product The product, or null when product_id is absent.
	 * @throws Exception When product_id is present but no matching product exists.
	 */
	private function resolve_product( int $product_id, object $line_item ): WC_Product {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->exists() ) {
			throw new Exception(
				sprintf(
					'Product not found for lookup_key "%d" (line item: %s).',
					$product_id,
					$line_item->description ?? 'unknown'
				)
			);
		}

		return $product;
	}

	/**
	 * Verifies that the sum of WooCommerce line item totals matches the
	 * Stripe checkout session subtotal. Throws if they diverge.
	 *
	 * This catches mapping errors before the order is finalized.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 * @throws Exception When the line item totals do not match.
	 */
	private function verify_line_item_totals( WC_Order $order, object $checkout_session ): void {
		$stripe_subtotal = self::convert_from_stripe_amount(
			(int) ( $checkout_session->amount_subtotal ?? $checkout_session->amount_total ), // @phpstan-ignore property.notFound
			$checkout_session->currency // @phpstan-ignore property.notFound
		);

		$wc_items_total = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$wc_items_total += (float) $item->get_total() + (float) $item->get_total_tax();
			}
		}

		if ( abs( $wc_items_total - $stripe_subtotal ) > 0.01 ) {
			throw new Exception(
				sprintf(
					'Line item total mismatch for session %s: WC items total %.2f, Stripe subtotal %.2f.',
					$checkout_session->id, // @phpstan-ignore property.notFound
					$wc_items_total,
					$stripe_subtotal
				)
			);
		}
	}

	/**
	 * Maps billing and shipping contact details from the checkout session.
	 *
	 * Sets name, phone, and address fields for both billing and shipping.
	 * Stripe provides a single full name field which is split into
	 * first name and last name for WooCommerce. The name may come from
	 * customer_details, shipping_details, or collected_information.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 */
	private function map_addresses( WC_Order $order, object $checkout_session ): void {
		// Billing address from customer_details.
		if ( isset( $checkout_session->customer_details ) ) {
			$details = $checkout_session->customer_details;

			// customer_details.name can be null — fall back to shipping_details.name.
			$billing_name = $details->name
				?? $checkout_session->shipping_details->name
				?? '';
			$name = self::split_full_name( $billing_name );

			$order->set_billing_first_name( $name['first'] );
			$order->set_billing_last_name( $name['last'] );

			if ( ! empty( $details->phone ) ) {
				$order->set_billing_phone( $details->phone );
			}

			if ( isset( $details->address ) ) {
				$addr = $details->address;
				$order->set_billing_address_1( $addr->line1 ?? '' );
				$order->set_billing_address_2( $addr->line2 ?? '' );
				$order->set_billing_city( $addr->city ?? '' );
				$order->set_billing_state( $addr->state ?? '' );
				$order->set_billing_postcode( $addr->postal_code ?? '' );
				$order->set_billing_country( $addr->country ?? '' );
			}
		}

		// Shipping address from shipping_details.
		if ( isset( $checkout_session->shipping_details ) ) {
			$shipping = $checkout_session->shipping_details;
			$name     = self::split_full_name( $shipping->name ?? '' );

			$order->set_shipping_first_name( $name['first'] );
			$order->set_shipping_last_name( $name['last'] );

			if ( isset( $shipping->address ) ) {
				$addr = $shipping->address;
				$order->set_shipping_address_1( $addr->line1 ?? '' );
				$order->set_shipping_address_2( $addr->line2 ?? '' );
				$order->set_shipping_city( $addr->city ?? '' );
				$order->set_shipping_state( $addr->state ?? '' );
				$order->set_shipping_postcode( $addr->postal_code ?? '' );
				$order->set_shipping_country( $addr->country ?? '' );
			}
		}
	}

	/**
	 * Adds a shipping line item to the order if shipping was charged.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 */
	private function map_shipping( WC_Order $order, object $checkout_session ): void {
		$shipping_amount = (int) ( $checkout_session->total_details->amount_shipping ?? 0 );

		if ( $shipping_amount <= 0 ) {
			return;
		}

		$currency = $checkout_session->currency; // @phpstan-ignore property.notFound
		$item     = new WC_Order_Item_Shipping();
		$item->set_method_title( __( 'Shipping', 'woocommerce-gateway-stripe' ) );
		$item->set_method_id( 'agentic_commerce' );
		$item->set_total( (string) self::convert_from_stripe_amount( $shipping_amount, $currency ) );
		$order->add_item( $item );
	}

	/**
	 * Stores Stripe-specific metadata on the order.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 */
	private function store_stripe_metadata( WC_Order $order, object $checkout_session ): void {
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Store payment intent ID (also adds an order note).
		$payment_intent_id = $this->get_payment_intent_id( $checkout_session );
		if ( $payment_intent_id ) {
			$order_helper->add_payment_intent_to_order( $payment_intent_id, $order );
		}

		// Store Stripe customer ID.
		$customer_id = $checkout_session->customer ?? null;
		if ( is_string( $customer_id ) && '' !== $customer_id ) {
			$order_helper->update_stripe_customer_id( $order, $customer_id );
		}

		// Store Stripe currency.
		$order_helper->update_stripe_currency( $order, strtolower( $checkout_session->currency ) ); // @phpstan-ignore property.notFound

		// Store checkout session ID for traceability.
		$order->update_meta_data( '_stripe_checkout_session_id', $checkout_session->id ); // @phpstan-ignore property.notFound
	}

	/**
	 * Finalizes the order: sets payment method, calculates totals, verifies
	 * the total matches Stripe, sets order status, and saves.
	 *
	 * @since 10.5.0
	 * @param WC_Order $order            The WooCommerce order.
	 * @param object   $checkout_session The Stripe checkout session object.
	 */
	private function finalize_order( WC_Order $order, object $checkout_session ): void {
		$order->set_payment_method( 'stripe' );
		$order->set_payment_method_title( __( 'Stripe', 'woocommerce-gateway-stripe' ) );

		// Calculate totals without recalculating taxes (we trust Stripe's amounts).
		$order->calculate_totals( false );

		// Verify total matches Stripe.
		$expected_total = self::convert_from_stripe_amount(
			(int) $checkout_session->amount_total, // @phpstan-ignore property.notFound
			$checkout_session->currency // @phpstan-ignore property.notFound
		);
		$order_total = (float) $order->get_total();

		if ( abs( $order_total - $expected_total ) > 0.01 ) {
			WC_Stripe_Logger::info(
				'Agentic order mapper: total mismatch, overriding with Stripe total.',
				[
					'order_total'    => $order_total,
					'expected_total' => $expected_total,
					'session_id'     => $checkout_session->id, // @phpstan-ignore property.notFound
				]
			);
			$order->set_total( (string) $expected_total );
		}

		$order->set_status( 'processing' );

		$order->add_order_note(
			__( 'Order created from Stripe agentic commerce checkout session.', 'woocommerce-gateway-stripe' )
		);

		$order->save();
	}

	/**
	 * Extracts the payment intent ID from a checkout session.
	 *
	 * The payment_intent field can be either a string ID or an expanded object.
	 *
	 * @since 10.5.0
	 * @param object $checkout_session The Stripe checkout session object.
	 * @return string|null The payment intent ID, or null if not available.
	 */
	private function get_payment_intent_id( object $checkout_session ): ?string {
		$pi = $checkout_session->payment_intent ?? null;

		if ( is_object( $pi ) && isset( $pi->id ) ) {
			return $pi->id;
		}

		if ( is_string( $pi ) && '' !== $pi ) {
			return $pi;
		}

		return null;
	}

	/**
	 * Converts a Stripe amount (in smallest currency unit) to a WooCommerce decimal amount.
	 *
	 * @since 10.5.0
	 * @param int    $amount   The amount in Stripe's smallest currency unit.
	 * @param string $currency The three-letter currency code.
	 * @return float The decimal amount for WooCommerce.
	 */
	private static function convert_from_stripe_amount( int $amount, string $currency ): float {
		$currency = strtolower( $currency );

		if ( in_array( $currency, WC_Stripe_Helper::no_decimal_currencies(), true ) ) {
			return (float) $amount;
		}

		if ( in_array( $currency, WC_Stripe_Helper::three_decimal_currencies(), true ) ) {
			return round( $amount / 1000, 3 );
		}

		return round( $amount / 100, 2 );
	}

	/**
	 * Splits a full name into first and last name components.
	 *
	 * @since 10.5.0
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
