<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The BLIK Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_BLIK extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::BLIK;

	/**
	 * Constructor for BLIK payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = 'BLIK';
		$this->is_reusable              = false;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::POLISH_ZLOTY ];
		$this->supported_countries      = [ 'PL' ];
		$this->label                    = 'BLIK';
		$this->description              = __(
			'BLIK is a mobile payment method primarily used in Poland. It allows customers to pay using their mobile banking app.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;
	}

	/**
	 * Checks if BLIK is available for the Stripe account's country.
	 *
	 * @return bool True if PL-based account; false otherwise.
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
