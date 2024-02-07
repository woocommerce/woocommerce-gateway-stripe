<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boleto Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Boleto extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'boleto';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Boleto::class;

	/**
	 * Constructor for Boleto payment method
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->can_refund           = false;
		$this->title                = 'Boleto';
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'BRL' ];
		$this->supported_countries  = [ 'BR' ];
		$this->supports             = [ 'products' ];
		$this->label                = __( 'Boleto', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Boleto is an official payment method in Brazil. Customers receive a voucher that can be paid at authorized agencies or banks, ATMs, or online bank portals.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'wc_stripe_allowed_payment_processing_statuses', [ $this, 'add_allowed_payment_processing_statuses' ], 10, 2 );
	}

	/**
	 * Adds on-hold as accepted status during webhook handling on orders paid with Boleto
	 *
	 * @param $allowed_statuses
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_allowed_payment_processing_statuses( $allowed_statuses, $order ) {
		if ( 'boleto' === $order->get_meta( '_stripe_upe_payment_type' ) && ! in_array( 'on-hold', $allowed_statuses ) ) {
			$allowed_statuses[] = 'on-hold';
		}

		return $allowed_statuses;
	}
}
