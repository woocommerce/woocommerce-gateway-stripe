<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bancontact Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Bancontact extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::BANCONTACT;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Bancontact::class;

	/**
	 * Constructor for Bancontact payment method
	 */
	public function __construct() {
		parent::__construct();
		$is_sepa_tokens_for_bancontact_enabled    = $this->is_sepa_tokens_for_bancontact_enabled();
		$this->stripe_id                          = self::STRIPE_ID;
		$this->title                              = 'Bancontact';
		$this->is_reusable                        = $is_sepa_tokens_for_bancontact_enabled;
		$this->supported_currencies               = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                              = __( 'Bancontact', 'woocommerce-gateway-stripe' );
		$this->description                        = __(
			'Bancontact is the most popular online payment method in Belgium, with over 15 million cards in circulation.',
			'woocommerce-gateway-stripe'
		);
		if ( $is_sepa_tokens_for_bancontact_enabled ) {
			$this->supports[] = 'subscriptions';
			$this->supports[] = 'tokenization';
			$this->supports[] = 'multiple_subscriptions';
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
