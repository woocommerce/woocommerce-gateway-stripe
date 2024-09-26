<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OXXO Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Oxxo extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::OXXO;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Oxxo::class;

	/**
	 * Constructor for OXXO payment method
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->can_refund           = false;
		$this->title                = 'OXXO';
		$this->is_reusable          = false;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::MEXICAN_PESO ];
		$this->supported_countries  = [ 'MX' ];
		$this->supports             = [ 'products' ];
		$this->label                = __( 'OXXO', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'OXXO is a Mexican chain of convenience stores that allows customers to pay bills and online purchases in-store with cash.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'wc_stripe_allowed_payment_processing_statuses', [ $this, 'add_allowed_payment_processing_statuses' ], 10, 2 );
	}

	/**
	 * Adds on-hold as accepted status during webhook handling on orders paid with OXXO
	 *
	 * @param $allowed_statuses
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_allowed_payment_processing_statuses( $allowed_statuses, $order ) {
		if ( WC_Stripe_Payment_Methods::OXXO === $order->get_meta( '_stripe_upe_payment_type' ) && ! in_array( 'on-hold', $allowed_statuses ) ) {
			$allowed_statuses[] = 'on-hold';
		}

		return $allowed_statuses;
	}
}
