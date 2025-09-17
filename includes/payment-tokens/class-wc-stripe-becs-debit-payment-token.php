<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe BECS Debit Payment Token.
 *
 * Representation of a payment token for BECS.
 *
 * @class    WC_Payment_Token_Becs_Debit
 * @since    9.4.0
 */
class WC_Payment_Token_Becs_Debit extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {

	use WC_Stripe_Fingerprint_Trait;

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::BECS_DEBIT;

	/**
	 * Stores BECS payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'last4'               => '',
		'payment_method_type' => WC_Stripe_Payment_Methods::BECS_DEBIT,
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
			/* translators: last 4 digits of account. */
			__( 'BECS Direct Debit ending in %s', 'woocommerce-gateway-stripe' ),
			$this->get_last4(),
		);

		return $display;
	}

	/**
	 * Hook prefix
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_becs_debit_get_';
	}

	/**
	 * Validate BECS Debit payment tokens.
	 *
	 * These fields are required by all BECS Debit payment tokens:
	 * last4  - string Last 4 digits of the Account Number
	 * bank_name - string Name of the bank
	 * account_type - string Type of account (checking, savings)
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

		if ( ! $this->get_fingerprint( 'edit' ) ) {
			return false;
		}

		return true;
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
		if ( WC_Stripe_Payment_Methods::BECS_DEBIT !== $payment_method->type ) {
			return false;
		}

		// Becs Debit uses the au_becs_debit property.
		return ( $payment_method->au_becs_debit->fingerprint ?? null ) === $this->get_fingerprint();
	}
}
