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
		$this->extra_data['fingerprint'] = '';
		// Stripe `card.wallet.type` (`apple_pay`, `google_pay`, `link`); empty for manual entry.
		$this->extra_data['wallet_type'] = '';

		parent::__construct( $token );
	}

	/**
	 * Returns the digital wallet the card was tokenized through, if any.
	 *
	 * @param string $context
	 * @return string
	 */
	public function get_wallet_type( $context = 'view' ) {
		return $this->get_prop( 'wallet_type', $context );
	}

	/**
	 * Stores the digital wallet the card was tokenized through.
	 *
	 * @param string $wallet_type
	 * @return void
	 */
	public function set_wallet_type( string $wallet_type ) {
		$this->set_prop( 'wallet_type', $wallet_type );
	}

	/**
	 * Returns the shopper-facing wallet label, or '' for tokens we don't surface
	 * wallet branding on (manual entry, `link`, anything unknown).
	 *
	 * @return string
	 */
	public function get_wallet_brand_label(): string {
		switch ( $this->get_wallet_type() ) {
			case WC_Stripe_Payment_Methods::APPLE_PAY:
				return WC_Stripe_Payment_Methods::APPLE_PAY_LABEL;
			case WC_Stripe_Payment_Methods::GOOGLE_PAY:
				return WC_Stripe_Payment_Methods::GOOGLE_PAY_LABEL;
			default:
				return '';
		}
	}

	/**
	 * Wraps the parent display name with wallet branding for Apple Pay /
	 * Google Pay tokenized cards so the classic checkout radio matches the
	 * My Account label, e.g. "Apple Pay (Visa) ending in 4242 (expires 03/27)".
	 *
	 * @inheritDoc
	 */
	public function get_display_name( $deprecated = '' ) {
		$wallet_label = $this->get_wallet_brand_label();
		if ( '' === $wallet_label ) {
			return parent::get_display_name( $deprecated );
		}

		return sprintf(
			/* translators: 1: wallet brand e.g. "Apple Pay", "Google Pay"; 2: card brand e.g. Visa; 3: last 4 digits; 4: expiry month; 5: expiry year */
			__( '%1$s (%2$s) ending in %3$s (expires %4$s/%5$s)', 'woocommerce-gateway-stripe' ),
			$wallet_label,
			wc_get_credit_card_type_label( $this->get_card_type() ),
			$this->get_last4(),
			$this->get_expiry_month(),
			substr( $this->get_expiry_year(), 2 )
		);
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
