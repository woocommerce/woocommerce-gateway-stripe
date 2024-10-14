<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WeChat Pay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Wechat_Pay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::WECHAT_PAY;

	/**
	 * Constructor for WeChat Pay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'WeChat Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_countries  = [ 'AT', 'AU', 'BE', 'CA', 'CH', 'DE', 'DK', 'ES', 'FI', 'FR', 'HK', 'IE', 'IT', 'JP', 'LU', 'NL', 'NO', 'PT', 'SE', 'SG', 'GB', 'US' ];
		$this->supported_currencies = [
			WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR,
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR,
			WC_Stripe_Currency_Code::SWISS_FRANC,
			WC_Stripe_Currency_Code::CHINESE_YUAN,
			WC_Stripe_Currency_Code::DANISH_KRONE,
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::POUND_STERLING,
			WC_Stripe_Currency_Code::HONG_KONG_DOLLAR,
			WC_Stripe_Currency_Code::JAPANESE_YEN,
			WC_Stripe_Currency_Code::NORWEGIAN_KRONE,
			WC_Stripe_Currency_Code::SWEDISH_KRONA,
			WC_Stripe_Currency_Code::SINGAPORE_DOLLAR,
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
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
				$currency = [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'CA':
				$currency = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'CH':
				$currency = [ WC_Stripe_Currency_Code::SWISS_FRANC, WC_Stripe_Currency_Code::CHINESE_YUAN, WC_Stripe_Currency_Code::EURO ];
				break;
			case 'DK':
				$currency = [ WC_Stripe_Currency_Code::DANISH_KRONE, WC_Stripe_Currency_Code::CHINESE_YUAN, WC_Stripe_Currency_Code::EURO ];
				break;
			case 'HK':
				$currency = [ WC_Stripe_Currency_Code::HONG_KONG_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'JP':
				$currency = [ WC_Stripe_Currency_Code::JAPANESE_YEN, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'NO':
				$currency = [ WC_Stripe_Currency_Code::NORWEGIAN_KRONE, WC_Stripe_Currency_Code::CHINESE_YUAN, WC_Stripe_Currency_Code::EURO ];
				break;
			case 'SE':
				$currency = [ WC_Stripe_Currency_Code::SWEDISH_KRONA, WC_Stripe_Currency_Code::CHINESE_YUAN, WC_Stripe_Currency_Code::EURO ];
				break;
			case 'SG':
				$currency = [ WC_Stripe_Currency_Code::SINGAPORE_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'GB':
				$currency = [ WC_Stripe_Currency_Code::POUND_STERLING, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case 'US':
				$currency = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			default:
				$currency = [ WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		$euro_supported_countries = [ 'AT', 'BE', 'FI', 'FR', 'DE', 'IE', 'IT', 'LU', 'NL', 'PT', 'ES' ];
		if ( in_array( $country, $euro_supported_countries, true ) ) {
			$currency = [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		return $currency;
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * WeChat Pay is available for the following countries: AT, AU, BE, CA, CH, DE, DK, ES, FI, FR, HK, IE, IT, JP, LU, NL, NO, PT, SE, SG, UK, US.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}
}
