<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe Link Payment Token.
 *
 * Representation of a payment token for Link.
 *
 * @class    WC_Payment_Token_Link
 */
class WC_Payment_Token_Link extends WC_Payment_Token {

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected $type = 'link';

	/**
	 * Stores Link payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'email'  => '',
	];

	/**
	 * Get type to display to user.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: customer email */
			__( 'Stripe Link email %s', 'woocommerce-gateway-stripe' ),
			$this->get_email()
		);

		return $display;
	}

	/**
	 * Hook prefix
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_link_get_';
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
	 * Returns the customer email.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Customer email.
	 */
	public function get_email( $context = 'view' ) {
		return $this->get_prop( 'email', $context );
	}

	/**
	 * Set the customer email.
	 *
	 * @param string $email Customer email.
	 */
	public function set_email( $email ) {
		$this->set_prop( 'email', $email );
	}
}
