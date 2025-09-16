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

class WC_Stripe_Klarna_Payment_Token extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
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
		return __( 'Klarna', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Sets the Klarna token's date of birth.
	 *
	 * @param string $dob The formatted date of birth object (YYYY-mm-dd).
	 */
	public function set_dob( string $dob ) {
		$this->set_prop( 'dob', $dob );
	}

	/**
	 * Fetches Klarna token's date of birth (formatted to Y-m-d).
	 *
	 * @return string The Klarna token's date of birth.
	 */
	public function get_dob(): string {
		$dob = $this->get_prop( 'dob' );
		if ( is_string( $dob ) ) {
			return $dob;
		}
		return '';
	}

	/**
	 * Sets the Klarna token's date of birth based on the object returned from the Stripe payment method API.
	 *
	 * @param object $dob The raw `dob` object from Stripe.
	 * @see https://docs.stripe.com/api/payment_methods/object#payment_method_object-klarna-dob
	 */
	public function set_dob_from_object( object $dob ) {
		$this->set_dob( $this->format_dob( $dob ) );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::KLARNA !== $payment_method->type ) {
			return false;
		}

		$payment_method_dob = $payment_method->klarna->dob ?? null;
		if ( empty( $this->get_dob() ) ) {
			return null === $payment_method_dob;
		}

		if ( null === $payment_method_dob || empty( get_object_vars( $payment_method_dob ) ) ) {
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
		return 'wc_stripe_klarna_payment_token_get_';
	}

	/**
	 * Formats the date of birth for display.
	 *
	 * @param $dob object The date of birth object.
	 * @return string The formatted date of birth.
	 */
	protected function format_dob( object $dob ) {
		if ( empty( $dob->year ) && empty( $dob->month ) && empty( $dob->day ) ) {
			return '';
		}
		$dob_parts = [
			$dob->year ?? 'YYYY',
			$dob->month ? str_pad( $dob->month, 2, '0', STR_PAD_LEFT ) : 'MM',
			$dob->day ? str_pad( $dob->day, 2, '0', STR_PAD_LEFT ) : 'DDDD',
		];
		return implode( '-', array_filter( $dob_parts ) );
	}
}
