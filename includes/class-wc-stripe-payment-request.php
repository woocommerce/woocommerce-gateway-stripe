<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Payment_Request class.
 */
class WC_Stripe_Payment_Request {

	/**
	 * Initialize class actions.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'wc_ajax_wc_stripe_get_cart_details', array( $this, 'ajax_get_cart_details' ) );
		add_action( 'wc_ajax_wc_stripe_create_order', array( $this, 'ajax_create_order' ) );
	}

	/**
	 * Check if Stripe gateway is enabled.
	 *
	 * @return bool
	 */
	protected function is_activated() {
		$options = get_option( 'woocommerce_stripe_settings', array() );

		return ! empty( $options['enabled'] ) && 'yes' === $options['enabled'];
	}

	/**
	 * Get publishable key.
	 *
	 * @return string
	 */
	protected function get_publishable_key() {
		$options = get_option( 'woocommerce_stripe_settings', array() );

		if ( empty( $options ) ) {
			return '';
		}

		return 'yes' === $options['testmode'] ? $options['test_publishable_key'] : $options['publishable_key'];
	}

	/**
	 * Load public scripts.
	 */
	public function scripts() {
		// Load PaymentRequest only on cart for now.
		if ( ! is_cart() ) {
			return;
		}

		if ( ! $this->is_activated() ) {
			return;
		}

		// $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$suffix = '';

		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );
		wp_enqueue_script( 'wc-stripe-payment-request', plugins_url( 'assets/js/payment-request' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'jquery', 'stripe' ), WC_STRIPE_VERSION, true );

		wp_localize_script(
			'wc-stripe-payment-request',
			'wcStripePaymentRequestParams',
			array(
				'wc_ajax_url'         => WC_AJAX::get_endpoint( "%%endpoint%%" ),
				'key'                 => $this->get_publishable_key(),
				'allow_prepaid_card'  => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
				'no_prepaid_card_msg' => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
				'payment_nonce'       => wp_create_nonce( 'wc-stripe-payment-request' ),
				'checkout_nonce'      => wp_create_nonce( 'woocommerce-process_checkout' ),
			)
		);
	}

	/**
	 * Get cart details.
	 */
	public function ajax_get_cart_details() {
		check_ajax_referer( 'wc-stripe-payment-request', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = array(
			'total' => array(
				'label'    => __( 'Total', 'woocommerce-gateway-stripe' ),
				'amount'   => array(
					'value'    => WC()->cart->total,
					'currency' => $currency,
				)
			),
		);

		// Set items details.
		// @TODO: optional and we don't need this right now or never.
		//
		// $items = array();

		// $cart_contents = WC()->cart->cart_contents;
		// foreach ( $cart_contents as $key => $_product ) {
		// 	$product = $_product['data'];

		// 	$items[] = array(
		// 		'label'  => $product->get_title(),
		// 		'amount' => array(
		// 			'value'    => $product->price,
		// 			'currency' => $currency,
		// 		),
		// 	);
		// }

		// $data['displayItems'] = $items;

		wp_send_json( $data );
	}

	/**
	 * Create order.
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		WC()->checkout()->process_checkout();

		die( 0 );
	}
}

new WC_Stripe_Payment_Request();
