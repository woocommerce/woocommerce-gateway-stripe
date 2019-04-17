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
		add_action( 'wc_ajax_wc_stripe_payment_intent_auth_succeeded', array( $this, 'successful_auth' ) );
		add_action( 'wc_ajax_wc_stripe_payment_intent_auth_failed', array( $this, 'failed_auth' ) );
	}

	/**
	 * Returns an instantiated gateway.
	 *
	 * @since 4.2.0
	 * @return WC_Gateway_Stripe
	 */
	protected function get_gateway() {
		if ( ! isset( $this->gateway ) ) {
			// ToDo: Load the correct gateway here (preorders, etc).
			$this->gateway = new WC_Gateway_Stripe();
		}

		return $this->gateway;
	}

	/**
	 * Responds with a JSON-encoded result.
	 *
	 * @since 4.2
	 * @param mixed $result The result to send back to JS, JSON-encodeable.
	 */
	protected function respond( $result ) {
		echo wp_json_encode( $result );
		exit;
	}

	/**
	 * Formats an exception as proper JSON.
	 *
	 * @since 4.2.0
	 * @param WC_Stripe_Exception $e The exception that occured.
	 */
	protected function respond_with_exception( $e ) {
		$response = array(
			'error' => array(
				'error'   => $e->getMessage(),
				'message' => $e->getLocalizedMessage(),
			),
		);
		echo wp_json_encode( $response );
		exit;
	}

	/**
	 * Loads the order from the current request.
	 *
	 * @since 4.2.0
	 * @throws WC_Stripe_Exception An exception if there is no order ID or the order does not exist.
	 * @return WC_Order
	 */
	protected function get_order_from_request() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : null; // wpcs: csrf ok.
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new WC_Stripe_Exception( 'missing-order', __( 'Missing order ID for PaymentIntent action', 'woocommerce-gateway-stripe' ) );
		}

		return $order;
	}

	/**
	 * Handles successful PaymentIntent authentications.
	 *
	 * @since 4.2.0
	 */
	public function successful_auth() {
		$gateway = $this->get_gateway();

		try {
			$order = $this->get_order_from_request();
			$gateway->maybe_complete_order( $order );

			$this->respond(
				array(
					'success'  => true,
					'redirect' => $gateway->get_return_url( $order ),
				)
			);
		} catch ( WC_Stripe_Exception $e ) {
			$this->respond_with_exception( $e );
		}
	}

	/**
	 * Handles failed PaymentIntent authentications.
	 *
	 * @since 4.2.0
	 */
	public function failed_auth() {
		$gateway = $this->get_gateway();

		try {
			$order = $this->get_order_from_request();
			$gateway->failed_auth( $order );

			$this->respond(
				array(
					'success' => true,
				)
			);
		} catch ( WC_Stripe_Exception $e ) {
			$this->respond_with_exception( $e );
		}
	}
}

new WC_Stripe_Intent_Controller();
