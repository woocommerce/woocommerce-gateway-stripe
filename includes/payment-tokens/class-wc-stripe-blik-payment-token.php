<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe BLIK Payment Token.
 *
 * Representation of a payment token for BLIK.
 *
 * @class    WC_Payment_Token_BLIK
 * @since    x.x.x
 */
class WC_Payment_Token_BLIK extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {

	use WC_Stripe_Fingerprint_Trait;

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::BLIK;

	/**
	 * Stores BLIK payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'bank_name'           => '',
		'last4'               => '',
		'payment_method_type' => WC_Stripe_Payment_Methods::BLIK,
		'fingerprint'         => '',
	];

	/**
	 * Get type to display to user.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: bank name, last 4 digits of account. */
			__( 'Saved BLIK payment method', 'woocommerce-gateway-stripe' ),
			$this->get_last4(),
			$this->get_bank_name()
		);

		return $display;
	}

	/**
	 * Hook prefix
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_blik_get_';
	}

	/**
	 * Validate BLIK payment tokens.
	 *
	 * These fields are required by all BLIK payment tokens:
	 * last4  - string Last 4 digits of the Account Number
	 * bank_name - string Name of the bank
	 * fingerprint - string Unique identifier for the bank account
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_bank_name( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_fingerprint( 'edit' ) ) {
			return false;
		}

		return true;
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
	 * Set the bank name.
	 *
	 * @param string $bank_name
	 */
	public function set_bank_name( $bank_name ) {
		$this->set_prop( 'bank_name', $bank_name );
	}

	/**
	 * Returns the last four digits.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if (
			WC_Stripe_Payment_Methods::BLIK === $payment_method->type
			&& ( $payment_method->{WC_Stripe_Payment_Methods::BLIK}->fingerprint ?? null ) === $this->get_fingerprint() ) {
			return true;
		}

		return false;
	}
}
