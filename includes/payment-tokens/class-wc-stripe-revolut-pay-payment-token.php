<?php
/**
 * WooCommerce Stripe Revolut Pay Payment Token
 *
 * Representation of a payment token for Revolut Pay.
 *
 * @package WooCommerce_Stripe
 */

// phpcs:disable WordPress.Files.FileName

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_Stripe_Revolut_Pay_Payment_Token extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::REVOLUT_PAY;

	/**
	 * Returns the name of the token to display.
	 *
	 * @param string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string The name of the token to display.
	 */
	public function get_display_name( $deprecated = '' ) {
		return __( 'Revolut Pay', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * Revolut Pay tokens carry no distinguishing sub-fields (unlike Klarna's DOB or
	 * Cash App's cashtag), so a type match is sufficient to dedupe a customer's mandate.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		return WC_Stripe_Payment_Methods::REVOLUT_PAY === $payment_method->type;
	}

	/**
	 * Returns this token's hook prefix.
	 *
	 * @return string The hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'wc_stripe_revolut_pay_payment_token_get_';
	}
}
