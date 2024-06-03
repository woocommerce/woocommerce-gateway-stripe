<?php
/**
 * Class UPE Cash App Pay Method
 *
 * @package WooCommerce_Stripe\Payment_Methods
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Cash App Pay Payment Method class extending UPE base class.
 */
class WC_Stripe_UPE_Payment_Method_Cash_App_Pay extends WC_Stripe_UPE_Payment_Method {

	/**
	 * The Stripe ID for the payment method.
	 */
	const STRIPE_ID = 'cashapp';

	/**
	 * Constructor for Cash App payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ 'USD' ];
		$this->supported_countries          = [ 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Cash App is a popular consumer app in the US that allows customers to bank, invest, send, and receive money using their digital wallet.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'order_received_text' ], 10, 2 );
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Cash App Pay is only available to merchants in the United States.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Custom Cash App order received text (for mobile only). This identifies the order failed and displays the error message.
	 *
	 * @param string   $text Default text.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function order_received_text( $text, $order ) {
		$redirect_status = '';
		if ( isset( $_GET['redirect_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$redirect_status = wc_clean( wp_unslash( $_GET['redirect_status'] ) );
		}
		if ( $order && $this->id === $order->get_payment_method() && 'failed' === $redirect_status ) {
			wc_add_notice( __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-gateway-stripe' ), 'error' );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		return $text;
	}
}
