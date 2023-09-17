<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Pay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Google_Pay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'google_pay';

	/**
	 * Constructor for Google Pay payment method
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with Google Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'USD' ];
		$this->label                = __( 'Google Pay (Stripe)', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Test description',
			'woocommerce-gateway-stripe'
		);
	}

}
