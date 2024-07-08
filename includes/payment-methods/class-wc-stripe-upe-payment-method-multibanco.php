<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Multibanco Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Multibanco extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'multibanco';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Multibanco::class;

	/**
	 * Constructor for Multibanco payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Multibanco', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'Multibanco', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Multibanco is an interbank network that links the ATMs of all major banks in Portugal, allowing customers to pay through either their ATM or online banking environment.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'wc_stripe_allowed_payment_processing_statuses', [ $this, 'add_allowed_payment_processing_statuses' ], 10, 2 );
	}

	/**
	 * Adds on-hold as accepted status during webhook handling on orders paid with Mukltibanco
	 *
	 * @param $allowed_statuses
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_allowed_payment_processing_statuses( $allowed_statuses, $order ) {
		if ( 'multibanco' === $order->get_meta( '_stripe_upe_payment_type' ) && ! in_array( 'on-hold', $allowed_statuses, true ) ) {
			$allowed_statuses[] = 'on-hold';
		}

		return $allowed_statuses;
	}
}
