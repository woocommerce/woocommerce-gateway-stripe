<?php

use Automattic\WooCommerce\Enums\PaymentGatewayFeature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEPA Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Sepa extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	public const STRIPE_ID = WC_Stripe_Payment_Methods::SEPA_DEBIT;

	/**
	 * Stripe account countries that may not enable SEPA Direct Debit.
	 *
	 * @var string[]
	 */
	protected const UNSUPPORTED_ACCOUNT_COUNTRIES = [
		WC_Stripe_Country_Code::BRAZIL,
		WC_Stripe_Country_Code::MALAYSIA,
		WC_Stripe_Country_Code::THAILAND,
		WC_Stripe_Country_Code::UNITED_ARAB_EMIRATES,
	];

	/**
	 * Shopper billing countries permitted to use SEPA Direct Debit — the SEPA zone (countries whose banks
	 * can issue a SEPA-compliant IBAN). Stripe's docs say "Europe / SEPA zone" without enumerating.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [
		WC_Stripe_Country_Code::ANDORRA,
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
		WC_Stripe_Country_Code::ICELAND,
		WC_Stripe_Country_Code::ITALY,
		WC_Stripe_Country_Code::LIECHTENSTEIN,
		WC_Stripe_Country_Code::LITHUANIA,
		WC_Stripe_Country_Code::LUXEMBOURG,
		WC_Stripe_Country_Code::LATVIA,
		WC_Stripe_Country_Code::MONACO,
		WC_Stripe_Country_Code::MALTA,
		WC_Stripe_Country_Code::NETHERLANDS,
		WC_Stripe_Country_Code::NORWAY,
		WC_Stripe_Country_Code::POLAND,
		WC_Stripe_Country_Code::PORTUGAL,
		WC_Stripe_Country_Code::ROMANIA,
		WC_Stripe_Country_Code::SWEDEN,
		WC_Stripe_Country_Code::SLOVENIA,
		WC_Stripe_Country_Code::SLOVAKIA,
		WC_Stripe_Country_Code::SAN_MARINO,
		WC_Stripe_Country_Code::VATICAN_CITY,
	];

	/**
	 * Constructor for SEPA payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                = __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		);
		$this->supports[]           = PaymentGatewayFeature::TOKENIZATION;

		// SEPA Direct Debit is the tokenization method for this method as well as Bancontact and iDEAL. Init subscription so it can process subscription payments.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param bool $show_optimized_checkout_instruction Deprecated. Whether to show optimized checkout instructions.
	 * @param bool $include_test_mode_label Whether to include the "Test mode:" label prefix. Pass false for
	 *                                      Blocks checkout, which already displays a Test Mode badge.
	 * @return string
	 */
	public function get_testing_instructions( bool $show_optimized_checkout_instruction = false, bool $include_test_mode_label = true ) {
		if ( false !== $show_optimized_checkout_instruction ) {
			_deprecated_argument(
				__FUNCTION__,
				'9.9.0'
			);
		}

		if ( $include_test_mode_label ) {
			return sprintf(
				/* translators: 1) HTML strong open tag 2) HTML strong closing tag 3) number open tag 4) number closing tag 5) HTML anchor open tag 6) HTML anchor closing tag */
				esc_html__( '%1$sTest mode:%2$s use account %3$sAT611904300234573201%4$s. %5$sMore test methods%6$s.', 'woocommerce-gateway-stripe' ),
				'<strong>',
				'</strong>',
				'<number>',
				'</number>',
				'<a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">',
				'</a>'
			);
		}

		return sprintf(
			/* translators: 1) number open tag 2) number closing tag 3) HTML anchor open tag 4) HTML anchor closing tag */
			esc_html__( 'Use account %1$sAT611904300234573201%2$s. %3$sMore test methods%4$s.', 'woocommerce-gateway-stripe' ),
			'<number>',
			'</number>',
			'<a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">',
			'</a>'
		);
	}

	/**
	 * Gets the payment method's icon.
	 *
	 * @return string The icon HTML.
	 */
	public function get_icon() {
		$icons = WC_Stripe::get_instance()->get_main_stripe_gateway()->payment_icons();
		return isset( $icons['sepa'] ) ? apply_filters( 'woocommerce_gateway_icon', $icons['sepa'], $this->id ) : parent::get_icon();
	}
}
