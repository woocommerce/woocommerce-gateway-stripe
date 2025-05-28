<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Trait for Subscriptions utility functions.
 *
 * @since 5.6.0
 */
trait WC_Stripe_Subscriptions_Utilities_Trait {

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @since 5.6.0
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 *
	 * @deprecated 9.2.0 Use WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled instead.
	 */
	public function is_subscriptions_enabled() {
		wc_deprecated_function( 'is_subscriptions_enabled', '9.2.0', 'WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled' );
		return WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled();
	}

	/**
	 * Is $order_id a subscription?
	 *
	 * @since 5.6.0
	 *
	 * @param  int $order_id
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return (
			function_exists( 'wcs_order_contains_subscription' )
			&& function_exists( 'wcs_is_subscription' )
			&& function_exists( 'wcs_order_contains_renewal' )
			&& ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) )
		);
	}

	/**
	 * Returns whether this user is changing the payment method for a subscription.
	 *
	 * @since 5.6.0
	 *
	 * @return bool
	 */
	public function is_changing_payment_method_for_subscription() {
		if ( isset( $_GET['change_payment_method'] ) && function_exists( 'wcs_is_subscription' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wcs_is_subscription( wc_clean( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return false;
	}

	/**
	 * Returns boolean value indicating whether payment for an order will be recurring,
	 * as opposed to single.
	 *
	 * @since 5.6.0
	 *
	 * @param int $order_id ID for corresponding WC_Order in process.
	 *
	 * @return bool
	 */
	public function is_payment_recurring( $order_id ) {
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return false;
		}
		return $this->is_changing_payment_method_for_subscription() || $this->has_subscription( $order_id );
	}

	/**
	 * Returns a boolean value indicating whether the save payment checkbox should be
	 * displayed during checkout.
	 *
	 * Returns `false` if the cart currently has a subscriptions or if the request has a
	 * `change_payment_method` GET parameter. Returns the value in `$display` otherwise.
	 *
	 * @since 5.6.0
	 *
	 * @param bool $display Bool indicating whether to show the save payment checkbox in the absence of subscriptions.
	 *
	 * @return bool Indicates whether the save payment method checkbox should be displayed or not.
	 */
	public function display_save_payment_method_checkbox( $display ) {
		if (
			( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() )
			|| $this->is_changing_payment_method_for_subscription()
		) {
			return false;
		}
		// Only render the "Save payment method" checkbox if there are no subscription products in the cart.
		return $display;
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @since 5.6.0
	 *
	 * @return bool
	 */
	public function is_subscription_item_in_cart() {
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) || $this->cart_contains_renewal();
		}
		return false;
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 *
	 * @since 5.6.0
	 *
	 * @return mixed The cart item containing the renewal as an array, else false.
	 */
	public function cart_contains_renewal() {
		if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
			return false;
		}
		return wcs_cart_contains_renewal();
	}

	/**
	 * Checks if the given object is a WC_Subscription.
	 *
	 * Slightly more performant than has_subscription() which checks wcs_order_contains_subscription() first.
	 *
	 * @param  mixed $subscription
	 *
	 * @return boolean
	 */
	public function is_subscription( $subscription ) {
		return function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $subscription );
	}
}
