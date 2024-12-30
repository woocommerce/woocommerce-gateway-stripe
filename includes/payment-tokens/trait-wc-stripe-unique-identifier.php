<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for checking payment method token unique identifiers.
 */
trait WC_Stripe_Unique_Identifier_Trait {
	/**
	 * Checks if the payment method token is unique.
	 *
	 * @param  object $payment_method Payment method object.
	 * @return bool
	 */
	public function is_equal( $payment_method ) {
		switch ( $this->get_type() ) {
			case 'CC':
				if ( isset( $payment_method->card->fingerprint ) && WC_Stripe_Payment_Methods::CARD === $payment_method->type
					&& $payment_method->card->fingerprint === $this->get_fingerprint() ) {
					return true;
				}
				break;
			case WC_Stripe_Payment_Methods::SEPA:
				if ( isset( $payment_method->sepa_debit->fingerprint ) && WC_Stripe_Payment_Methods::SEPA_DEBIT === $payment_method->type
					&& $payment_method->sepa_debit->fingerprint === $this->get_fingerprint() ) {
					return true;
				}
				break;
			case WC_Stripe_Payment_Methods::LINK:
				if ( isset( $payment_method->link->email ) && WC_Stripe_Payment_Methods::LINK === $payment_method->type
					&& $payment_method->link->email === $this->get_email() ) {
					return true;
				}
				break;
			case WC_Stripe_Payment_Methods::CASHAPP_PAY:
				if ( isset( $payment_method->cashapp->cashtag ) && WC_Stripe_Payment_Methods::CASHAPP_PAY === $payment_method->type
					&& $payment_method->cashapp->cashtag === $this->get_cashtag() ) {
					return true;
				}
				break;
		}
		return false;
	}
}
