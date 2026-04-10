<?php
/**
 * WooCommerce Stripe Credit Card Payment Token
 *
 * Representation of a payment token for Credit Card.
 *
 * @package WooCommerce_Stripe
 * @since 9.9.0
 */

// phpcs:disable WordPress.Files.FileName

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_Stripe_Payment_Token_CC extends WC_Payment_Token_CC implements WC_Stripe_Payment_Method_Comparison_Interface {

	use WC_Stripe_Fingerprint_Trait;

	/**
	 * Constructor.
	 *
	 * @inheritDoc
	 */
	public function __construct( $token = '' ) {
		// Add fingerprint to extra data to be persisted.
		$this->extra_data['fingerprint'] = '';

		parent::__construct( $token );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::CARD !== $payment_method->type ) {
			return false;
		}

		return ( $payment_method->card->fingerprint ?? null ) === $this->get_fingerprint();
	}
}
