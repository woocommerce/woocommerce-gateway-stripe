<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Link extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'link';

	/**
	 * Constructor for Link payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with Link', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'USD' ];
		$this->label                = __( 'Stripe Link', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Link is a payment method that allows customers to save payment information  and use the payment details
			for further payments.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Return if Stripe Link is enabled
	 *
	 * @return bool
	 */
	public static function is_link_enabled() {
		// Assume Link is disabled if UPE is disabled.
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return false;
		}

		return in_array(
			self::STRIPE_ID,
			woocommerce_gateway_stripe()->get_main_stripe_gateway()->get_upe_enabled_payment_method_ids(),
			true
		);
	}
}
