<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Afterpay / Clearpay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'afterpay_clearpay';

	/**
	 * Constructor for afterpay / clearpay payment method
	 */
	public function __construct() {
		parent::__construct();

		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country     = $cached_account_data['country'] ?? null;
		$method_name         = 'GB' === $account_country ? __( 'Clearpay', 'woocommerce-gateway-stripe' ) : __( 'Afterpay', 'woocommerce-gateway-stripe' );

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = $method_name;
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ 'USD', 'CAD', 'GBP', 'AUD', 'NZD' ];
		$this->supported_countries          = [ 'AU', 'CA', 'GB', 'NZ', 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = $method_name;
		$this->description                  = 'GB' === $account_country ? __(
			'Allow customers to pay over time with Clearpay.',
			'woocommerce-gateway-stripe'
		) : __(
			'Allow customers to pay over time with Afterpay.',
			'woocommerce-gateway-stripe'
		);
	}
}
