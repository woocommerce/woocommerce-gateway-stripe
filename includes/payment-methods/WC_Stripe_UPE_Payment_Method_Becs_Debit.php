<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles BECS Direct Debit as a UPE Payment Method.
 *
 * @extends WC_Stripe_UPE_Payment_Method
 */
class WC_Stripe_UPE_Payment_Method_Becs_Debit extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * Stripe's internal identifier for BECS Direct Debit.
	 */
	const STRIPE_ID = WC_Stripe_Payment_Methods::BECS_DEBIT;

	/**
	 * Constructor for BECS Direct Debit payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'BECS Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->label                = __( 'BECS Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __( 'Pay directly from your Australian bank account via BECS.', 'woocommerce-gateway-stripe' );
		$this->supported_currencies = [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR ];
		$this->supported_countries  = [ 'AU' ];
		$this->supports[]           = 'tokenization';
		$this->supports[]           = 'subscriptions';

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();
		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Checks if BECS is available for the Stripe account's country.
	 *
	 * @return bool True if AU-based account; false otherwise.
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
	 * Creates a BECS Debit payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token_Becs_Debit|null The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		if ( ! isset( $payment_method->id ) || ! isset( $payment_method->{self::STRIPE_ID} ) ) {
			return null;
		}

		$payment_token = new WC_Payment_Token_Becs_Debit();
		$payment_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_token( $payment_method->id );
		$payment_token->set_last4( $payment_method->{self::STRIPE_ID}->last4 );
		$payment_token->set_fingerprint( $payment_method->{self::STRIPE_ID}->fingerprint );
		$payment_token->save();

		return $payment_token;
	}
}
