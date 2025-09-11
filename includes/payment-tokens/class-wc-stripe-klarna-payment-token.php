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
		if ( empty( $this->get_dob() ) ) {
			return __( 'Klarna', 'woocommerce-gateway-stripe' );
		}

		$masked_dob = 'xxxx' . gmdate( '-m-d', strtotime( $this->get_dob() ) );

		// Translators: %s is the customer's masked date of birth.
		return sprintf( __( 'Klarna (%s)', 'woocommerce-gateway-stripe' ), $masked_dob );
	}

	/**
	 * Sets the Klarna token's date of birth.
	 *
	 * @param string $dob The formated date of birth object (YYYY-mm-dd).
	 */
	public function set_dob( $dob ) {
		$this->set_prop( 'dob', $dob );
	}

	/**
	 * Fetches Klarna token's date of birth (formated to Y-m-d).
	 *
	 * @return string The Klarna token's date of birth.
	 */
	public function get_dob() {
		return $this->get_prop( 'dob' );
	}

	/**
	 * Formats and sets the Klarna token's date of birth.
	 *
	 * @param object $dob The raw date of birth object (from Stripe's API).
	 */
	public function set_formated_dob( $dob ) {
		$this->set_dob( $this->format_dob( $dob ) );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::KLARNA !== $this->get_type() ) {
			return false;
		}

		$payment_method_dob = $payment_method->klarna->dob ?? null;
		if ( empty( $this->get_dob() ) ) {
			return null === $payment_method_dob;
		}

		if ( null === $payment_method_dob ) {
			return false;
		}

		return $this->format_dob( $payment_method_dob ) === $this->get_dob();
	}

	/**
	 * Returns this token's hook prefix.
	 *
	 * @return string The hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_klarna_get_';
	}

	/**
	 * Formats the date of birth for display.
	 *
	 * @param $dob object The date of birth object.
	 * @return string The formatted date of birth.
	 */
	protected function format_dob( $dob ) {
		return sprintf( '%04d-%02d-%02d', $dob->year, $dob->month, $dob->day );
	}
}
