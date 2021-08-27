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
	 */
	public function __construct() {
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

	/**
	 * Add payment method to user and return WC payment token.
	 *
	 * @param WP_User $user User to add payment token to.
	 * @param object $intent JSON object for Stripe payment/setup intent.
	 *
	 * @return WC_Payment_Token_CC WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $intent ) {
		$payment_method = $intent->payment_method;

		$token = new WC_Payment_Token_CC();
		$token->set_expiry_month( $payment_method->card->exp_month );
		$token->set_expiry_year( $payment_method->card->exp_year );
		$token->set_card_type( strtolower( $payment_method->card->brand ) );
		$token->set_last4( $payment_method->card->last4 );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user->ID );
		$token->save();

		return $token;
	}


}
