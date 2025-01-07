<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for comparing payment method tokens with payment methods.
 */
interface WC_Stripe_Payment_Method_Comparison_Interface {
	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param  object $payment_method Payment method object.
	 * @return bool
	 */
	public function is_equal_payment_method( $payment_method ): bool;
}
