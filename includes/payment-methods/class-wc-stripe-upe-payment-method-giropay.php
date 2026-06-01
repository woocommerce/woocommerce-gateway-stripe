<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The giropay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Giropay extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::GIROPAY;

	/**
	 * Stripe account countries that may enable giropay. Last-known data; giropay was
	 * deprecated by Stripe on 2024-06-30. is_enabled_at_checkout() returns false.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_ACCOUNT_COUNTRIES = [];

	/**
	 * Shopper billing countries permitted to use giropay (last-known data).
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [ WC_Stripe_Country_Code::GERMANY ];

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
