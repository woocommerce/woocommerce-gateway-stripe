<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Klarna Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Klarna extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::KLARNA;

	/**
	 * Constructor for giropay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Klarna', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [
			WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR,
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR,
			WC_Stripe_Currency_Code::SWISS_FRANC,
			WC_Stripe_Currency_Code::CZECH_KORUNA,
			WC_Stripe_Currency_Code::DANISH_KRONE,
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::POUND_STERLING,
			WC_Stripe_Currency_Code::NORWEGIAN_KRONE,
			WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR,
			WC_Stripe_Currency_Code::POLISH_ZLOTY,
			WC_Stripe_Currency_Code::SWEDISH_KRONA,
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
		];
		$this->supported_countries  = [ 'AU', 'AT', 'BE', 'CA', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'NZ', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US' ];
		$this->label                = __( 'Klarna', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Allow customers to pay over time with Klarna.',
			'woocommerce-gateway-stripe'
		);

		// Klarna has complex rules around currencies and technically allows cross border transactions (like France to Norway). Currency and location rules will be enforced via checkout billing country validation.
		$this->accept_only_domestic_payment = false;
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
			case WC_Stripe_Currency_Code::SWISS_FRANC:
				return [ 'CH' ];
			case WC_Stripe_Currency_Code::CZECH_KORUNA:
				return [ 'CZ' ];
			case WC_Stripe_Currency_Code::DANISH_KRONE:
				return [ 'DK' ];
			case WC_Stripe_Currency_Code::NORWEGIAN_KRONE:
				return [ 'NO' ];
			case WC_Stripe_Currency_Code::POLISH_ZLOTY:
				return [ 'PL' ];
			case WC_Stripe_Currency_Code::SWEDISH_KRONA:
				return [ 'SE' ];
			case WC_Stripe_Currency_Code::POUND_STERLING:
				return [ 'GB' ];
			case WC_Stripe_Currency_Code::EURO:
				// EEA countries that use Euro.
				return [ 'AT', 'BE', 'FI', 'FR', 'GR', 'DE', 'IE', 'IT', 'NL', 'PT', 'ES' ];
		}

		return parent::get_available_billing_countries();
	}

	/**
	 * Returns the currencies this UPE method supports for the Stripe account.
	 *
	 * Klarna has unique requirements for domestic transactions. The customer must be located in the same country as the merchant's Stripe account and the currency must match.
	 * - Stores connected to US account and presenting USD can only transact with customers located in the US.
	 * - Stores connected to US account and presenting non-USD currency can not transact with customers irrespective of their location.
	 *
	 * Additionally, if the merchant is in the EEA, the country they can transact with depends on the presentment currency.
	 * EUR stores can transact with other EUR countries. Stores with currencies like GBP, CHF, etc. can only transact with customers located in those countries.
	 * This creates the following unique situations:
	 *  - Stores presenting EUR, with a Stripe account in any EEA country including Switzerland or the UK can transact with countries where Euros are the standard currency: AT, BE, FI, FR, GR, DE, IE, IT, NL, PT, ES.
	 *  - Stores presenting GBP with a Stripe account in any EEA country including Switzerland or the UK can transact with: GB.
	 *  - Stores presenting NOK with a Stripe account in France, for example, cannot sell into France. They can only sell into Norway.
	 *
	 * @return array Supported currencies.
	 */
	public function get_supported_currencies() {
		$account         = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country = strtoupper( $account['country'] ?? '' );

		// Countries in the EEA + UK and Switzerland can transact across all other EEA countries as long as the currency matches.
		$eea_countries = array_merge( WC_Stripe_Helper::get_european_economic_area_countries(), [ 'CH', 'GB' ] );

		// Countries outside the EEA can only transact with customers in their own currency.
		if ( ! in_array( $account_country, $eea_countries, true ) ) {
			return [ strtoupper( $account['default_currency'] ?? '' ) ];
		}

		// Stripe account in EEA + UK and Switzerland can present the following as store currencies.
		// EEA currencies can only transact with countries where that currency is the standard currency.
		return [
			WC_Stripe_Currency_Code::SWISS_FRANC,
			WC_Stripe_Currency_Code::CZECH_KORUNA,
			WC_Stripe_Currency_Code::DANISH_KRONE,
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::POUND_STERLING,
			WC_Stripe_Currency_Code::NORWEGIAN_KRONE,
			WC_Stripe_Currency_Code::POLISH_ZLOTY,
			WC_Stripe_Currency_Code::SWEDISH_KRONA,
		];
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

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @inheritDoc
	 */
	public function is_available() {
		// Klarna is only available if the official Klarna plugin is not active.
		if ( WC_Stripe_Helper::has_gateway_plugin_active( WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA ) ) {
			return false;
		}

		return parent::is_available();
	}
}
