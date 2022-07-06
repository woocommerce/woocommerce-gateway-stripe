<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Link extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'link';

	/**
	 * Constructor for Link payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with Link', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'USD' ];
		$this->label                = __( 'Stripe Link', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Link is a payment method that allows customers to save payment information  and use the payment details
			for further payments.',
			'woocommerce-gateway-stripe'
		);
	}
}
