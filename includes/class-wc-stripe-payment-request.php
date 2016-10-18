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

		wp_enqueue_script( 'wc-stripe-payment-request', plugins_url( 'assets/js/payment-request' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'jquery' ), WC_STRIPE_VERSION, true );

		wp_localize_script(
			'wc-stripe-payment-request',
			'wcStripePaymentRequestParams',
			array(
				'wc_ajax_url' => WC_AJAX::get_endpoint( "%%endpoint%%" ),
			)
		);
	}

	/**
	 * Get cart details.
	 */
	public function ajax_get_cart_details() {
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

		// Set items details (optional and we don't need this right now).
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
}

new WC_Stripe_Payment_Request();
