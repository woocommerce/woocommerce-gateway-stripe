<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Intent_Controller class.
 *
 * Handles in-checkout AJAX calls, related to Payment Intents.
 */
class WC_Stripe_Intent_Controller {
	/**
	 * Holds an instance of the gateway class.
	 *
	 * @since 4.2.0
	 * @var WC_Gateway_Stripe
	 */
	protected $gateway;

	/**
	 * Class constructor, adds the necessary hooks.
	 *
	 * @since 4.2.0
	 */
	public function __construct() {
		add_action( 'wc_ajax_wc_stripe_verify_intent', array( $this, 'verify_intent' ) );
	}

	/**
	 * Returns an instantiated gateway.
	 *
	 * @since 4.2.0
	 * @return WC_Gateway_Stripe
	 */
	protected function get_gateway() {
		if ( ! isset( $this->gateway ) ) {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$class_name = 'WC_Stripe_Subs_Compat';
			} else {
				$class_name = 'WC_Gateway_Stripe';
			}

			$this->gateway = new $class_name();
		}

		return $this->gateway;
	}

	/**
	 * Loads the order from the current request.
	 *
	 * @since 4.2.0
	 * @throws WC_Stripe_Exception An exception if there is no order ID or the order does not exist.
	 * @return WC_Order
	 */
	protected function get_order_from_request() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'wc_stripe_confirm_pi' ) ) {
			throw new WC_Stripe_Exception( 'missing-nonce', __( 'CSRF verification failed.', 'woocommerce-gateway-stripe' ) );
		}

		// Load the order ID.
		$order_id = null;
		if ( isset( $_GET['order'] ) && absint( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		}

		// Retrieve the order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new WC_Stripe_Exception( 'missing-order', __( 'Missing order ID for payment confirmation', 'woocommerce-gateway-stripe' ) );
		}

		return $order;
	}

	/**
	 * Handles successful PaymentIntent authentications.
	 *
	 * @since 4.2.0
	 */
	public function verify_intent() {
		$gateway = $this->get_gateway();

		try {
			$order           = $this->get_order_from_request();
			$should_redirect = $gateway->verify_intent_after_checkout( $order );

			if ( $should_redirect ) {
				$redirect_url = isset( $_GET['redirect_to'] ) // wpcs: csrf ok.
					? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) // wpcs: csrf ok.
					: $gateway->get_return_url( $order );

				wp_safe_redirect( $redirect_url );

			}
		} catch ( WC_Stripe_Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}

		exit;
	}
}

new WC_Stripe_Intent_Controller();
