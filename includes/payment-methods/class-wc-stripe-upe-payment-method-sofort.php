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
		$this->title                = __( 'Sofort', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'Sofort', 'woocommerce-gateway-stripe' );
		$this->supports[]           = 'subscriptions';
		$this->supports[]           = 'tokenization';
		$this->supports[]           = 'multiple_subscriptions';
		$this->description          = __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-gateway-stripe'
		);

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}
}
