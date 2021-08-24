<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_UPE_Payment_Method_CC
 */

/**
 * Credit card Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_CC extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'card';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe::class;

	/**
	 * Constructor for card payment method
	 *
	 * @param WC_Stripe_Payment_Tokens $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::STRIPE_ID;
		$this->title       = 'Credit card / debit card';
		$this->is_reusable = true;
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		if ( ! $payment_details ) {
			return $this->title;
		}

		$details       = $payment_details[ $this->stripe_id ];
		$funding_types = [
			'credit'  => __( 'credit', 'woocommerce-gateway-stripe' ),
			'debit'   => __( 'debit', 'woocommerce-gateway-stripe' ),
			'prepaid' => __( 'prepaid', 'woocommerce-gateway-stripe' ),
			'unknown' => __( 'unknown', 'woocommerce-gateway-stripe' ),
		];

		return sprintf(
			// Translators: %1$s card brand, %2$s card funding (prepaid, credit, etc.).
			__( '%1$s %2$s card', 'woocommerce-gateway-stripe' ),
			ucfirst( $details->network ),
			$funding_types[ $details->funding ]
		);
	}

}
