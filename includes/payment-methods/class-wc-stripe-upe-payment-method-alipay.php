<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The alipay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Alipay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::ALIPAY;

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
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR,
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR,
			WC_Stripe_Currency_Code::CHINESE_YUAN,
			WC_Stripe_Currency_Code::POUND_STERLING,
			WC_Stripe_Currency_Code::HONG_KONG_DOLLAR,
			WC_Stripe_Currency_Code::JAPANESE_YEN,
			WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR,
			WC_Stripe_Currency_Code::SINGAPORE_DOLLAR,
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			WC_Stripe_Currency_Code::MALAYSIAN_RINGGIT,
		];
		$this->label                = __( 'Alipay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Alipay is a popular wallet in China, operated by Ant Financial Services Group, a financial services provider affiliated with Alibaba.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns the currencies this UPE method supports for the Stripe account.
	 * Documentation: https://docs.stripe.com/payments/alipay#supported-currencies.
	 *
	 * @return array
	 */
	public function get_supported_currencies() {
		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$country             = $cached_account_data['country'] ?? null;

		$currency = [];

		switch ( $country ) {
			case 'AU':
				$currency = [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'CA':
				$currency = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'GB':
				$currency = [ WC_Stripe_Currency_Code::POUND_STERLING, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'HK':
				$currency = [ WC_Stripe_Currency_Code::HONG_KONG_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'JP':
				$currency = [ WC_Stripe_Currency_Code::JAPANESE_YEN, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'MY':
				$currency = [ WC_Stripe_Currency_Code::MALAYSIAN_RINGGIT, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'NZ':
				$currency = [ WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'SG':
				$currency = [ WC_Stripe_Currency_Code::SINGAPORE_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'US':
				$currency = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			default:
				$currency = [ WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		$euro_supported_countries = [ 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'NO', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH' ];
		if ( in_array( $country, $euro_supported_countries, true ) ) {
			$currency = [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		return $currency;
	}
}
