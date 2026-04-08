<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_Sessions_Ajax_Handler class.
 */
class WC_Stripe_Checkout_Sessions_Ajax_Handler {
	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wc_ajax_wc_stripe_create_checkout_session', [ $this, 'create_checkout_session' ] );
		add_action( 'wc_ajax_wc_stripe_update_checkout_session', [ $this, 'update_checkout_session' ] );
	}

	/**
	 * Create a Stripe Checkout Session and return the client secret.
	 *
	 * @return void
	 */
	public function create_checkout_session(): void {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_checkout_session_nonce', 'security', false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this request. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			$wc_customer = WC()->customer;
			if ( ! $wc_customer ) {
				throw new Exception( __( 'Unable to retrieve customer data.', 'woocommerce-gateway-stripe' ) );
			}

			$user_id = $wc_customer->get_id();

			// TODO: Test guest checkout flow.
			try {
				$stripe_customer = new WC_Stripe_Customer( $user_id );
				$stripe_customer->maybe_create_customer();
			} catch ( Exception $e ) {
				throw new Exception( __( 'Unable to create or retrieve Stripe customer.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
			}

			$currency = get_woocommerce_currency();
			// Payable cart total: subtotal, tax, shipping, fees, minus discounts (same as checkout order total).
			$cart_total = WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), $currency );

			$line_items = [
				[
					'price_data' => [
						'currency'     => strtolower( $currency ),
						'product_data' => [
							'name' => __( 'Cart total', 'woocommerce-gateway-stripe' ),
						],
						'unit_amount'  => $cart_total,
					],
					// As we are using one aggregate line item: the payable total lives in unit_amount, not in quantity × unit price.
					'quantity'   => 1,
				],
			];

			$first_name = get_user_meta( $user_id, 'first_name', true );
			$last_name  = get_user_meta( $user_id, 'last_name', true );
			$full_name  = trim( sanitize_text_field( $first_name ) . ' ' . sanitize_text_field( $last_name ) );
			$email      = $wc_customer->get_email();

			$payment_intent_metadata = apply_filters(
				'wc_stripe_payment_metadata',
				[
					'customer_name'  => $full_name,
					'customer_email' => $email,
					'site_url'       => esc_url_raw( get_site_url() ),
					'payment_type'   => 'single',
				],
				null,
				null
			);

			$request = [
				'ui_mode'                       => 'custom',
				'customer'                      => $stripe_customer->get_id(),
				'line_items'                    => $line_items,
				'excluded_payment_method_types' => WC_Stripe::get_instance()->get_main_stripe_gateway()->get_excluded_payment_method_types(),
				'payment_intent_data'           => [
					'metadata'      => $payment_intent_metadata,
					'receipt_email' => $email,
					'shipping'      => [
						'name'    => $full_name,
						'address' => [
							'line1'       => $wc_customer->get_shipping_address_1(),
							'line2'       => $wc_customer->get_shipping_address_2(),
							'city'        => $wc_customer->get_shipping_city(),
							'country'     => $wc_customer->get_shipping_country(),
							'postal_code' => $wc_customer->get_shipping_postcode(),
							'state'       => $wc_customer->get_shipping_state(),
						],
					],
				],
				'mode'                          => 'payment',
				'adaptive_pricing'              => [
					'enabled' => 'true',
				],
				'saved_payment_method_options'  => [
					'payment_method_save' => 'enabled',
				],
			];

			$checkout_session = WC_Stripe_API::request( $request, 'checkout/sessions' );

			if ( ! empty( $checkout_session->error ) ) {
				$message = empty( $checkout_session->error->message ) ? __( 'Checkout Sessions API returned an error', 'woocommerce-gateway-stripe' ) : $checkout_session->error->message;
				throw new Exception( $message );
			}

			if ( empty( $checkout_session->client_secret ) ) {
				throw new Exception( __( 'Unable to create Stripe Checkout Session.', 'woocommerce-gateway-stripe' ) );
			}

			wp_send_json_success(
				[
					'client_secret' => $checkout_session->client_secret,
					'session_id'    => $checkout_session->id,
				]
			);
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Create checkout session error.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Update a Stripe Checkout Session. Currently only used to update the line items.
	 *
	 * @return void
	 */
	public function update_checkout_session(): void {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_update_checkout_session_nonce', 'security', false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this request. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			$session_id = isset( $_POST['checkout_session_id'] )
				? wc_clean( wp_unslash( $_POST['checkout_session_id'] ) )
				: '';
			if ( ! is_string( $session_id ) || '' === $session_id ) {
				throw new Exception( __( 'Checkout session ID is required.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
			}

			// Recalculate totals.
			WC()->cart->calculate_totals();

			$currency   = get_woocommerce_currency();
			$cart_total = WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), $currency );
			$request    = [
				'line_items' => [
					[
						'price_data' => [
							'currency'     => strtolower( $currency ),
							'product_data' => [
								'name' => __( 'Cart total', 'woocommerce-gateway-stripe' ),
							],
							'unit_amount'  => $cart_total,
						],
						'quantity'   => 1,
					],
				],
			];

			$checkout_session = WC_Stripe_API::request( $request, "checkout/sessions/$session_id" );

			if ( ! empty( $checkout_session->error ) ) {
				$message = empty( $checkout_session->error->message ) ? __( 'Checkout Sessions update API returned an error', 'woocommerce-gateway-stripe' ) : $checkout_session->error->message;
				throw new Exception( $message );
			}

			wp_send_json_success( [ 'result' => 'success' ] );

		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Update checkout session error.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}
}
