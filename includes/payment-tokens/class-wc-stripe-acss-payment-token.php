<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe ACSS Payment Token.
 *
 * Token for ACSS.
 *
 * @since 9.4.0
 */
class WC_Payment_Token_ACSS extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
	use WC_Stripe_Fingerprint_Trait;

	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::ACSS_DEBIT;

	/**
	 * ACSS payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'bank_name'           => '',
		'last4'               => '',
		'payment_method_type' => WC_Stripe_Payment_Methods::ACSS_DEBIT,
		'fingerprint'         => '',
	];

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param  object $payment_method Payment method object.
	 * @return bool
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::ACSS_DEBIT !== $payment_method->type ) {
			return false;
		}

		return ( $payment_method->acss_debit->fingerprint ?? null ) === $this->get_fingerprint();
	}

	/**
	 * Set the last four digits for the ACSS Debit Token.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the last four digits of the ACSS Token.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string The last 4 digits.
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set Stripe payment method type.
	 *
	 * @param string $type Payment method type.
	 */
	public function set_payment_method_type( $type ) {
		$this->set_prop( 'payment_method_type', $type );
	}

	/**
	 * Returns Stripe payment method type.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string $payment_method_type
	 */
	public function get_payment_method_type( $context = 'view' ) {
		return $this->get_prop( 'payment_method_type', $context );
	}

	/**
	 * Set the bank name.
	 *
	 * @param string $bank_name
	 */
	public function set_bank_name( $bank_name ) {
		$this->set_prop( 'bank_name', $bank_name );
	}

	/**
	 * Get the bank name.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_bank_name( $context = 'view' ) {
		return $this->get_prop( 'bank_name', $context );
	}

	/**
	 * Returns the name of the token to display.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: bank name, last 4 digits of account. */
			__( '%1$s ending in %2$s', 'woocommerce-gateway-stripe' ),
			$this->get_bank_name(),
			$this->get_last4()
		);

		return $display;
	}

	/**
	 * Hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_acss_get_';
	}
}
