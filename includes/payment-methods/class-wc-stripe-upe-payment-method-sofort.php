<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sofort Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sofort extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'sofort';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Sofort::class;

	/**
	 * Constructor for Sofort payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with Sofort', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'Sofort', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-gateway-stripe'
		);
	}
}
