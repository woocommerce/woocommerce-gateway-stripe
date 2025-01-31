<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The iDEAL Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Ideal extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::IDEAL;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Ideal::class;

	/**
	 * Constructor for iDEAL payment method
	 */
	public function __construct() {
		parent::__construct();
		$is_sepa_tokens_for_other_methods_enabled = $this->is_sepa_tokens_for_other_methods_enabled();
		$this->stripe_id                          = self::STRIPE_ID;
		$this->title                              = __( 'iDEAL', 'woocommerce-gateway-stripe' );
		$this->is_reusable                        = $is_sepa_tokens_for_other_methods_enabled;
		$this->supported_currencies               = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                              = __( 'iDEAL', 'woocommerce-gateway-stripe' );
		$this->description                        = __(
			'iDEAL is a Netherlands-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		);
		if ( $is_sepa_tokens_for_other_methods_enabled ) {
			$this->supports[] = 'subscriptions';
			$this->supports[] = 'multiple_subscriptions';
			$this->supports[] = 'tokenization';
		}

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;
	}
}
