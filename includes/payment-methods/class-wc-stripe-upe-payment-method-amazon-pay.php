<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amazon Pay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Amazon_Pay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::AMAZON_PAY;

	/**
	 * Constructor for Amazon Pay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Amazon Pay', 'woocommerce-gateway-stripe' );
		$this->supported_currencies = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->is_reusable          = true;
		$this->label                = __( 'Amazon Pay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Amazon Pay is a payment method that allows customers to pay with their Amazon account.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @return bool
	 */
	public function requires_automatic_capture() {
		// Amazon Pay supports manual capture.
		return false;
	}
}
