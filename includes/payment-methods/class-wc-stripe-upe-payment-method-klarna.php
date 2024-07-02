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
	 * Returns the supported customer locations for which charges for a payment method can be processed.
	 *
	 * Klarna has unique requirements for domestic transactions. The customer must be located in the same country as the merchant's Stripe account.
	 * Additionally, if the merchant is in the EEA, the country they can transact with depends on the presentment currency.
	 *
	 * EUR stores can transact with other EUR countries. Stores with currencies like GBP, CHF, etc. can only transact with customers located in those countries.
	 * This creates the following unique situations:
	 *  - Stores presenting EUR, with a Stripe account in any EEA country including Switzerland or the UK can transact with countries where Euros are the standard currency: AT, BE, FI, FR, GR, DE, IE, IT, NL, PT, ES.
	 *  - Stores presenting GBP with a Stripe account in any EEA country including Switzerland or the UK can transact with: GB.
	 *  - Stores presenting NOK with a Stripe account in France, for example, cannot sell into France. They can only sell into Norway.
	 *
	 * @see https://docs.stripe.com/payments/klarna#:~:text=Merchant%20country%20availability
	 *
	 * @return array Supported customer locations.
	 */
	public function get_available_billing_countries() {
		$account         = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country = strtoupper( $account['country'] );

		// Countries in the EEA + UK and Switzerland can transact across all other EEA countries as long as the currency matches.
		$eea_countries = array_merge( WC_Stripe_Helper::get_european_economic_area_countries(), [ 'CH', 'GB' ] );

		// Countries outside the EEA can only transact with customers in their own country.
		if ( ! in_array( $account_country, $eea_countries, true ) ) {
			return [ $account_country ];
		}

		// EEA currencies can only transact with countries where that currency is the standard currency.
		switch ( get_woocommerce_currency() ) {
			case 'CHF':
				return [ 'CH' ];
			case 'CZK':
				return [ 'CZ' ];
			case 'DKK':
				return [ 'DK' ];
			case 'NOK':
				return [ 'NO' ];
			case 'PLN':
				return [ 'PL' ];
			case 'SEK':
				return [ 'SE' ];
			case 'GBP':
				return [ 'GB' ];
			case 'EUR':
				// EEA countries that use Euro.
				return [ 'AT', 'BE', 'FI', 'FR', 'GR', 'DE', 'IE', 'IT', 'NL', 'PT', 'ES' ];
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
