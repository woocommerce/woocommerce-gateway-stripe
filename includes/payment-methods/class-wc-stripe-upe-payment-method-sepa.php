<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEPA Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sepa extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'sepa_debit';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Sepa::class;

	/**
	 * Constructor for SEPA payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Add payment method to user and return WC payment token.
	 *
	 * @param WP_User $user           User to add payment token to.
	 * @param object  $intent         JSON object for Stripe payment intent.
	 *
	 * @return WC_Payment_Token_CC WC object for payment token.
	 */
	public function add_token_to_user_from_intent( $user_id, $intent ) {
		return $this->add_token_to_user_from_payment_method( $user_id, $intent->payment_method );
	}
}
