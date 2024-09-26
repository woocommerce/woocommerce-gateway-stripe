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
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * The Stripe ID for the payment method.
	 */
	const STRIPE_ID = WC_Stripe_Payment_Methods::CASHAPP_PAY;

	/**
	 * Constructor for Cash App payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = true;
		$this->supported_currencies         = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->supported_countries          = [ 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->supports[]                   = 'tokenization';
		$this->label                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Cash App is a popular consumer app in the US that allows customers to bank, invest, send, and receive money using their digital wallet.',
			'woocommerce-gateway-stripe'
		);

		// Cash App Pay supports subscriptions. Init subscriptions so it can process subscription payments.
		$this->maybe_init_subscriptions();

		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'order_received_text_for_wallet_failure' ], 10, 2 );
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
	 * Returns a string representing payment method type to query for when retrieving saved payment methods from Stripe.
	 *
	 * @return string The payment method type.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Creates a Cash App Pay payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_CashApp();

		$token->set_gateway_id( $this->id );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );

		if ( isset( $payment_method->cashapp->cashtag ) ) {
			$token->set_cashtag( $payment_method->cashapp->cashtag );
		}

		$token->save();

		return $token;
	}

	/**
	 * Custom wallet payment method order received text (for mobile only). This identifies the order failed and displays the error message.
	 *
	 * @param string   $text Default text.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function order_received_text_for_wallet_failure( $text, $order ) {
		$redirect_status = '';
		if ( isset( $_GET['redirect_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$redirect_status = wc_clean( wp_unslash( $_GET['redirect_status'] ) );
		}
		if ( $order && $this->id === $order->get_payment_method() && 'failed' === $redirect_status ) {
			$text      = '<p class="woocommerce-error">';
				$text .= esc_html( 'Unfortunately your order cannot be processed as the payment method has declined your transaction. Please attempt your purchase again.' );
			$text     .= '</p>';
			$text     .= '<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">';
				$text .= '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '" class="button pay">' . esc_html( 'Pay' ) . '</a>';
			if ( is_user_logged_in() ) {
				$text .= '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="button pay">' . esc_html( 'My account' ) . '</a>';
			}
			$text .= '</p>';
		}

		return $text;
	}
}
