<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles ACH Direct Debit as a UPE Payment Method.
 *
 * @extends WC_Stripe_UPE_Payment_Method
 */
class WC_Stripe_UPE_Payment_Method_ACH extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * Stripe's internal identifier for ACH Direct Debit.
	 */
	const STRIPE_ID = WC_Stripe_Payment_Methods::ACH;

	/**
	 * Constructor for ACH Direct Debit payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->label                = __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __( 'Pay directly from your US bank account via ACH.', 'woocommerce-gateway-stripe' );
		$this->supported_currencies = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->supported_countries  = [ 'US' ];
		$this->supports[]           = 'tokenization';
		$this->supports[]           = 'subscriptions';

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Checks if ACH is available for the Stripe account's country.
	 *
	 * @return bool True if US-based account; false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Creates an ACH payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token_ACH|null The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		if ( ! isset( $payment_method->id ) || ! isset( $payment_method->us_bank_account ) ) {
			return null;
		}

		$payment_token = new WC_Payment_Token_ACH();
		$payment_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_token( $payment_method->id );
		$payment_token->set_last4( $payment_method->us_bank_account->last4 );
		$payment_token->set_bank_name( $payment_method->us_bank_account->bank_name );
		$payment_token->set_account_type( $payment_method->us_bank_account->account_type );
		$payment_token->set_fingerprint( $payment_method->us_bank_account->fingerprint );
		$payment_token->save();

		return $payment_token;
	}
}
