<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Klarna Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Klarna extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'klarna';

	/**
	 * Constructor for giropay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Klarna', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ 'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'NOK', 'NZD', 'PLN', 'SEK', 'USD' ];
		$this->supported_countries          = [ 'AU', 'AT', 'BE', 'CA', 'CZ', 'DK', 'FI', 'FR', 'GR', 'DE', 'IE', 'IT', 'NL', 'NZ', 'NO', 'PL', 'PT', 'ES', 'SE', 'CH', 'GB', 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Klarna', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Klarna.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Klarna is available for domestic transactions in the following countries:
	 * Austria, Belgium, Canada, Czech Republic, Denmark, Finland, France, Germany, Greece, Ireland, Italy, Netherlands,
	 * New Zealand, Norway, Poland, Portugal, Spain, Sweden, Switzerland, United Kingdom, United States.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}
}
