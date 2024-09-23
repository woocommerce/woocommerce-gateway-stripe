<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The giropay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Giropay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::GIROPAY;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Giropay::class;

	/**
	 * Constructor for giropay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'giropay', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                = __( 'giropay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @param int|null    $order_id
	 * @param string|null $account_domestic_currency The account's default currency.
	 * @return bool
	 */
	public function is_enabled_at_checkout( $order_id = null, $account_domestic_currency = null ) {
		return false;
	}
}
