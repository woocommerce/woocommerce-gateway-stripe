<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Przelewy24 Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_P24 extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::P24;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_P24::class;

	/**
	 * Constructor for Przelewy24 payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Przelewy24', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::POLISH_ZLOTY ];
		$this->label                = __( 'Przelewy24', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Przelewy24 is a Poland-based payment method aggregator that allows customers to complete transactions online using bank transfers and other methods.',
			'woocommerce-gateway-stripe'
		);
	}
}
