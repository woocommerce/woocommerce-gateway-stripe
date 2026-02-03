<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_Sessions_Controller class.
 */
class WC_Stripe_Checkout_Sessions_Controller {
	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wc_ajax_wc_stripe_create_checkout_session', [ $this, 'create_checkout_session' ] );
	}

	/**
	 * Create a Stripe Checkout Session and return the client secret.
	 *
	 * @return void
	 * @throws WC_Stripe_Exception When unable to create the Checkout Session.
	 */
	public function create_checkout_session(): void {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_checkout_session_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}

			$payment_method_type     = isset( $_POST['payment_method_type'] ) ? wc_clean( wp_unslash( $_POST['payment_method_type'] ) ) : '';
			$enabled_payment_methods = $payment_method_type ? [ $payment_method_type ] : [];

			// TODO: Test guest checkout flow.
			try {
				$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );
				$customer->maybe_create_customer();
			} catch ( Exception $e ) {
				throw new Exception( __( 'Unable to create or retrieve Stripe customer.', 'woocommerce-gateway-stripe' ) );
			}

			if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
				throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
			}

			$currency   = get_woocommerce_currency();
			$line_items = [];
			foreach ( WC_Stripe_Helper::build_line_items() as $raw_line_item ) {
				if ( 'total_discount' === $raw_line_item['key'] ) {
					// TODO: Stripe Checkout handles discounts/coupons differently. Skip for now.
					continue;
				}

				$line_items[] = [
					'price_data' => [
						'currency' => strtolower( $currency ),
						'product_data' => [
							'name' => $raw_line_item['label'],
						],
						'unit_amount' => $raw_line_item['amount'],
					],
					'quantity' => 1,
				];
			}

			$request = [
				'ui_mode'              => 'custom',
				'customer'             => $customer->get_id(),
				'line_items'           => $line_items,
				'payment_method_types' => $enabled_payment_methods,
				'payment_intent_data'  => [], // @todo Pass additional data if needed.
				'mode'                 => 'payment',
				'adaptive_pricing'     => [
					'enabled' => 'true',
				],
			];

			$checkout_session = WC_Stripe_API::request( $request, 'checkout/sessions' );

			if ( ! empty( $checkout_session->error ) ) {
				throw new Exception( $checkout_session->error->message );
			}

			if ( empty( $checkout_session->client_secret ) ) {
				throw new Exception( __( 'Unable to create Stripe Checkout Session.', 'woocommerce-gateway-stripe' ) );
			}

			wp_send_json_success( [ 'client_secret' => $checkout_session->client_secret ] );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Create checkout session error.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}
}
