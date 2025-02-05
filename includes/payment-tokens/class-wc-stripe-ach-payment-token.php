<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe ACH Direct Debit Payment Token.
 *
 * Representation of a payment token for ACH.
 *
 * @class    WC_Payment_Token_ACH
 * @version  4.0.0
 * @since    4.0.0
 */
class WC_Payment_Token_ACH extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {

	use WC_Stripe_Fingerprint_Trait;

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::ACH;

	/**
	 * Stores ACH payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'bank_name'           => '',
		'account_type'        => '',
		'last4'               => '',
		'payment_method_type' => WC_Stripe_Payment_Methods::ACH,
		'fingerprint'         => '',
	];

	/**
	 * Get type to display to user.
	 *
	 * @since  4.0.0
	 * @version 4.0.0
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: bank name, account type (checking, savings), last 4 digits of account. */
			__( '%1$s account ending in %2$s (%3$s)', 'woocommerce-gateway-stripe' ),
			ucfirst( $this->get_account_type() ),
			$this->get_last4(),
			$this->get_bank_name()
		);

		return $display;
	}

	/**
	 * Hook prefix
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_ach_get_';
	}

	/**
	 * Validate ACH payment tokens.
	 *
	 * These fields are required by all ACH payment tokens:
	 * last4  - string Last 4 digits of the Account Number
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		// TODO: check all the properties.
		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the bank name.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_bank_name( $context = 'view' ) {
		return $this->get_prop( 'bank_name', $context );
	}

	/**
	 * Set the bank name.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $bank_name
	 */
	public function set_bank_name( $bank_name ) {
		$this->set_prop( 'bank_name', $bank_name );
	}

	/**
	 * Get the account type.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_account_type( $context = 'view' ) {
		return $this->get_prop( 'account_type', $context );
	}

	/**
	 * Set the account type.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $account_type
	 */
	public function set_account_type( $account_type ) {
		$this->set_prop( 'account_type', $account_type );
	}

	/**
	 * Returns the last four digits.
	 *
	 * @since  4.0.0
	 * @version 4.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
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
			WC_Stripe_Payment_Methods::ACH === $payment_method->type
			&& ( $payment_method->{WC_Stripe_Payment_Methods::ACH}->fingerprint ?? null ) === $this->get_fingerprint() ) {
			return true;
		}

		return false;
	}
}
