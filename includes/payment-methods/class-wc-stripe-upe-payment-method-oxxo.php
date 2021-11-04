<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OXXO Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Oxxo extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'oxxo';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Oxxo::class;

	/**
	 * Constructor for OXXO payment method
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = 'Pay with OXXO';
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'BRL' ];
		$this->label                = __( 'OXXO', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'OXXO is a voucher payment widely used in Mexico',
			'woocommerce-gateway-stripe'
		);
	}
}
