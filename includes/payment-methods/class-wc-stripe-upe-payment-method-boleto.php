<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boleto Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Boleto extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'boleto';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Boleto::class;

	/**
	 * Constructor for Boleto payment method
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->can_refund           = false;
		$this->title                = 'Pay with Boleto';
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'BRL' ];
		$this->supported_countries  = [ 'BR' ];
		$this->label                = __( 'Boleto', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Boleto is an official payment method in Brazil. Customers receive a voucher that can be paid at authorized agencies or banks, ATMs, or online bank portals.',
			'woocommerce-gateway-stripe'
		);
	}
}
