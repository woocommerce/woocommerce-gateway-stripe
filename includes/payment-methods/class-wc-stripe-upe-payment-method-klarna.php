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
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}
}
