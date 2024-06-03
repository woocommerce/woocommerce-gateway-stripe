<?php
/**
 * Class UPE Cash App Pay Method
 *
 * @package WooCommerce_Stripe\Payment_Methods
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Cash App Pay Payment Method class extending UPE base class.
 */
class WC_Stripe_UPE_Payment_Method_Cash_App_Pay extends WC_Stripe_UPE_Payment_Method {

	/**
	 * The Stripe ID for the payment method.
	 */
	const STRIPE_ID = 'cashapp';

	/**
	 * Constructor for Cash App payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ 'USD' ];
		$this->supported_countries          = [ 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Cash App is a popular consumer app in the US that allows customers to bank, invest, send, and receive money using their digital wallet.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'order_received_text_for_wallet_failure' ], 10, 2 );
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Cash App Pay is only available to merchants in the United States.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}
}
