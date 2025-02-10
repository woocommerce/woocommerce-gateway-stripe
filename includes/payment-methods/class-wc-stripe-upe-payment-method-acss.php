<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Canadian Pre-Authorized Debit (ACSS Debit) Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_ACSS extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::ACSS_DEBIT;

	/**
	 * Constructor for ACSS Debit payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable              = true;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR ]; // The US dollar is supported, but has a high risk of failure since only a few Canadian bank accounts support it.
		$this->supported_countries      = [ 'CA' ];
		$this->label                    = __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->description              = __(
			'Canadian Pre-Authorized Debit is a payment method that allows customers to pay using their Canadian bank account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}
}
