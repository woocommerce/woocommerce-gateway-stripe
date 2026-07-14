<?php

use Automattic\WooCommerce\Enums\PaymentGatewayFeature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles ACH Direct Debit as a UPE Payment Method.
 *
 * @extends WC_Stripe_UPE_Payment_Method
 */
class WC_Stripe_UPE_Payment_Method_ACH extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * Stripe's internal identifier for ACH Direct Debit.
	 */
	public const STRIPE_ID = WC_Stripe_Payment_Methods::ACH;

	/**
	 * Stripe account countries that may enable ACH Direct Debit. ACH itself only debits US
	 * bank accounts, but Stripe permits the connected merchant account to live in many countries.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_ACCOUNT_COUNTRIES = [
		WC_Stripe_Country_Code::AUSTRIA,
		WC_Stripe_Country_Code::BELGIUM,
		WC_Stripe_Country_Code::BULGARIA,
		WC_Stripe_Country_Code::SWITZERLAND,
		WC_Stripe_Country_Code::CYPRUS,
		WC_Stripe_Country_Code::CZECH_REPUBLIC,
		WC_Stripe_Country_Code::GERMANY,
		WC_Stripe_Country_Code::DENMARK,
		WC_Stripe_Country_Code::ESTONIA,
		WC_Stripe_Country_Code::SPAIN,
		WC_Stripe_Country_Code::FINLAND,
		WC_Stripe_Country_Code::FRANCE,
		WC_Stripe_Country_Code::UNITED_KINGDOM,
		WC_Stripe_Country_Code::GIBRALTAR,
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
		WC_Stripe_Country_Code::UNITED_STATES,
	];

	/**
	 * Shopper billing countries permitted to use ACH Direct Debit (US bank accounts only).
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [ WC_Stripe_Country_Code::UNITED_STATES ];

	/**
	 * Constructor for ACH Direct Debit payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->label                = __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __( 'Pay directly from your US bank account via ACH.', 'woocommerce-gateway-stripe' );
		$this->supported_currencies = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->supports[]           = PaymentGatewayFeature::TOKENIZATION;

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Creates an ACH payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token_ACH|null The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		if ( ! isset( $payment_method->id ) || ! isset( $payment_method->us_bank_account ) ) {
			return null;
		}

		$payment_token = new WC_Payment_Token_ACH();
		$payment_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_token( $payment_method->id );
		$payment_token->set_last4( $payment_method->us_bank_account->last4 );
		$payment_token->set_bank_name( $payment_method->us_bank_account->bank_name );
		$payment_token->set_account_type( $payment_method->us_bank_account->account_type );
		$payment_token->set_fingerprint( $payment_method->us_bank_account->fingerprint );
		$payment_token->save();

		return $payment_token;
	}
}
