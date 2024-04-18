<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Affirm Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Affirm extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'affirm';

	/**
	 * Constructor for Affirm payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ 'CAD', 'USD' ];
		$this->supported_countries          = [ WC_Stripe_Country_Code::UNITED_STATES, WC_Stripe_Country_Code::CANADA ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Affirm.',
			'woocommerce-gateway-stripe'
		);
		$this->limits_per_currency          = [
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR      => [
				WC_Stripe_Country_Code::CANADA => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents CAD 50 - 30,000 CAD.
			],
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR => [
				WC_Stripe_Country_Code::UNITED_STATES => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents USD 50 - 30,000 USD.
			],
		];
		$this->countries                    = [ WC_Stripe_Country_Code::UNITED_STATES, WC_Stripe_Country_Code::CANADA ];
	}
}
