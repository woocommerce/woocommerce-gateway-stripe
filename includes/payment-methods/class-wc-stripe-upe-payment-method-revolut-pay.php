<?php

use Automattic\WooCommerce\Enums\PaymentGatewayFeature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Revolut Pay Payment Method class extending UPE base class.
 *
 * Revolut Pay is a redirect-based wallet that supports recurring payments via Stripe mandates.
 */
class WC_Stripe_UPE_Payment_Method_Revolut_Pay extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

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
		$this->is_reusable          = true;
		$this->supports[]           = PaymentGatewayFeature::TOKENIZATION;
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

		// Init subscription so it can process subscription payments.
		$this->maybe_init_subscriptions();
	}

	/**
	 * Revolut Pay supports separate authorization and capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}

	/**
	 * Returns the currencies this method supports for the Stripe account.
	 *
	 * Revolut Pay restricts charge currencies by the merchant's account country: UK accounts
	 * may charge GBP, while EEA accounts may charge EUR, RON, HUF, PLN, or DKK (never GBP).
	 * https://docs.stripe.com/payments/revolut-pay#supported-currencies
	 *
	 * @return string[]
	 */
	public function get_supported_currencies() {
		$account_country = WC_Stripe::get_instance()->account->get_account_country();

		if ( WC_Stripe_Country_Code::UNITED_KINGDOM === $account_country ) {
			return [ WC_Stripe_Currency_Code::POUND_STERLING ];
		}

		// Any other account country must be a supported EEA country to charge these currencies.
		if ( in_array( $account_country, static::SUPPORTED_ACCOUNT_COUNTRIES, true ) ) {
			return [
				WC_Stripe_Currency_Code::EURO,
				WC_Stripe_Currency_Code::ROMANIAN_LEU,
				WC_Stripe_Currency_Code::HUNGARIAN_FORINT,
				WC_Stripe_Currency_Code::POLISH_ZLOTY,
				WC_Stripe_Currency_Code::DANISH_KRONE,
			];
		}

		return [];
	}

	/**
	 * Creates a Revolut Pay payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 * @return WC_Payment_Token The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Stripe_Revolut_Pay_Payment_Token();

		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->save();

		return $token;
	}
}
