<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Page_Helper class.
 */
class WC_Stripe_Page_Helper {
	/**
	 * Checks if the current page is the pay for order page and the current user is allowed to pay for the order.
	 *
	 * @return bool
	 */
	public static function is_valid_pay_for_order(): bool {
		// If not on the pay for order page, return false.
		if ( ! static::is_pay_for_order( true ) ) {
			return false;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		$order    = wc_get_order( $order_id );

		// If the order is not found or the param `key` is not set or the order key does not match the order key in the URL param, return false.
		if ( ! $order || ! isset( $_GET['key'] ) || wc_clean( wp_unslash( $_GET['key'] ) ) !== $order->get_order_key() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		// If the order doesn't need payment, we don't need to prepare the payment page.
		if ( ! $order->needs_payment() ) {
			return false;
		}

		return current_user_can( 'pay_for_order', $order->get_id() );
	}

	/**
	 * Checks if the current page is the order received page and the current user is allowed to manage the order.
	 *
	 * @return bool
	 */
	public static function is_valid_order_received(): bool {
		// If not on the order-received page, return false.
		if ( ! static::is_order_received( true ) ) {
			return false;
		}

		// Verify nonce. Duplicated here in order to avoid PHPCS warnings.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ), 'wc_stripe_process_redirect_order_nonce' ) ) {
			return false;
		}

		$order_id_from_order_key = absint( wc_get_order_id_by_order_key( wc_clean( wp_unslash( $_GET['key'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$order_id_from_query_var = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : null;

		// If the order ID is not found or the order ID does not match the given order ID, return false.
		if ( ! $order_id_from_order_key || ( $order_id_from_query_var !== $order_id_from_order_key ) ) {
			return false;
		}

		$order = wc_get_order( $order_id_from_order_key );

		// If the order doesn't need payment, return false.
		if ( ! $order->needs_payment() ) {
			return false;
		}

		return current_user_can( 'pay_for_order', $order->get_id() );
	}

	/**
	 * Checks if this is the Pay for Order page.
	 *
	 * @param bool $check_key Optional. If true, check if the key is set in the URL.
	 * @return boolean
	 */
	public static function is_pay_for_order( $check_key = false ) {
		$is_pay_for_order = is_checkout_pay_page() || is_wc_endpoint_url( 'order-pay' ) || isset( $_GET['pay_for_order'] ); // phpcs:ignore WordPress.Security.NonceVerification
		return $is_pay_for_order && ( ! $check_key || isset( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Checks if this is the order received page.
	 *
	 * @param $check_key bool Optional. If true, check if the key is set in the URL.
	 * @return bool
	 */
	public static function is_order_received( $check_key = false ) {
		$is_order_received = is_order_received_page() || is_wc_endpoint_url( 'order-received' ); // phpcs:ignore WordPress.Security.NonceVerification
		return $is_order_received && ( ! $check_key || isset( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Checks if this page is a cart page.
	 *
	 * @return bool
	 */
	public static function is_cart() {
		return is_cart() || has_block( 'woocommerce/cart' );
	}

	/**
	 * Checks if this page is a checkout page.
	 *
	 * @return bool
	 */
	public static function is_checkout() {
		return is_checkout() || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Checks if this page is a cart or checkout page.
	 *
	 * @return boolean
	 */
	public static function is_cart_or_checkout() {
		return static::is_cart() || static::is_checkout();
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @return boolean
	 */
	public static function is_product() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}

	/**
	 * Checks if this is the change payment method page.
	 *
	 * @return boolean
	 */
	public static function is_change_payment_method() {
		return isset( $_GET['change_payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Returns true when viewing payment methods page.
	 *
	 * @return bool
	 */
	public static function is_payment_methods() {
		global $wp;

		$page_id = wc_get_page_id( 'myaccount' );

		return ( $page_id && is_page( $page_id ) && ( isset( $wp->query_vars['payment-methods'] ) ) );
	}

	/**
	 * Checks if this is the Stripe settings page.
	 *
	 * @return bool
	 */
	public static function is_admin_settings() {
		// phpcs:disable WordPress.Security.NonceVerification
		return is_admin()
			&& isset( $_GET['page'], $_GET['tab'], $_GET['section'] )
			&& 'wc-settings' === $_GET['page']
			&& 'checkout' === $_GET['tab']
			&& 0 === strpos( wc_clean( wp_unslash( $_GET['section'] ) ), 'stripe' );
		// phpcs:enable WordPress.Security.NonceVerification
	}
}
