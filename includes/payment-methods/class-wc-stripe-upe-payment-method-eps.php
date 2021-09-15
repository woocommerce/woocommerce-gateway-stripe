<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EPS Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Eps extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'eps';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Eps::class;

	/**
	 * Constructor for card payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = 'Pay with EPS';
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'EUR' ];
	}
}
