<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Keeps the customer's order notes on the classic checkout across a failed Stripe payment.
 *
 * When a guest opts into account creation and the payment then fails, WooCommerce reloads the
 * checkout page to pick up the new login. The address fields are rebuilt from the customer
 * record, but the order notes have no such source and come back empty even though they were
 * already saved on the failed order. Prefilling from that order restores what the customer
 * typed and carries it onto the retried payment.
 *
 * @since 11.0.0
 */
class WC_Stripe_Checkout_Customer_Note {

	/**
	 * Registers the classic checkout hook.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_filter( 'default_checkout_order_comments', [ $this, 'restore_note_from_awaiting_order' ] );
	}

	/**
	 * Returns the customer note of the order awaiting payment in the session when the field has no other default.
	 *
	 * Uses the status half of the condition WooCommerce core applies when it resumes that order
	 * (pending or failed), narrowed to orders that went through a Stripe gateway. The cart-hash
	 * half is skipped on purpose: the note is the customer's own input for this session, so it
	 * should survive a cart change that makes core create a fresh order instead.
	 *
	 * @param string|null $value The default value WooCommerce computed for the order notes field.
	 * @return string|null
	 */
	public function restore_note_from_awaiting_order( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( ! $session || ! is_callable( [ $session, 'get' ] ) ) {
			return $value;
		}

		$order_id = absint( $session->get( 'order_awaiting_payment' ) );
		if ( ! $order_id ) {
			return $value;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! $order->has_status( [ OrderStatus::PENDING, OrderStatus::FAILED ] ) ) {
			return $value;
		}

		if ( ! WC_Stripe_Order_Helper::get_instance()->is_stripe_gateway_order( $order ) ) {
			return $value;
		}

		$note = $order->get_customer_note();

		return '' === $note ? $value : $note;
	}
}
