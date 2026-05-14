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
		// Stripe `card.wallet.type` (`apple_pay`, `google_pay`, `link`) when the card was
		// tokenized via a digital wallet. Empty for manually entered cards. Used by the
		// saved-methods list to surface the wallet brand instead of just the underlying card.
		$this->extra_data['wallet_type'] = '';

		parent::__construct( $token );
	}

	/**
	 * Returns the digital wallet the card was tokenized through.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string One of WC_Stripe_Payment_Methods::APPLE_PAY/GOOGLE_PAY/LINK, or empty.
	 */
	public function get_wallet_type( $context = 'view' ) {
		return $this->get_prop( 'wallet_type', $context );
	}

	/**
	 * Stores the digital wallet the card was tokenized through.
	 *
	 * @param string $wallet_type One of WC_Stripe_Payment_Methods::APPLE_PAY/GOOGLE_PAY/LINK, or empty.
	 * @return void
	 */
	public function set_wallet_type( string $wallet_type ) {
		$this->set_prop( 'wallet_type', $wallet_type );
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
