<?php
/**
 * WooCommerce Stripe Klarna Payment Token
 *
 * Representation of a payment token for Klarna.
 *
 * @package WooCommerce_Stripe
 * @since 10.0.0
 */

// phpcs:disable WordPress.Files.FileName

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_Payment_Token_Klarna extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::KLARNA;

	/**
	 * Extra data.
	 *
	 * @var string[]
	 */
	protected $extra_data = [
		'dob' => '',
	];

	/**
	 * Returns the name of the token to display
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string The name of the token to display
	 */
	public function get_display_name( $deprecated = '' ) {
		$dob = $this->get_dob();

		// Translators: %s is the customer's date of birth.
		return empty( $dob ) ? __( 'Klarna', 'woocommerce-gateway-stripe' ) : sprintf( __( 'Klarna (%s)', 'woocommerce-gateway-stripe' ), $dob );
	}

	/**
	 * Sets the Klarna token's date of birth.
	 *
	 * @param object $dob The date of birth.
	 */
	public function set_dob( $dob ) {
		$this->set_prop( 'dob', $dob );
	}

	/**
	 * Fetches Klarna token's date of birth.
	 *
	 * @return object The Klarna token's date of birth.
	 */
	public function get_dob() {
		return $this->get_prop( 'dob' );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::KLARNA === $this->get_type()
			&& ( $payment_method->klarna->dob ?? null ) === $this->get_dob() ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns this token's hook prefix.
	 *
	 * @return string The hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_klarna_get_';
	}
}
