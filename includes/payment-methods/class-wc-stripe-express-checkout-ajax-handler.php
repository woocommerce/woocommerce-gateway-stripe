<?php
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
		add_action( 'wc_ajax_wc_stripe_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wc_ajax_wc_stripe_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wc_ajax_wc_stripe_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wc_ajax_wc_stripe_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
		add_action( 'wc_ajax_wc_stripe_clear_cart', [ $this, 'ajax_clear_cart' ] );
		add_action( 'wc_ajax_wc_stripe_log_errors', [ $this, 'ajax_log_errors' ] );
		add_action( 'wc_ajax_wc_stripe_pay_for_order', [ $this, 'ajax_pay_for_order' ] );
	}

	/**
	 * Get cart details.
	 */
	public function ajax_get_cart_details() {
		check_ajax_referer( 'wc-stripe-get-cart-details', 'security' );

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

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
		$product      = wc_get_product( $product_id );
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		} elseif ( in_array( $product_type, $this->express_checkout_helper->supported_product_types(), true ) ) {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		WC()->cart->calculate_totals();

		$data           = [];
		$data          += $this->express_checkout_helper->build_display_items();
		$data['result'] = 'success';

		if ( 'booking' === $product_type ) {
			$booking_id = $this->express_checkout_helper->get_booking_id_from_cart();

			if ( ! empty( $booking_id ) ) {
				$data['bookingId'] = $booking_id;
			}
		}

		// @phpstan-ignore-next-line (return statement is added)
		wp_send_json( $data );
	}

	/**
	 * Clears cart.
	 */
	public function ajax_clear_cart() {
		check_ajax_referer( 'wc-stripe-clear-cart', 'security' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : null;

		WC()->cart->empty_cart();

		if ( $booking_id ) {
			// When a bookable product is added to the cart, a 'booking' is create with status 'in-cart'.
			// This status is used to prevent the booking from being booked by another customer
			// and should be removed when the cart is emptied for PRB purposes.
			do_action( 'wc-booking-remove-inactive-cart', $booking_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		}

		exit;
	}

	/**
	 * Get shipping options.
	 *
	 * @see WC_Cart::get_shipping_packages().
	 * @see WC_Shipping::calculate_shipping().
	 * @see WC_Shipping::get_packages().
	 */
	public function ajax_get_shipping_options() {
		check_ajax_referer( 'wc-stripe-express-checkout-shipping', 'security' );

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
	 */
	public function ajax_update_shipping_method() {
		check_ajax_referer( 'wc-stripe-update-shipping-method', 'security' );

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

		try { // @phpstan-ignore-line (return statement is added)
			$product_id      = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$qty             = ! isset( $_POST['qty'] ) ? 1 : apply_filters( 'woocommerce_add_to_cart_quantity', absint( $_POST['qty'] ), $product_id );
			$addon_value     = isset( $_POST['addon_value'] ) ? max( floatval( $_POST['addon_value'] ), 0 ) : 0;
			$product         = wc_get_product( $product_id );
			$variation_id    = null;
			$currency        = get_woocommerce_currency();
			$is_deposit      = isset( $_POST['wc_deposit_option'] ) ? 'yes' === sanitize_text_field( wp_unslash( $_POST['wc_deposit_option'] ) ) : null;
			$deposit_plan_id = isset( $_POST['wc_deposit_payment_plan'] ) ? absint( $_POST['wc_deposit_payment_plan'] ) : 0;

			if ( ! is_a( $product, 'WC_Product' ) ) {
				/* translators: 1) The product Id */
				throw new Exception( sprintf( __( 'Product with the ID (%1$s) cannot be found.', 'woocommerce-gateway-stripe' ), $product_id ) );
			}

			if ( in_array( $product->get_type(), [ 'variable', 'variable-subscription' ], true ) && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
				}
			}

			if ( $this->express_checkout_helper->is_invalid_subscription_product( $product, true ) ) {
				throw new Exception( __( 'The chosen subscription product is not supported.', 'woocommerce-gateway-stripe' ) );
			}

			// Force quantity to 1 if sold individually and check for existing item in cart.
			if ( $product->is_sold_individually() ) {
				$qty = apply_filters( 'wc_stripe_payment_request_add_to_cart_sold_individually_quantity', 1, $qty, $product_id, $variation_id );
			}

			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1) product name 2) quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-gateway-stripe' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			$price = $this->express_checkout_helper->get_product_price( $product, $is_deposit, $deposit_plan_id );
			$total = $qty * $price + $addon_value;

			$quantity_label = 1 < $qty ? ' (x' . $qty . ')' : '';

			$items = [];
			$data  = [
				'currency'        => strtolower( $currency ),
				'country_code'    => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'requestShipping' => wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping(),
			];

			$items[] = [
				'label'  => $product->get_name() . $quantity_label,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $total ),
			];

			$total_tax = 0;
			foreach ( $this->express_checkout_helper->get_taxes_like_cart( $product, $price ) as $tax ) {
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
				'amount' => WC_Stripe_Helper::get_stripe_amount( $total + $total_tax, $currency ),
			];

			wp_send_json( $data );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Product data error in express checkout: ' . $e->getMessage() );
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ] );
		}
	}

	/**
	 * Create order. Security is handled by WC.
	 */
	public function ajax_create_order() {
		try {
			if ( WC()->cart->is_empty() ) {
				wp_send_json_error( __( 'Empty cart', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
				define( 'WOOCOMMERCE_CHECKOUT', true );
			}

			$this->express_checkout_helper->fix_address_fields_mapping();

			// Normalizes billing and shipping state values.
			$this->express_checkout_helper->normalize_state();

			// In case the state is required, but is missing, add a more descriptive error notice.
			$this->express_checkout_helper->validate_state();

			WC()->checkout()->process_checkout();
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Failed to create order for express checkout payment: ' . $e );

			$response = [
				'result'   => 'error',
				'messages' => $e->getMessage(),
			];
			wp_send_json( $response, 400 );
		}

		die( 0 );
	}

	/**
	 * Log errors coming from express checkout elements
	 */
	public function ajax_log_errors() {
		check_ajax_referer( 'wc-stripe-log-errors', 'security' );

		$errors = isset( $_POST['errors'] ) ? wc_clean( wp_unslash( $_POST['errors'] ) ) : '';

		WC_Stripe_Logger::log( $errors );

		exit;
	}

	/**
	 * Processes the Pay for Order AJAX request from the Express Checkout.
	 */
	public function ajax_pay_for_order() {
		check_ajax_referer( 'wc-stripe-pay-for-order' );

		if (
			! isset( $_POST['payment_method'] ) || 'stripe' !== $_POST['payment_method']
			|| ! isset( $_POST['order'] ) || ! intval( $_POST['order'] )
			|| ! isset( $_POST['wc-stripe-payment-method'] ) || empty( $_POST['wc-stripe-payment-method'] )
		) {
			// Incomplete request.
			$response = [
				'result'   => 'error',
				'messages' => __( 'Invalid request', 'woocommerce-gateway-stripe' ),
			];
			wp_send_json( $response, 400 );
			return;
		}

		$order_id = intval( $_POST['order'] );
		try {
			// Set up an environment, similar to core checkout.
			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			wc_set_time_limit( 0 );

			// Load the order.
			$order = wc_get_order( $order_id );

			if ( ! is_a( $order, WC_Order::class ) ) {
				throw new Exception( __( 'Invalid order!', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! $order->needs_payment() ) {
				throw new Exception( __( 'This order does not require payment!', 'woocommerce-gateway-stripe' ) );
			}

			// Process the payment.
			$result = WC_Stripe::get_instance()->get_main_stripe_gateway()->process_payment( $order_id );

			// process_payment() should only return `success` or throw an exception.
			if ( ! is_array( $result ) || ! isset( $result['result'] ) || 'success' !== $result['result'] || ! isset( $result['redirect'] ) ) {
				throw new Exception( __( 'Unable to determine payment success.', 'woocommerce-gateway-stripe' ) );
			}

			// Include the order ID in the result.
			$result['order_id'] = $order_id;

			$result = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Pay for order failed for order ' . $order_id . ' with express checkout: ' . $e );

			$result = [
				'result'   => 'error',
				'messages' => $e->getMessage(),
			];
		}

		wp_send_json( $result );
	}
}
