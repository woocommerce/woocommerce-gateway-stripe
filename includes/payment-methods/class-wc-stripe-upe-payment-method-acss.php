<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Canadian Pre-Authorized Debit (ACSS Debit) Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_ACSS extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	const STRIPE_ID = WC_Stripe_Payment_Methods::ACSS_DEBIT;

	/**
	 * Constructor for ACSS Debit payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable              = true;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR ]; // The US dollar is also supported, but has a high risk of failure since only a few Canadian bank accounts support it.
		$this->supported_countries      = [ 'CA' ];
		$this->label                    = __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->description              = __(
			'Canadian Pre-Authorized Debit is a payment method that allows customers to pay using their Canadian bank account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;
		$this->supports[]               = 'tokenization';
		$this->supports[]               = 'subscriptions';

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Creates an ACSS payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token_ACSS|null The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$payment_token = new WC_Payment_Token_ACSS();
		$payment_token->set_token( $payment_method->id );
		$payment_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_last4( $payment_method->acss_debit->last4 );
		$payment_token->set_bank_name( $payment_method->acss_debit->bank_name );
		$payment_token->set_fingerprint( $payment_method->acss_debit->fingerprint );
		$payment_token->save();

		return $payment_token;
	}
}
