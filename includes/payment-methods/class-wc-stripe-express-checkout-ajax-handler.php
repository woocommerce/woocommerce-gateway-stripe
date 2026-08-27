<?php

use Automattic\WooCommerce\Enums\ProductType;
use Automattic\WooCommerce\Enums\ProductStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Express_Checkout_Ajax_Handler class.
 */
class WC_Stripe_Express_Checkout_Ajax_Handler {
	/**
	 * WC_Stripe_Express_Checkout_Helper instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Express_Checkout_Helper $express_checkout_helper Express checkout helper.
	 */
	public function __construct( WC_Stripe_Express_Checkout_Helper $express_checkout_helper ) {
		$this->express_checkout_helper = $express_checkout_helper;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wc_ajax_wc_stripe_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		add_action( 'wc_ajax_wc_stripe_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		add_action( 'wc_ajax_wc_stripe_normalize_address', [ $this, 'ajax_normalize_address' ] );
		add_action( 'wc_ajax_wc_stripe_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wc_ajax_wc_stripe_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wc_ajax_wc_stripe_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
		add_action( 'wc_ajax_wc_stripe_clear_cart', [ $this, 'ajax_clear_cart' ] );
		add_action( 'wc_ajax_wc_stripe_log_errors', [ $this, 'ajax_log_errors' ] );
		add_filter( 'woocommerce_get_country_locale', [ $this, 'modify_country_locale_for_express_checkout' ], 20 );
		add_filter( 'rest_pre_dispatch', [ $this, 'tokenized_cart_store_api_address_normalization' ], 10, 3 );
	}

	/**
	 * Normalizes redacted Google Pay / Apple Pay address data on express checkout
	 * Store API requests so WooCommerce's validation doesn't reject it.
	 *
	 * Wallets send a long-form state and (on shipping-address change) a truncated
	 * postcode. We normalize the state, and on the `update-customer` route also
	 * normalize the postcode and relax its validation so shipping zones resolve.
	 * `select-shipping-rate` needs neither: the location is already set by then.
	 *
	 * @param mixed                                  $response Returned unchanged.
	 * @param \WP_REST_Server                        $server   Server instance.
	 * @param \WP_REST_Request<array<string, mixed>> $request  The current request.
	 *
	 * @return mixed
	 */
	public function tokenized_cart_store_api_address_normalization( $response, $server, $request ) {
		// Only act on verified express checkout Store API requests (header + nonce).
		if ( ! $this->express_checkout_helper->is_express_checkout_context() ) {
			return $response;
		}

		$is_update_customer_route = '/wc/store/v1/cart/update-customer' === $request->get_route();
		if ( $is_update_customer_route ) {
			add_filter( 'woocommerce_validate_postcode', [ $this, 'maybe_skip_postcode_validation' ], 10, 3 );
		}

		// Gather addresses so states (incl. the Apple Pay Hong Kong edge case) normalize together.
		$data = [];
		foreach ( [ 'billing_address', 'shipping_address' ] as $key ) {
			if ( isset( $request[ $key ] ) && is_array( $request[ $key ] ) ) {
				$data[ $key ] = $request[ $key ];
			}
		}

		if ( empty( $data ) ) {
			return $response;
		}

		$data = $this->express_checkout_helper->normalize_state( $data );

		foreach ( $data as $key => $address ) {
			// Pad redacted UK/CA postcodes on update-customer so shipping zones still match.
			if ( $is_update_customer_route && ! empty( $address['country'] ) && ! empty( $address['postcode'] ) ) {
				$address['postcode'] = $this->express_checkout_helper->get_normalized_postal_code(
					$address['postcode'],
					$address['country']
				);
			}

			$request->set_param( $key, $address );
		}

		return $response;
	}

	/**
	 * Lets redacted UK/CA wallet postcodes bypass postcode validation on the Store API.
	 *
	 * They're padded with `0` (not `*`) because WC_Validation::is_postcode() rejects
	 * non-alphanumerics before this filter runs.
	 *
	 * @param bool   $valid    Whether the postcode is valid.
	 * @param string $postcode The postcode in question.
	 * @param string $country  The country for the postcode.
	 *
	 * @return bool
	 */
	public function maybe_skip_postcode_validation( $valid, $postcode, $country ) {
		if ( ! in_array( $country, [ WC_Stripe_Country_Code::UNITED_KINGDOM, WC_Stripe_Country_Code::CANADA ], true ) ) {
			return $valid;
		}

		// Padded redacted postcodes end in `0`. Loose, but real UK/CA postcodes ending in `0` are valid anyway.
		if ( '0' === substr( (string) $postcode, -1 ) ) {
			return true;
		}

		return $valid;
	}

	/**
	 * Get cart details.
	 *
	 * @deprecated 10.9.0 Cart details are now fetched via Store API.
	 *
	 * @return void
	 */
	public function ajax_get_cart_details() {
		check_ajax_referer( 'wc-stripe-get-cart-details', 'security' );
		_deprecated_function( __METHOD__, '10.9.0' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = [
			'shipping_required' => WC()->cart->needs_shipping(),
			'order_data'        => [
				'currency'     => strtolower( $currency ),
				'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			],
		];

		$data['order_data'] += $this->express_checkout_helper->build_display_items();

		wp_send_json( $data );
	}


	/**
	 * Adds the current product to the cart. Used on product detail page.
	 *
	 * @return array $data Results of adding the product to the cart.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'wc-stripe-add-to-cart', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		try {

			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			// wc_stock_amount() respects the store's decimal-quantity setting; wc_format_decimal()
			// normalises localised separators ("0,25") before the cast so fractions survive.
			$qty         = 1;
			$cleaned_qty = isset( $_POST['qty'] ) ? wc_clean( wp_unslash( $_POST['qty'] ) ) : 1;
			if ( is_string( $cleaned_qty ) ) {
				$qty = max( 0, wc_stock_amount( (float) wc_format_decimal( $cleaned_qty ) ) );
			}

			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product || ! $this->is_product_available_for_purchase( $product, $product_id ) ) {
				throw new Exception( $this->get_product_unavailable_message() );
			}

			$product_type = $product->get_type();
			$variation_id = 0;
			$attributes   = [];

			if ( ( ProductType::VARIABLE === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				if ( $variation_id ) {
					$variation_product = wc_get_product( $variation_id );
					if ( ! $variation_product instanceof WC_Product || ! $this->is_product_available_for_purchase( $variation_product, $product_id ) ) {
						throw new Exception( $this->get_product_unavailable_message() );
					}
				}
			}

			$existing_error_count = count( wc_get_notices( 'error' ) );
			$passed_validation    = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $qty, $variation_id, $attributes );

			if ( ! $passed_validation ) {
				throw new Exception( $this->get_cart_error_message( $existing_error_count ) );
			}

			$booking_ids = [];
			if ( 'booking' === $product_type ) {
				$booking_ids = $this->express_checkout_helper->get_booking_ids_from_cart();
			}

			WC()->shipping->reset_shipping();

			// First empty the cart to prevent wrong calculation.
			WC()->cart->empty_cart();

			// When a bookable product is added to the cart, a 'booking' is created with status 'in-cart'.
			// This status is used to prevent the booking from being booked by another customer
			// and should be removed when the cart is emptied for ECE purposes.
			if ( has_action( 'wc-booking-remove-inactive-cart' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
				foreach ( $booking_ids as $booking_id ) {
					do_action( 'wc-booking-remove-inactive-cart', $booking_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
				}
			}

			// On the add_to_cart() calls below: $qty can be a float on decimal-quantity stores.
			// WC_Cart::add_to_cart() accepts fractional quantities at runtime even though its stub types $quantity as int.
			// The inline PHPStan suppressions below are limited to that stub mismatch.
			$cart_item_key = false;
			if ( ProductType::VARIABLE === $product_type || 'variable-subscription' === $product_type ) {
				$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes ); // @phpstan-ignore argument.type
			} elseif ( in_array( $product_type, $this->express_checkout_helper->supported_product_types(), true ) ) {
				$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), $qty ); // @phpstan-ignore argument.type
			}

			if ( false === $cart_item_key ) {
				throw new Exception( $this->get_cart_error_message( $existing_error_count ) );
			}

			WC()->cart->calculate_totals();

			$data           = [];
			$data          += $this->express_checkout_helper->build_display_items();
			$data['result'] = 'success';

			wp_send_json( $data );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Add to cart error in express checkout.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => wp_strip_all_tags( $e->getMessage() ) ] );
		}
	}

	/**
	 * Clears cart.
	 *
	 * @return void
	 */
	public function ajax_clear_cart() {
		check_ajax_referer( 'wc-stripe-clear-cart', 'security' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : null;

		WC()->cart->empty_cart();

		if ( $booking_id ) {
			// When a bookable product is added to the cart, a 'booking' is created with status 'in-cart'.
			// This status is used to prevent the booking from being booked by another customer
			// and should be removed when the cart is emptied for express checkout purposes.
			do_action( 'wc-booking-remove-inactive-cart', $booking_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		}

		exit;
	}

	/**
	 * Normalizes address fields in WooCommerce supported format.
	 *
	 * @return void
	 */
	public function ajax_normalize_address() {
		check_ajax_referer( 'wc-stripe-express-checkout-normalize-address', 'security' );

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		// Normalizes billing and shipping state values.
		$normalized_data = $this->express_checkout_helper->normalize_state( $data );
		$normalized_data = $this->express_checkout_helper->fix_address_fields_mapping( $normalized_data );

		/**
		 * Filters the address data for express checkout after the standard normalization logic has been applied.
		 *
		 * NOTE: This data is immediately returned to the client, so be careful with the filter implementation,
		 * as it can cause issues for express checkout flows. Also ensure that data is correctly sanitized and checked
		 * as it will be visible to shoppers.
		 *
		 * @since 10.2.0
		 *
		 * @param array $normalized_data The normalized address data.
		 * @param array $data            The original address data sent from the client before normalization.
		 */
		$normalized_data = apply_filters( 'wc_stripe_express_checkout_normalize_address', $normalized_data, $data );

		wp_send_json( $normalized_data );
	}

	/**
	 * Get shipping options.
	 *
	 * @deprecated 10.9.0 Shipping options are now fetched via Store API.
	 *
	 * @see WC_Cart::get_shipping_packages().
	 * @see WC_Shipping::calculate_shipping().
	 * @see WC_Shipping::get_packages().
	 *
	 * @return void
	 */
	public function ajax_get_shipping_options() {
		check_ajax_referer( 'wc-stripe-express-checkout-shipping', 'security' );
		_deprecated_function( __METHOD__, '10.9.0' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_address          = filter_input_array(
			INPUT_POST,
			[
				'country'   => FILTER_SANITIZE_SPECIAL_CHARS,
				'state'     => FILTER_SANITIZE_SPECIAL_CHARS,
				'postcode'  => FILTER_SANITIZE_SPECIAL_CHARS,
				'city'      => FILTER_SANITIZE_SPECIAL_CHARS,
				'address'   => FILTER_SANITIZE_SPECIAL_CHARS,
				'address_2' => FILTER_SANITIZE_SPECIAL_CHARS,
			]
		);
		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_SPECIAL_CHARS ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data = $this->express_checkout_helper->get_shipping_options( $shipping_address, $should_show_itemized_view );
		wp_send_json( $data );
	}

	/**
	 * Update shipping method.
	 *
	 * @deprecated 10.9.0 Shipping method updates now use Store API.
	 *
	 * @return void
	 */
	public function ajax_update_shipping_method() {
		check_ajax_referer( 'wc-stripe-update-shipping-method', 'security' );
		_deprecated_function( __METHOD__, '10.9.0' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_methods = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$this->express_checkout_helper->update_shipping_method( $shipping_methods );

		WC()->cart->calculate_totals();

		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_SPECIAL_CHARS ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data           = [];
		$data          += $this->express_checkout_helper->build_display_items( $should_show_itemized_view );
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Gets the selected product data.
	 *
	 * @return  array $data The selected product data.
	 */
	public function ajax_get_selected_product_data() {
		check_ajax_referer( 'wc-stripe-get-selected-product-data', 'security' );

		try {
			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			// Preserve decimal quantities (see ajax_add_to_cart above): wc_format_decimal()
			// normalises localised decimal separators before the cast.
			$qty         = 1;
			$cleaned_qty = isset( $_POST['qty'] ) ? wc_clean( wp_unslash( $_POST['qty'] ) ) : 1;
			if ( is_string( $cleaned_qty ) ) {
				$qty = max( 0, wc_stock_amount( (float) wc_format_decimal( $cleaned_qty ) ) );
			}

			// Re-clamp after the add-to-cart-quantity filter (a third-party callback could
			// return a negative or non-numeric value) so the preview total can't go negative.
			$filtered_qty = apply_filters( 'woocommerce_add_to_cart_quantity', $qty, $product_id );
			$qty          = is_numeric( $filtered_qty ) ? max( 0, wc_stock_amount( (float) $filtered_qty ) ) : 0;

			$addon_value     = isset( $_POST['addon_value'] ) ? max( floatval( $_POST['addon_value'] ), 0 ) : 0;
			$product         = wc_get_product( $product_id );
			$variation_id    = null;
			$currency        = get_woocommerce_currency();
			$is_deposit      = isset( $_POST['wc_deposit_option'] ) ? 'yes' === sanitize_text_field( wp_unslash( $_POST['wc_deposit_option'] ) ) : null;
			$deposit_plan_id = isset( $_POST['wc_deposit_payment_plan'] ) ? absint( $_POST['wc_deposit_payment_plan'] ) : 0;

			if ( ! $product instanceof WC_Product || ! $this->is_product_available_for_purchase( $product, $product_id ) ) {
				throw new Exception( $this->get_product_unavailable_message() );
			}

			if ( in_array( $product->get_type(), [ ProductType::VARIABLE, 'variable-subscription' ], true ) && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );

					if ( ! $product instanceof WC_Product || ! $this->is_product_available_for_purchase( $product, $product_id ) ) {
						throw new Exception( $this->get_product_unavailable_message() );
					}
				}
			}

			if ( $this->express_checkout_helper->is_invalid_subscription_product( $product, true ) ) {
				throw new Exception( __( 'The chosen subscription product is not supported.', 'woocommerce-gateway-stripe' ) );
			}

			// Force quantity to 1 if sold individually and check for existing item in cart.
			if ( $product->is_sold_individually() ) {
				/**
				 * Filters the quantity for sold-individually products in Payment Request add-to-cart previews.
				 * Deprecated in favor of wc_stripe_express_checkout_add_to_cart_sold_individually_quantity.
				 *
				 * @deprecated 10.6.0
				 *
				 * @param int|float $quantity     Quantity to add. Defaults to 1, not the requested quantity.
				 * @param int|float $requested    Requested quantity.
				 * @param int       $product_id   Product ID.
				 * @param int|null  $variation_id Variation ID, if any.
				 */
				$qty = apply_filters_deprecated(
					'wc_stripe_payment_request_add_to_cart_sold_individually_quantity',
					[ 1, $qty, $product_id, $variation_id ],
					'10.6.0',
					'wc_stripe_express_checkout_add_to_cart_sold_individually_quantity'
				);
				/**
				 * Filters the quantity for sold-individually products in Express Checkout add-to-cart previews.
				 *
				 * @since 10.6.0
				 *
				 * @param int|float $quantity     Quantity to add.
				 * @param int|float $requested    Requested quantity.
				 * @param int       $product_id   Product ID.
				 * @param int|null  $variation_id Variation ID, if any.
				 */
				$qty = apply_filters( 'wc_stripe_express_checkout_add_to_cart_sold_individually_quantity', $qty, $qty, $product_id, $variation_id );
			}

			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1) product name 2) quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-gateway-stripe' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			$price      = $this->express_checkout_helper->get_product_price( $product, $is_deposit, $deposit_plan_id );
			$line_total = $qty * $price + $addon_value;

			$quantity_label = 1 < $qty ? ' (x' . $qty . ')' : '';

			$items = [];
			$data  = [
				'currency'        => strtolower( $currency ),
				'country_code'    => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'requestShipping' => wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping(),
			];

			$items[] = [
				'label'  => $product->get_name() . $quantity_label,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $line_total, $currency ),
			];

			// Tax the full line total ($line_total = qty x price + add-ons) like the cart does, and skip
			// tax entirely for non-taxable products so the preview can't show tax the cart won't charge.
			$total_tax  = 0;
			$line_taxes = $product->is_taxable()
				? $this->express_checkout_helper->get_taxes_like_cart( $product, $line_total )
				: [];
			foreach ( $line_taxes as $tax ) {
				$total_tax += $tax;

				$items[] = [
					'label'   => __( 'Tax', 'woocommerce-gateway-stripe' ),
					'amount'  => WC_Stripe_Helper::get_stripe_amount( $tax, $currency ),
					'pending' => 0 === $tax,
				];
			}

			if ( true === $data['requestShipping'] ) {
				$items[] = [
					'label'   => __( 'Shipping', 'woocommerce-gateway-stripe' ),
					'amount'  => 0,
					'pending' => true,
				];

				$data['shippingOptions'] = [
					'id'     => 'pending',
					'label'  => __( 'Pending', 'woocommerce-gateway-stripe' ),
					'detail' => '',
					'amount' => 0,
				];
			}

			$data['displayItems'] = $items;
			$data['total']        = [
				'label'  => $this->express_checkout_helper->get_total_label(),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $line_total + $total_tax, $currency ),
			];

			wp_send_json( $data );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Product data error in express checkout.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ] );
		}
	}

	/**
	 * Determine whether a product may be exposed through a storefront request.
	 *
	 * WC_Product::is_purchasable() permits unpublished products to users who can edit
	 * them. This endpoint is shared with storefront visitors, so it requires a published
	 * product explicitly while retaining WooCommerce's normal catalog-hidden behavior.
	 *
	 * @param WC_Product|false|null $product    Product object, or false/null when it does not exist.
	 * @param int                   $product_id Top-level product ID used for password protection.
	 * @return bool
	 */
	private function is_product_available_for_purchase( $product, $product_id ) {
		return $product instanceof WC_Product
			&& ProductStatus::PUBLISH === $product->get_status()
			&& ! post_password_required( $product_id )
			&& $product->is_purchasable();
	}

	/**
	 * Return one response for missing and unavailable products so IDs cannot be enumerated.
	 *
	 * @return string
	 */
	private function get_product_unavailable_message() {
		return __( 'This product is not available for purchase.', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Return the latest WooCommerce validation error for a publicly purchasable product.
	 *
	 * Product eligibility is checked before cart validation, so WooCommerce's useful stock
	 * and extension feedback can be preserved here without exposing non-public product data.
	 *
	 * @param int $existing_error_count Number of error notices before validation started.
	 * @return string
	 */
	private function get_cart_error_message( $existing_error_count ) {
		$error_notices = array_slice( wc_get_notices( 'error' ), $existing_error_count );
		$last_error    = end( $error_notices );

		if ( is_array( $last_error ) && isset( $last_error['notice'] ) ) {
			return (string) $last_error['notice'];
		}

		if ( is_string( $last_error ) ) {
			return $last_error;
		}

		return __( 'This product cannot be added to the cart.', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Deprecated no-op for the former express checkout error-logging endpoint.
	 *
	 * @deprecated 10.9.0 No longer called by the plugin; the endpoint no longer logs anything.
	 *
	 * @return void
	 */
	public function ajax_log_errors() {
		check_ajax_referer( 'wc-stripe-log-errors', 'security' );
		_deprecated_function( __METHOD__, '10.9.0' );

		// No-op: this endpoint is deprecated and nothing in the plugin calls it anymore. Keep the
		// original empty 200 so any remaining third-party caller is not broken mid-deprecation.
		exit;
	}
	/**
	 * Modify country locale for express checkout.
	 * Countries that don't have state fields, make the state field optional.
	 * Make postcode optional for specific countries during express checkout.
	 *
	 * @param array $locale The country locale.
	 * @return array Modified country locale.
	 */
	public function modify_country_locale_for_express_checkout( $locale ) {
		// Only modify locale settings if this is an express checkout context.
		if ( ! $this->express_checkout_helper->is_express_checkout_context() ) {
			return $locale;
		}

		// For countries that don't have state fields, make the state field optional.
		foreach ( WC_Stripe_Express_Checkout_Button_States::STATES as $country_code => $states ) {
			if ( empty( $states ) ) {
				$locale[ $country_code ]['state']['required'] = false;
			}
		}

		// List of countries where postcode is optional in express checkouts (Google Pay, Apple Pay).
		// These countries allow addresses without postal codes, but WooCommerce requires them by default.
		/**
		 * Filters countries where postcodes are optional in Express Checkout.
		 *
		 * @param string[] $country_codes Country codes where postcodes are optional.
		 */
		$countries_with_optional_postcode = apply_filters(
			'wc_stripe_express_checkout_countries_with_optional_postcode',
			[
				'AE', // United Arab Emirates
				'BH', // Bahrain
				'IL', // Israel
				'KW', // Kuwait
				'OM', // Oman
				'QA', // Qatar
				'SA', // Saudi Arabia
			]
		);

		// Make postcode optional for countries where payment providers don't require it.
		foreach ( $countries_with_optional_postcode as $country_code ) {
			if ( isset( $locale[ $country_code ] ) ) {
				$locale[ $country_code ]['postcode']['required'] = false;
			}
		}

		return $locale;
	}
}
