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
		// TODO: verify nonce
		// $is_nonce_valid = check_ajax_referer( 'wc_stripe_create_checkout_session_nonce', false, false );
		// if ( ! $is_nonce_valid ) {
		// throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
		// }

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$payment_method_type     = isset( $_POST['payment_method_type'] ) ? wc_clean( wp_unslash( $_POST['payment_method_type'] ) ) : '';
		$enabled_payment_methods = $payment_method_type ? [ $payment_method_type ] : [];

		WC()->cart->calculate_totals();

		$user     = wp_get_current_user();
		$customer = new WC_Stripe_Customer( $user->ID );
		$customer->update_or_create_customer();

		// TODO: fix issue when customer does not have a billing address.
		// Critical Uncaught WC_Stripe_Exception: missing_required_customer_field: name in includes/class-wc-stripe-customer.php:265

		$currency   = get_woocommerce_currency();
		$line_items = [];
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_name   = $cart_item['data']->get_name();
			$line_items[] = [
				'price_data' => [
					'currency' => strtolower( $currency ),
					'product_data' => [
						'name' => $product_name,
					],
					'unit_amount' => WC_Stripe_Helper::get_stripe_amount( $cart_item['line_subtotal'] / $cart_item['quantity'], $currency ),
				],
				'quantity' => $cart_item['quantity'],
			];
		}

		$request = [
			'ui_mode'              => 'custom',
			'customer'             => $customer->get_id(),
			'line_items'           => $line_items,
			'payment_method_types' => $enabled_payment_methods,
			'payment_intent_data'  => [],
			'mode'                 => 'payment',
			'adaptive_pricing'     => [
				'enabled' => 'true',
			],
		];

		$checkout_session = WC_Stripe_API::request( $request, 'checkout/sessions' );

		if ( ! empty( $checkout_session->error ) ) {
			throw new Exception( $checkout_session->error->message );
		}

		wp_send_json( [ 'client_secret' => $checkout_session->client_secret ] );
	}
}
