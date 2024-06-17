<?php
/**
 * WooCommerce Stripe Cash App Pay Payment Token
 *
 * Representation of a payment token for Cash App Pay.
 *
 * @package WooCommerce_Stripe
 * @since 8.4.0
 */

// phpcs:disable WordPress.Files.FileName

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_Payment_Token_CashApp extends WC_Payment_Token {

	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected $type = 'cashapp';

	/**
	 * Extra data.
	 *
	 * @var string[]
	 */
	protected $extra_data = [
		'cashtag' => '',
	];

	/**
	 * Returns the name of the token to display
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string The name of the token to display
	 */
	public function get_display_name( $deprecated = '' ) {
		$cashtag = $this->get_cashtag();

		// Translators: %s is the Cash App Pay $Cashtag.
		return empty( $cashtag ) ? __( 'Cash App Pay', 'woocommerce-gateway-stripe' ) : sprintf( __( 'Cash App Pay (%s)', 'woocommerce-gateway-stripe' ), $cashtag );
	}

	/**
	 * Sets the Cash App Pay $Cashtag for this token.
	 *
	 * @param string $cashtag A public identifier for buyers using Cash App.
	 */
	public function set_cashtag( $cashtag ) {
		$this->set_prop( 'cashtag', $cashtag );
	}

	/**
	 * Fetches the Cash App Pay token's $Cashtag.
	 *
	 * @return string The Cash App Pay $Cashtag.
	 */
	public function get_cashtag() {
		return $this->get_prop( 'cashtag' );
	}

	/**
	 * Returns this token's hook prefix.
	 *
	 * @return string The hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_cashapp_get_';
	}
}
