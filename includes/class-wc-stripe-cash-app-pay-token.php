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
	 * Undocumented function
	 *
	 * @param string $token
	 */
	public function __construct( $token = '' ) {
		parent::__construct( $token );

		$this->extra_data['payment_method_type'] = 'cash_app';
	}

	/**
	 * Returns the name of the token to display
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string The name of the token to display
	 */
	public function get_display_name( $deprecated = '' ) {
		return __( 'Saved token for Cash App Pay', 'woocommerce-gateway-stripe' );
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
