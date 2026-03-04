<?php
/**
 * Interface WC_Stripe_Checkout_Session_Interface
 *
 * Typed wrapper around the v1.delegated_checkout.customize_checkout webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base interface for checkout sessions.
 *
 * Used for both webhooks (completed sessions) and the customization hook.
 *
 * @since 10.5.0
 */
interface WC_Stripe_Checkout_Session_Interface {
	/**
	 * Returns the billing address object.
	 *
	 * @since 10.5.0
	 * @return WC_Stripe_API_Address
	 */
	public function get_billing_address(): WC_Stripe_API_Address;

	/**
	 * Returns the shipping address object, if provided.
	 *
	 * @since 10.5.0
	 * @return WC_Stripe_API_Address|null
	 */
	public function get_shipping_address(): ?WC_Stripe_API_Address;
}
