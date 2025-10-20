<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe Amazon Pay Payment Token.
 *
 * Representation of a payment token for Amazon Pay.
 *
 * @class    WC_Payment_Token_Amazon_Pay
 */
class WC_Payment_Token_Amazon_Pay extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::AMAZON_PAY;

	/**
	 * Stores Amazon Pay payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'email' => '',
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
			__( 'Amazon Pay (%s)', 'woocommerce-gateway-stripe' ),
			$this->get_email()
		);

		return $display;
	}

	/**
	 * Hook prefix
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_amazon_pay_get_';
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

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::AMAZON_PAY !== $payment_method->type ) {
			return false;
		}

		return ( $payment_method->billing_details->email ?? null ) === $this->get_email();
	}
}
