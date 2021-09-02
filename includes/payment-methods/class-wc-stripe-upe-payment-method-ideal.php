<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ideal Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Ideal extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'ideal';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Ideal::class;

	/**
	 * Constructor for iDEAL payment method
	 */
	public function __construct() {
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = 'Pay with iDEAL';
		$this->is_reusable          = false; // TODO: should be true, once SEPA is implemented as a payment method
		$this->supported_currencies = [ 'EUR' ];
	}
}
