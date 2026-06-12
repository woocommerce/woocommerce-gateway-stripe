<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EPS Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Eps extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::EPS;

	/**
	 * Stripe account countries that may not enable EPS.
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
	 * Shopper billing countries permitted to use EPS.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [ WC_Stripe_Country_Code::AUSTRIA ];

	/**
	 * Constructor for EPS payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'EPS', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO ];
		$this->label                = __( 'EPS', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'EPS is an Austria-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		);
	}
}
