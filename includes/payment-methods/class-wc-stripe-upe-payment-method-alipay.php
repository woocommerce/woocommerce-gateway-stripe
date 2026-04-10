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
			case WC_Stripe_Country_Code::AUSTRALIA:
				$currency = [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::CANADA:
				$currency = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::UNITED_KINGDOM:
				$currency = [ WC_Stripe_Currency_Code::POUND_STERLING, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::HONG_KONG:
				$currency = [ WC_Stripe_Currency_Code::HONG_KONG_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::JAPAN:
				$currency = [ WC_Stripe_Currency_Code::JAPANESE_YEN, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::MALAYSIA:
				$currency = [ WC_Stripe_Currency_Code::MALAYSIAN_RINGGIT, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::NEW_ZEALAND:
				$currency = [ WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::SINGAPORE:
				$currency = [ WC_Stripe_Currency_Code::SINGAPORE_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			case WC_Stripe_Country_Code::UNITED_STATES:
				$currency = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ];
				break;
			default:
				$currency = [ WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		$euro_supported_countries = [
			WC_Stripe_Country_Code::AUSTRIA,
			WC_Stripe_Country_Code::BELGIUM,
			WC_Stripe_Country_Code::BULGARIA,
			WC_Stripe_Country_Code::CYPRUS,
			WC_Stripe_Country_Code::CZECH_REPUBLIC,
			WC_Stripe_Country_Code::DENMARK,
			WC_Stripe_Country_Code::ESTONIA,
			WC_Stripe_Country_Code::FINLAND,
			WC_Stripe_Country_Code::FRANCE,
			WC_Stripe_Country_Code::GERMANY,
			WC_Stripe_Country_Code::GREECE,
			WC_Stripe_Country_Code::IRELAND,
			WC_Stripe_Country_Code::ITALY,
			WC_Stripe_Country_Code::LATVIA,
			WC_Stripe_Country_Code::LITHUANIA,
			WC_Stripe_Country_Code::LUXEMBOURG,
			WC_Stripe_Country_Code::MALTA,
			WC_Stripe_Country_Code::NETHERLANDS,
			WC_Stripe_Country_Code::NORWAY,
			WC_Stripe_Country_Code::PORTUGAL,
			WC_Stripe_Country_Code::ROMANIA,
			WC_Stripe_Country_Code::SLOVAKIA,
			WC_Stripe_Country_Code::SLOVENIA,
			WC_Stripe_Country_Code::SPAIN,
			WC_Stripe_Country_Code::SWEDEN,
			WC_Stripe_Country_Code::SWITZERLAND,
		];
		if ( in_array( $country, $euro_supported_countries, true ) ) {
			$currency = [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::CHINESE_YUAN ];
		}

		return $currency;
	}
}
