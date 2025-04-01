<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Bank Transfer Payment Method class extending UPE base class.
 */
class WC_Stripe_UPE_Payment_Method_Bank_Transfer extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::BANK_TRANSFER;

	/**
	 * Constructor for Bank Transfer payment method
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = __( 'Bank Transfer', 'woocommerce-gateway-stripe' );
		$this->is_reusable              = false;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->supported_countries      = [ 'US' ];
		$this->label                    = __( 'Bank Transfer', 'woocommerce-gateway-stripe' );
		$this->description              = __(
			'Bank Transfer enables customers to pay by transferring funds through the bank rails.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = true;
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}
}
