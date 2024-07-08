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
		$this->label                        = __( 'Klarna', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Klarna.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns the currencies this UPE method supports for the Stripe account.
	 *
	 * @return array
	 */
	public function get_supported_currencies() {
		$billing_country = isset( WC()->session ) && isset( WC()->session->get( 'customer' )['country'] ) ? WC()->session->get( 'customer' )['country'] : '';

		// Countries in the EEA can transact across all other EEA countries. This includes Switzerland and the UK who aren't strictly in the EU.
		$eea_countries = array_merge( WC_Stripe_Helper::get_european_economic_area_countries(), [ 'CH', 'GB' ] );

		if ( in_array( $billing_country, $eea_countries, true ) ) {
			return WC_Stripe_Helper::get_european_economic_area_currencies( $billing_country );
		}

		return [ strtoupper( WC_Stripe::get_instance()->account->get_account_default_currency() ) ];
	}

	/**
	 * Determines whether the payment method is restricted to the Stripe account's currency.
	 * The restriction is based on the billing country of the customer for Klarna.
	 * If the customer is in the EEA, Switzerland or UK, the currency is not restricted within these countries.
	 *
	 * @return bool
	 */
	public function has_domestic_transactions_restrictions(): bool {
		$account         = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country = strtoupper( $account['country'] ?? '' );

		$billing_country = isset( WC()->session ) && isset( WC()->session->get( 'customer' )['country'] ) ? WC()->session->get( 'customer' )['country'] : '';

		// Countries in the EEA can transact across all other EEA countries. This includes Switzerland and the UK who aren't strictly in the EU.
		$eea_countries = array_merge( WC_Stripe_Helper::get_european_economic_area_countries(), [ 'CH', 'GB' ] );

		// If the merchant and billing country both are in the EEA, there is no domestic transaction restrictions
		if ( in_array( $account_country, $eea_countries, true ) && in_array( $billing_country, $eea_countries, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the supported customer locations for which charges for a payment method can be processed.
	 *
	 * Klarna has unique requirements for domestic transactions. The customer must be located in the same country as the merchant's Stripe account.
	 * Additionally, merchants located in the EEA can transact with customers located across all other EEA countries - including Switzerland and the UK.
	 *
	 * @return array Supported customer locations.
	 */
	public function get_available_billing_countries() {
		$account         = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country = strtoupper( $account['country'] ?? '' );

		// Countries in the EEA can transact across all other EEA countries. This includes Switzerland and the UK who aren't strictly in the EU.
		$eea_countries = array_merge( WC_Stripe_Helper::get_european_economic_area_countries(), [ 'CH', 'GB' ] );

		// If the merchant is in the EEA, all EEA countries are supported.
		if ( in_array( $account_country, $eea_countries, true ) ) {
			return $eea_countries;
		}

		return parent::get_available_billing_countries();
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Klarna is available for the following countries: AU, AT, BE, CA, CZ, DK, FI, FR, GR, DE, IE, IT, NL, NZ, NO, PL, PT, ES, SE, CH, GB, US.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}
}
