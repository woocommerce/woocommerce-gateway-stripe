<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Revolut Pay Payment Method class extending UPE base class.
 *
 * Revolut Pay is a redirect-based wallet. Stripe supports recurring mandates for it,
 * but this integration ships one-time payments only ($is_reusable = false).
 */
class WC_Stripe_UPE_Payment_Method_Revolut_Pay extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::REVOLUT_PAY;

	/**
	 * Stripe account countries that may enable Revolut Pay.
	 * https://docs.stripe.com/payments/revolut-pay
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_ACCOUNT_COUNTRIES = [
		WC_Stripe_Country_Code::AUSTRIA,
		WC_Stripe_Country_Code::BELGIUM,
		WC_Stripe_Country_Code::BULGARIA,
		WC_Stripe_Country_Code::CYPRUS,
		WC_Stripe_Country_Code::CZECH_REPUBLIC,
		WC_Stripe_Country_Code::GERMANY,
		WC_Stripe_Country_Code::DENMARK,
		WC_Stripe_Country_Code::ESTONIA,
		WC_Stripe_Country_Code::SPAIN,
		WC_Stripe_Country_Code::FINLAND,
		WC_Stripe_Country_Code::FRANCE,
		WC_Stripe_Country_Code::UNITED_KINGDOM,
		WC_Stripe_Country_Code::GREECE,
		WC_Stripe_Country_Code::CROATIA,
		WC_Stripe_Country_Code::HUNGARY,
		WC_Stripe_Country_Code::IRELAND,
		WC_Stripe_Country_Code::ITALY,
		WC_Stripe_Country_Code::LIECHTENSTEIN,
		WC_Stripe_Country_Code::LITHUANIA,
		WC_Stripe_Country_Code::LUXEMBOURG,
		WC_Stripe_Country_Code::LATVIA,
		WC_Stripe_Country_Code::MALTA,
		WC_Stripe_Country_Code::NETHERLANDS,
		WC_Stripe_Country_Code::NORWAY,
		WC_Stripe_Country_Code::POLAND,
		WC_Stripe_Country_Code::PORTUGAL,
		WC_Stripe_Country_Code::ROMANIA,
		WC_Stripe_Country_Code::SWEDEN,
		WC_Stripe_Country_Code::SLOVENIA,
		WC_Stripe_Country_Code::SLOVAKIA,
	];

	/**
	 * Buyer eligibility is gated by having a Revolut account or card, not by billing country.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [];

	/**
	 * Constructor for Revolut Pay payment method.
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Revolut Pay', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::POUND_STERLING,
			WC_Stripe_Currency_Code::ROMANIAN_LEU,
			WC_Stripe_Currency_Code::HUNGARIAN_FORINT,
			WC_Stripe_Currency_Code::POLISH_ZLOTY,
			WC_Stripe_Currency_Code::DANISH_KRONE,
		];
		$this->label                = __( 'Revolut Pay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Let customers pay with their Revolut account or any major card through Revolut Pay.',
			'woocommerce-gateway-stripe'
		);
	}
}
