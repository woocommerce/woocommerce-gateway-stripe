<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Affirm Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Affirm extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::AFFIRM;

	/**
	 * Stripe account countries that may enable Affirm.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_ACCOUNT_COUNTRIES = [
		WC_Stripe_Country_Code::UNITED_STATES,
		WC_Stripe_Country_Code::CANADA,
	];

	/**
	 * Shopper billing countries permitted to use Affirm (Affirm is domestic-only, so same as account).
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [
		WC_Stripe_Country_Code::UNITED_STATES,
		WC_Stripe_Country_Code::CANADA,
	];

	/**
	 * Constructor for Affirm payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Affirm.',
			'woocommerce-gateway-stripe'
		);
		$this->limits_per_currency          = [
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR      => [
				WC_Stripe_Country_Code::CANADA => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents CAD 50 - 30,000 CAD.
			],
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR => [
				WC_Stripe_Country_Code::UNITED_STATES => [
					'min' => 3500,
					'max' => 3000000,
				], // Represents USD 35 - 30,000 USD.
			],
		];
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @inheritDoc
	 */
	public function is_available() {
		// Affirm is only available if the official Affirm plugin is not active.
		if ( WC_Stripe_Helper::has_gateway_plugin_active( WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM ) ) {
			return false;
		}

		return parent::is_available();
	}
}
