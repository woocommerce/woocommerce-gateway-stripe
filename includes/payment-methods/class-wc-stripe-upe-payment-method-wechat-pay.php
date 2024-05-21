<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WeChat Pay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Wechat_Pay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'wechat_pay';

	/**
	 * Constructor for WeChat Pay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'WeChat Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [
			'CNY',
			'AUD',
			'CAD',
			'EUR',
			'GBP',
			'HKD',
			'JPY',
			'SGD',
			'USD',
			'DKK',
			'NOK',
			'SEK',
			'CHF',
		];
		$this->label                = __( 'WeChat Pay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'WeChat Pay is a popular mobile payment and digital wallet service by WeChat in China.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns the currencies this UPE method supports for the Stripe account.
	 * Documentation: https://docs.stripe.com/payments/wechat-pay#supported-currencies.
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
			case 'SG':
				$currency = [ 'SGD', 'CNY' ];
				break;
			case 'US':
				$currency = [ 'USD', 'CNY' ];
				break;
			case 'DK':
				$currency = [ 'DKK', 'CNY', 'EUR' ];
				break;
			case 'NO':
				$currency = [ 'NOK', 'CNY', 'EUR' ];
				break;
			case 'SE':
				$currency = [ 'SEK', 'CNY', 'EUR' ];
				break;
			case 'CH':
				$currency = [ 'CHF', 'CNY', 'EUR' ];
				break;
			default:
				$currency = [ 'CNY' ];
		}

		$euro_supported_countries = [ 'AT', 'BE', 'FI', 'FR', 'DE', 'IE', 'IT', 'LU', 'NL', 'PT', 'ES' ];
		if ( in_array( $country, $euro_supported_countries, true ) ) {
			$currency = [ 'EUR', 'CNY' ];
		}

		return $currency;
	}
}
