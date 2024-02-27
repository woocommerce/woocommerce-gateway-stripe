<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The alipay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Alipay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'alipay';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Alipay::class;

	/**
	 * Constructor for alipay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Alipay', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [
			'EUR',
			'AUD',
			'CAD',
			'CNY',
			'GBP',
			'HKD',
			'JPY',
			'NZD',
			'SGD',
			'USD',
			'MYR',
		];
		$this->label                = __( 'Alipay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Alipay is a popular wallet in China, operated by Ant Financial Services Group, a financial services provider affiliated with Alibaba.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns the currencies this UPE method supports for the Stripe account.
	 * Documentation: https://stripe.com/docs/payments/alipay#supported-currencies.
	 *
	 * @return array
	 */
	public function get_supported_currencies() {
		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$country             = $cached_account_data['country'] ?? null;

		$currency = [];

		switch ( $country ) {
			case 'AU':
				$currency = [ 'AUD', 'CNY' ];
				break;
			case 'CA':
				$currency = [ 'CAD', 'CNY' ];
				break;
			case 'UK':
				$currency = [ 'GBP', 'CNY' ];
				break;
			case 'HK':
				$currency = [ 'HKD', 'CNY' ];
				break;
			case 'JP':
				$currency = [ 'JPY', 'CNY' ];
				break;
			case 'MY':
				$currency = [ 'MYR', 'CNY' ];
				break;
			case 'NZ':
				$currency = [ 'NZD', 'CNY' ];
				break;
			case 'SG':
				$currency = [ 'SGD', 'CNY' ];
				break;
			case 'US':
				$currency = [ 'USD', 'CNY' ];
				break;
			default:
				$currency = [ 'CNY' ];
		}

		$euro_supported_countries = [ 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'NO', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH' ];
		if ( in_array( $country, $euro_supported_countries, true ) ) {
			$currency = [ 'EUR', 'CNY' ];
		}

		return $currency;
	}
}
