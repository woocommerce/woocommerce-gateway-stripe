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
	use WC_Stripe_Subscriptions_Trait;

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
		$this->is_reusable                  = true;
		$this->supported_currencies         = [ 'USD' ];
		$this->supported_countries          = [ 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->supports[]                   = 'tokenization';
		$this->label                        = __( 'Cash App Pay', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Cash App is a popular consumer app in the US that allows customers to bank, invest, send, and receive money using their digital wallet.',
			'woocommerce-gateway-stripe'
		);

		// Cash App Pay supports subscriptions. Init subscription so it can process subscription payments.
		$this->maybe_init_subscriptions();
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
