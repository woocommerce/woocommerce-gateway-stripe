<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bancontact Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Bancontact extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'bancontact';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Bancontact::class;

	/**
	 * Constructor for card payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = 'Pay with Bancontact';
		$this->is_reusable          = true; // TODO: is it?
		$this->supported_currencies = [ 'EUR' ];
	}
}
