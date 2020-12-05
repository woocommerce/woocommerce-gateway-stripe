<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce Stripe SEPA Direct Debit Payment Token.
 *
 * Representation of a payment token for SEPA.
 *
 * @class    WC_Payment_Token_SEPA
 * @version  4.0.0
 * @since    4.0.0
 */
class WC_Payment_Token_SEPA extends WC_Payment_Token {

	/** @protected string Token Type String. */
	protected $type = 'sepa';

	/**
	 * Stores SEPA payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4' => '',
	);

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
			/* translators: last 4 digits of IBAN account */
			__( 'SEPA IBAN ending in %s', 'woocommerce-gateway-stripe' ),
			$this->get_last4()
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
		return 'woocommerce_payment_token_sepa_get_';
	}

	/**
	 * Validate SEPA payment tokens.
	 *
	 * These fields are required by all SEPA payment tokens:
	 * last4  - string Last 4 digits of the iBAN
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the last four digits.
	 *
	 * @since  4.0.0
	 * @version 4.0.0
	 * @param  string $context
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}
}
