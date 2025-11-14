<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sofort Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sofort extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	const STRIPE_ID = WC_Stripe_Payment_Methods::SOFORT;

	/**
	 * Constructor for Sofort payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Sofort', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                = __( 'Sofort', 'woocommerce-gateway-stripe' );
		$this->supports[]           = 'tokenization';
		$this->description          = __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-gateway-stripe'
		);

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();

		// Init subscription so it can process subscription payments.
		$this->maybe_init_subscriptions();
	}
}
