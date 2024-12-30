<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for comparing payment method tokens with payment methods.
 */
trait WC_Stripe_Token_Comparison_Trait {
	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param  object $payment_method Payment method object.
	 * @return bool
	 */
	public function is_equal( $payment_method ) {
		$fingerprint = method_exists( $this, 'get_fingerprint' ) ? $this->get_fingerprint() : null;
		$email       = method_exists( $this, 'get_email' ) ? $this->get_email() : null;
		$cashtag     = method_exists( $this, 'get_cashtag' ) ? $this->get_cashtag() : null;

		if ( $this->get_type() === 'CC' && ( $payment_method->card->fingerprint ?? null ) === $fingerprint ) {
			return true;
		}

		if ( $this->get_type() === WC_Stripe_Payment_Methods::SEPA && ( $payment_method->sepa_debit->fingerprint ?? null ) === $fingerprint ) {
			return true;
		}

		if ( $this->get_type() === WC_Stripe_Payment_Methods::LINK && ( $payment_method->link->email ?? null ) === $email ) {
			return true;
		}

		if ( $this->get_type() === WC_Stripe_Payment_Methods::CASHAPP_PAY && ( $payment_method->cashapp->cashtag ?? null ) === $cashtag ) {
			return true;
		}

		return false;
	}
}
