<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEPA Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sepa extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'sepa_debit';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Sepa::class;

	/**
	 * Constructor for SEPA payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @return string
	 */
	public function get_testing_instructions() {
		return sprintf(
			/* translators: 1) HTML strong open tag 2) HTML strong closing tag 3) HTML anchor open tag 2) HTML anchor closing tag */
			esc_html__( '<strong>Test mode:</strong> use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a>here</a>.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>',
			'<a href="https://stripe.com/docs/testing?payment-method=sepa-direct-debit" target="_blank">',
			'</a>'
		);
	}
}
