<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEPA Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sepa extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'sepa_debit';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Sepa::class;

	/**
	 * Constructor for SEPA payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = 'SEPA';
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
	}
}
