<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEPA Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sepa extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	const STRIPE_ID = WC_Stripe_Payment_Methods::SEPA_DEBIT;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Sepa::class;

	/**
	 * Constructor for SEPA payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                = __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		);
		$this->supports[] = 'tokenization';

		// SEPA Direct Debit is the tokenization method for this method as well as Bancontact and iDEAL. Init subscription so it can process subscription payments.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
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
			esc_html__( '%1$sTest mode:%2$s use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed %3$shere%4$s.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>',
			'<a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">',
			'</a>'
		);
	}

	/**
	 * Gets the payment method's icon.
	 *
	 * @return string The icon HTML.
	 */
	public function get_icon() {
		$icons = WC_Stripe::get_instance()->get_main_stripe_gateway()->payment_icons();
		return isset( $icons['sepa'] ) ? apply_filters( 'woocommerce_gateway_icon', $icons['sepa'], $this->id ) : parent::get_icon();
	}
}
