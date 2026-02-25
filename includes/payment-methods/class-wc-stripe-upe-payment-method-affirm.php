<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Affirm Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Affirm extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::AFFIRM;

	/**
	 * Constructor for Affirm payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->supported_countries          = [ 'US', 'CA' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Affirm.',
			'woocommerce-gateway-stripe'
		);
		$this->limits_per_currency          = [
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR      => [
				'CA' => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents CAD 50 - 30,000 CAD.
			],
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR => [
				'US' => [
					'min' => 3500,
					'max' => 3000000,
				], // Represents USD 35 - 30,000 USD.
			],
		];
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * @return bool
	 *
	 * @deprecated 10.5.0 Use WC_Stripe_UPE_Payment_Method::is_available_for_account_country() instead.
	 */
	public function is_available_for_account_country() {
		wc_deprecated_function( __METHOD__, '10.5.0', 'WC_Stripe_UPE_Payment_Method::is_available_for_account_country' );
		return parent::is_available_for_account_country();
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
		// Affirm is only available if the official Affirm plugin is not active.
		if ( WC_Stripe_Helper::has_gateway_plugin_active( WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM ) ) {
			return false;
		}

		return parent::is_available();
	}
}
