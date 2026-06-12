<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Przelewy24 Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_P24 extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::P24;

	/**
	 * Stripe account countries that may not enable Przelewy24.
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
	 * Shopper billing countries permitted to use Przelewy24.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [ WC_Stripe_Country_Code::POLAND ];

	/**
	 * Constructor for Przelewy24 payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Przelewy24', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::POLISH_ZLOTY ];
		$this->label                = __( 'Przelewy24', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Przelewy24 is a Poland-based payment method aggregator that allows customers to complete transactions online using bank transfers and other methods.',
			'woocommerce-gateway-stripe'
		);
	}
}
