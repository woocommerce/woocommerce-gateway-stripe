<?php

use Automattic\WooCommerce\Enums\PaymentGatewayFeature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The iDEAL Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Ideal extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	public const STRIPE_ID = WC_Stripe_Payment_Methods::IDEAL;

	/**
	 * Stripe account countries that may not enable iDEAL | Wero.
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
	 * Shopper billing countries permitted to use iDEAL | Wero.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [ WC_Stripe_Country_Code::NETHERLANDS ];

	/**
	 * Constructor for iDEAL payment method
	 */
	public function __construct() {
		parent::__construct();
		$is_sepa_tokens_for_ideal_enabled = $this->is_sepa_tokens_for_ideal_enabled();
		$this->stripe_id                  = self::STRIPE_ID;
		$this->title                      = 'iDEAL | Wero';
		$this->is_reusable                = $is_sepa_tokens_for_ideal_enabled;
		$this->supported_currencies       = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                      = 'iDEAL | Wero';
		$this->description                = __(
			'iDEAL | Wero is a Netherlands-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		);
		if ( $is_sepa_tokens_for_ideal_enabled ) {
			$this->supports[] = PaymentGatewayFeature::TOKENIZATION;

			// Check if subscriptions are enabled and add support for them.
			$this->maybe_init_subscriptions();
		}

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;
	}
}
