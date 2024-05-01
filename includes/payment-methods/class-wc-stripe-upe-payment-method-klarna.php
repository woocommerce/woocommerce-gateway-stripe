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
	 * Determines if the billing country is considered a domestic transaction.
	 *
	 * Klarna has unique requirements for domestic transactions. The customer must be located in the same country as the merchant's Stripe account.
	 * Additionally, customers in the EEA can transact across all other EEA countries - including Switzerland and the UK.
	 *
	 * @return bool True if the transaction is domestic, false otherwise.
	 */
	public function is_domestic_transaction( $billing_country ) {
		// If the customer is in the same country as the merchant's Stripe account, the transaction is domestic.
		if ( parent::is_domestic_transaction( $billing_country ) ) {
			return true;
		}

		$account_country = strtoupper( WC_Stripe::get_instance()->account->get_cached_account_data()['country'] ?? WC()->countries->get_base_country() );

		// Countries in the EEA can transact across all other EEA countries. This includes Switzerland and the UK who aren't strictly in the EU.
		$eea_countries = WC_Stripe_Helper::get_european_economic_area_countries() + [ 'CH', 'GB' ];

		// If the customer is in the EEA and the merchant is in the EEA, the transaction is also considered domestic for Klarna.
		if ( in_array( $billing_country, $eea_countries, true ) && in_array( $account_country, $eea_countries, true ) ) {
			return true;
		}

		return false;
	}
}
