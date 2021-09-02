<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Giropay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Giropay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'giropay';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Giropay::class;

	/**
	 * Constructor for giropay payment method
	 */
	public function __construct() {
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = 'Pay with giropay';
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'EUR' ];
	}
}
