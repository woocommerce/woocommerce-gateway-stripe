<?php
/**
 * Token helpers.
 *
 * @package WooCommerce/Tests
 */

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
	 * @param string $gateway        Token's Gateway ID, default to WC_Payment_Gateway_WCPay::GATEWAY_ID
	 */
	public static function create_token( $payment_method, $user_id = null, $gateway = WC_Payment_Gateway_WCPay::GATEWAY_ID ) {
		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_method );
		$token->set_gateway_id( $gateway );
		$token->set_user_id( $user_id ?? get_current_user_id() );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( 6 );
		$token->set_expiry_year( intval( gmdate( 'Y' ) ) + 1 );
		$token->save();

		return WC_Payment_Tokens::get( $token->get_id() );
	}
}
