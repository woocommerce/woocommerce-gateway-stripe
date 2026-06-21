<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Pay by Bank Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Pay_By_Bank extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::PAY_BY_BANK;

	/**
	 * Shopper billing countries permitted to use Pay by Bank.
	 * Customers must have a bank account in one of these countries to authenticate.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [
		WC_Stripe_Country_Code::GERMANY,
		WC_Stripe_Country_Code::FINLAND,
		WC_Stripe_Country_Code::FRANCE,
		WC_Stripe_Country_Code::UNITED_KINGDOM,
		WC_Stripe_Country_Code::IRELAND,
	];

	/**
	 * Constructor for Pay by Bank payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay by Bank', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [
			WC_Stripe_Currency_Code::EURO,
			WC_Stripe_Currency_Code::POUND_STERLING,
		];
		$this->label                = __( 'Pay by Bank', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Allow customers to pay directly from their bank account.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return true;
	}
}
