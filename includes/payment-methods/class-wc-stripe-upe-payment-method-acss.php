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
		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Canadian Pre-Autorized Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = true;
		$this->supported_currencies         = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->supported_countries          = [ 'CA' ];
		$this->label                        = __( 'Canadian Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Canadian Pre-Authorized Debit is a payment method that allows customers to pay using their Canadian bank account.',
			'woocommerce-gateway-stripe'
		);

		$this->is_deferred_intent = false;
	}

	public function get_testing_instructions() {
		return __( 'Use the following test account details:', 'woocommerce-gateway-stripe' ) . '<br>' .
			__( 'Account number: 000123456789', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Canadian Pre-Authorized Debit is only available for domestic transactions in the United States or Canada.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	// public function is_available_for_account_country() {
	// 	return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	// }
}
