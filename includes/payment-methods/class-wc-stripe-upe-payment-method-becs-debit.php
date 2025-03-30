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
		$this->is_reusable          = false;
		$this->label                = __( 'BECS Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __( 'Pay directly from your Australian bank account via BECS.', 'woocommerce-gateway-stripe' );
		$this->supported_currencies = [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR ];
		$this->supported_countries  = [ 'AU' ];
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
}
