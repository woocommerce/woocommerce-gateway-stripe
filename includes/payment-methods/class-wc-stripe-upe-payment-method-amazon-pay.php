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
	 * Return if Amazon Pay is enabled.
	 *
	 * @return bool
	 */
	public static function is_amazon_pay_enabled() {
		// Amazon Pay is disabled if feature flag is disabled.
		if ( ! WC_Stripe_Feature_Flags::is_amazon_pay_available() ) {
			return false;
		}

		// Amazon Pay is disabled if UPE is disabled.
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return false;
		}

		$upe_enabled_method_ids = WC_Stripe_Helper::get_settings( null, 'upe_checkout_experience_accepted_payments' );

		return is_array( $upe_enabled_method_ids ) && in_array( self::STRIPE_ID, $upe_enabled_method_ids, true );
	}

	/**
	 * Returns whether the payment method is available.
	 *
	 * Amazon Pay is rendered as an express checkout method only, for now.
	 * We return false here so that it isn't considered available by WooCommerce
	 * and rendered as a standard payment method at checkout.
	 *
	 * @return bool
	 */
	public function is_available() {
		return false;
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
