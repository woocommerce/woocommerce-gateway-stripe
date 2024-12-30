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

class WC_Stripe_Payment_Token_CC extends WC_Payment_Token_CC {

	use WC_Stripe_Fingerprint_Trait;
	use WC_Stripe_Unique_Identifier_Trait;

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
}
