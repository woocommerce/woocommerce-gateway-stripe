<?php
/**
 * Class WC_Helper_Token.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Helper_Token {

	/**
	 * Create a token.
	 *
	 * @param string $payment_method Token payment method.
	 * @param int    $user_id        ID of the token's user, defaults to get_current_user_id().
	 * @param string $gateway        Token's Gateway ID, default to WC_Gateway_Stripe::ID
	 */
	public static function create_token( $payment_method, $user_id = null, $gateway = WC_Gateway_Stripe::ID ) {
		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_method );
		$token->set_gateway_id( $gateway );
		$token->set_user_id( is_null( $user_id ) ? get_current_user_id() : $user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( 7 );
		$token->set_expiry_year( intval( gmdate( 'Y' ) ) + 1 );
		// Co-branded credit card support
		if ( version_compare( WC_VERSION, '8.7.0', '>' ) ) {
			$token->set_available_networks( [ 'visa', 'cartes_bancaires' ] );
			$token->set_preferred_network( 'visa' );
			$token->save();
		}
		$token->save();

		return WC_Payment_Tokens::get( $token->get_id() );
	}

	/**
	 * Create a SEPA token.
	 *
	 * @param string $payment_method      Token payment method.
	 * @param string $payment_method_type Original Stripe payment method type, defaults to sepa_debit.
	 * @param int    $user_id             ID of the token's user, defaults to get_current_user_id().
	 * @param string $gateway             Token's Gateway ID, default to WC_Gateway_Stripe::ID
	 */
	public static function create_sepa_token( $payment_method, $payment_method_type = 'sepa_debit', $user_id = null, $gateway = WC_Gateway_Stripe::ID ) {
		$token = new WC_Payment_Token_SEPA();
		$token->set_token( $payment_method );
		$token->set_gateway_id( $gateway );
		$token->set_user_id( is_null( $user_id ) ? get_current_user_id() : $user_id );
		$token->set_last4( '4269' );
		$token->save();

		return WC_Payment_Tokens::get( $token->get_id() );
	}
}
