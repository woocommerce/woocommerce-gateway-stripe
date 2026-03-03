<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The alipay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Alipay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::ALIPAY;

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
			case WC_Stripe_Country_Code::AU:
				$currency = [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::CA:
				$currency = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::GB:
				$currency = [ WC_Stripe_Currency_Code::POUND_STERLING, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::HK:
				$currency = [ WC_Stripe_Currency_Code::HONG_KONG_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::JP:
				$currency = [ WC_Stripe_Currency_Code::JAPANESE_YEN, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::MY:
				$currency = [ WC_Stripe_Currency_Code::MALAYSIAN_RINGGIT, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::NZ:
				$currency = [ WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::SG:
				$currency = [ WC_Stripe_Currency_Code::SINGAPORE_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::US:
				$currency = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			default:
				$currency = [ WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		$euro_supported_countries = [ WC_Stripe_Country_Code::AT, WC_Stripe_Country_Code::BE, WC_Stripe_Country_Code::BG, WC_Stripe_Country_Code::CY, WC_Stripe_Country_Code::CZ, WC_Stripe_Country_Code::DK, WC_Stripe_Country_Code::EE, WC_Stripe_Country_Code::FI, WC_Stripe_Country_Code::FR, WC_Stripe_Country_Code::DE, WC_Stripe_Country_Code::GR, WC_Stripe_Country_Code::IE, WC_Stripe_Country_Code::IT, WC_Stripe_Country_Code::LV, WC_Stripe_Country_Code::LT, WC_Stripe_Country_Code::LU, WC_Stripe_Country_Code::MT, WC_Stripe_Country_Code::NL, WC_Stripe_Country_Code::NO, WC_Stripe_Country_Code::PT, WC_Stripe_Country_Code::RO, WC_Stripe_Country_Code::SK, WC_Stripe_Country_Code::SI, WC_Stripe_Country_Code::ES, WC_Stripe_Country_Code::SE, WC_Stripe_Country_Code::CH ];
		if ( in_array( $country, $euro_supported_countries, true ) ) {
			$currency = [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		return $currency;
	}
}
