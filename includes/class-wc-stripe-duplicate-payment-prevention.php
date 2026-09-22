<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Prevents a single checkout cart from being charged twice.
 *
 * WooCommerce core resumes an interrupted checkout only while its order still needs payment
 * (see woocommerce/woocommerce#40964). Once Stripe has charged the cart, the order is paid, so
 * a resubmit, whether a reload after a lost response, a duplicate renewal cart, or two tabs
 * submitting at once, makes core mint a *second* order and Stripe charges the card again. Core
 * cannot see this: only the gateway knows Stripe already charged this cart.
 *
 * This guard closes the gap with two independent mechanisms keyed on the cart, not the order,
 * because each resubmit is a different order:
 *
 * - A per-cart lock ({@see WC_Stripe_Option_Lock}) held across the charge. Two concurrent
 *   submissions of the same cart serialize on it, so only one reaches Stripe; the loser is
 *   turned away before charging. This is the case a post-payment marker alone cannot catch,
 *   since both requests read "not yet paid" before either records anything.
 * - A short-lived record of the paid order, written under that lock and read under it on the
 *   next attempt. A sequential resubmit after the first charge finished sees the record and is
 *   redirected to the order already paid instead of charging again.
 *
 * The record is cleared once the shopper reaches the order-received page: seeing the
 * confirmation proves the first order succeeded, so a later repurchase of the same items is
 * deliberate, not a duplicate.
 */
final class WC_Stripe_Duplicate_Payment_Prevention {

	/**
	 * Prefix for the per-cart charge lock option name.
	 *
	 * @var string
	 */
	private const LOCK_PREFIX = 'wc_stripe_checkout_charge_lock_';

	/**
	 * Prefix for the transient that records a paid cart.
	 *
	 * @var string
	 */
	private const RECORD_PREFIX = 'wc_stripe_paid_cart_';

	/**
	 * Default detection window, in seconds.
	 *
	 * @var int
	 */
	private const DEFAULT_WINDOW = 120;

	/**
	 * How long the charge lock is held before it is treated as abandoned, in seconds.
	 *
	 * Comfortably longer than a create-and-confirm round trip to Stripe, so a slow charge does
	 * not release the lock to a concurrent resubmit while it is still running.
	 *
	 * @var int
	 */
	private const LOCK_TTL = 90;

	/**
	 * Returns the duplicate-charge detection window in seconds.
	 *
	 * Filterable via `wc_stripe_duplicate_charge_detection_window`; return 0 to disable the
	 * guard entirely.
	 *
	 * @return int Seconds; 0 disables the guard.
	 */
	public static function get_detection_window(): int {
		/**
		 * Filters how long a paid cart is protected against a duplicate charge.
		 *
		 * @since 9.9.0
		 *
		 * @param int $window Seconds. Return 0 to disable duplicate-charge prevention.
		 */
		$window = (int) apply_filters( 'wc_stripe_duplicate_charge_detection_window', self::DEFAULT_WINDOW );

		return max( 0, $window );
	}

	/**
	 * Builds the cart key that a resubmit of the same cart shares with the original attempt.
	 *
	 * Keyed on the order's cart hash plus the shopper (billing email and customer ID) so a
	 * resubmit matches while a different shopper's identical cart does not. Returns an empty
	 * string when the guard cannot apply: no detection window, no cart hash (add-payment-method,
	 * some pay-for-order requests), or no billing email to scope by.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return string The md5 cart key, or '' when the guard does not apply to this order.
	 */
	public static function get_cart_key( WC_Order $order ): string {
		if ( 0 === self::get_detection_window() ) {
			return '';
		}

		$cart_hash = (string) $order->get_cart_hash();
		$email     = strtolower( trim( (string) $order->get_billing_email() ) );
		if ( '' === $cart_hash || '' === $email ) {
			return '';
		}

		return md5( $cart_hash . '|' . $email . '|' . (int) $order->get_customer_id() );
	}

	/**
	 * Acquires the per-cart charge lock.
	 *
	 * @param string $cart_key The key from {@see get_cart_key()}.
	 * @return string|null The owner token to release with, or null when another request holds it.
	 */
	public static function acquire_lock( string $cart_key ): ?string {
		return WC_Stripe_Option_Lock::acquire( self::LOCK_PREFIX . $cart_key, self::LOCK_TTL );
	}

	/**
	 * Releases the per-cart charge lock, only while the caller still owns it.
	 *
	 * @param string $cart_key The key from {@see get_cart_key()}.
	 * @param string $owner    The owner token returned by {@see acquire_lock()}.
	 * @return void
	 */
	public static function release_lock( string $cart_key, string $owner ): void {
		WC_Stripe_Option_Lock::release( self::LOCK_PREFIX . $cart_key, $owner );
	}

	/**
	 * Returns a still-paid order recently created from this cart, if one exists.
	 *
	 * The recorded order must be different from the one being processed, still hold its paid
	 * state (a cancelled, refunded, or failed order keeps its `date_paid`, so status is checked
	 * too, not just `date_paid`), and fall inside the detection window.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return WC_Order|null The earlier paid order to redirect to, or null when there is none.
	 */
	public static function get_recent_paid_order( WC_Order $order ): ?WC_Order {
		$cart_key = self::get_cart_key( $order );
		if ( '' === $cart_key ) {
			return null;
		}

		$record = get_transient( self::RECORD_PREFIX . $cart_key );
		if ( ! is_array( $record ) || empty( $record['order_id'] ) ) {
			return null;
		}

		$recorded_order_id = (int) $record['order_id'];
		if ( $recorded_order_id === $order->get_id() ) {
			return null;
		}

		$paid_at = isset( $record['paid_at'] ) ? (int) $record['paid_at'] : 0;
		if ( $paid_at <= 0 || ( time() - $paid_at ) >= self::get_detection_window() ) {
			return null;
		}

		$paid_order = wc_get_order( $recorded_order_id );
		if ( ! $paid_order instanceof WC_Order || ! $paid_order->get_date_paid() ) {
			return null;
		}

		// A refund, cancellation, or failure after payment keeps date_paid populated but must not
		// block a genuine repurchase.
		if ( $paid_order->has_status( [ OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::FAILED, OrderStatus::PENDING ] ) ) {
			return null;
		}

		return $paid_order;
	}

	/**
	 * Records that this cart produced a paid order.
	 *
	 * A no-op unless the order is actually paid (`date_paid`), so authorize-only orders, which
	 * never set it, are not covered. Safe to call from both the synchronous charge and the
	 * browser-side async completion (3DS/SCA, redirect APMs); it reads the order's stored cart
	 * hash rather than the live cart, which may already be empty on the async leg.
	 *
	 * @param WC_Order $order The order that was paid.
	 * @return void
	 */
	public static function record_paid_order( WC_Order $order ): void {
		if ( ! $order->get_date_paid() ) {
			return;
		}

		$cart_key = self::get_cart_key( $order );
		if ( '' === $cart_key ) {
			return;
		}

		$window = self::get_detection_window();
		set_transient(
			self::RECORD_PREFIX . $cart_key,
			[
				'order_id' => $order->get_id(),
				'paid_at'  => time(),
			],
			$window
		);
	}

	/**
	 * Clears the paid-cart record for this order's cart.
	 *
	 * @param WC_Order $order The order whose cart record should be cleared.
	 * @return void
	 */
	public static function clear_paid_record( WC_Order $order ): void {
		$cart_key = self::get_cart_key( $order );
		if ( '' === $cart_key ) {
			return;
		}

		delete_transient( self::RECORD_PREFIX . $cart_key );
	}
}
