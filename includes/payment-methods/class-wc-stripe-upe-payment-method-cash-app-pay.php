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
	const STRIPE_ID = 'cashapp';

	/**
	 * Constructor for Cash App payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = true;
		$this->supported_currencies         = [ 'USD' ];
		$this->supported_countries          = [ 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->supports[]                   = 'tokenization';
		$this->label                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Cash App is a popular consumer app in the US that allows customers to bank, invest, send, and receive money using their digital wallet.',
			'woocommerce-gateway-stripe'
		);

		// Cash App Pay supports subscriptions. Init subscription so it can process subscription payments.
		$this->maybe_init_subscriptions();
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
	 * Determines whether Cash App Pay is enabled at checkout.
	 *
	 * @param int    $order_id                  The order ID.
	 * @param string $account_domestic_currency The account's default currency.
	 *
	 * @return bool Whether Cash App Pay is enabled at checkout.
	 */
	public function is_enabled_at_checkout( $order_id = null, $account_domestic_currency = null ) {
		/**
		 * Cash App Pay is incapable of processing zero amount payments with saved payment methods.
		 *
		 * This is because setup intents with a saved payment method (token) fail. While we wait for a solution to this issue, we
		 * disable Cash App Pay for zero amount orders.
		 */
		if ( $this->get_current_order_amount() <= 0 ) {
			if ( isset( $GLOBALS['troubleshoot-jga'] ) ) {
				error_log( "{$GLOBALS['troubleshoot-jga']} - {$this->stripe_id} failed at 1" );
				echo( "{$GLOBALS['troubleshoot-jga']} - {$this->stripe_id} failed at 1" );
			}
			return false;
		}

		return parent::is_enabled_at_checkout( $order_id, $account_domestic_currency );
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
}
