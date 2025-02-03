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
		$this->is_reusable          = false; // Usually ACH requires verification per transaction.
		$this->supported_currencies = [ 'USD' ];
		$this->supported_countries  = [ 'US' ];
		$this->label                = __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __( 'Pay directly from your US bank account via ACH.', 'woocommerce-gateway-stripe' );
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
}
