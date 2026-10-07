<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Prevents a single checkout cart from being charged twice.
 *
 * Once Stripe charges a cart the order is paid, so WooCommerce core will not resume it and mints a
 * second order on a resubmit (see woocommerce/woocommerce#40964), charging the card again. Only the
 * gateway knows Stripe already charged this cart. Both mechanisms below are keyed on the cart, not
 * the order, because each resubmit is a different order:
 *
 * - A per-cart lock ({@see WC_Stripe_Option_Lock}) held across the charge, so two concurrent
 *   submissions serialize and only one reaches Stripe. A post-payment marker cannot catch this:
 *   both requests read "not yet paid" before either records anything.
 * - A short-lived record of the paid order, so a later resubmit is redirected to it instead of
 *   charging again. Cleared once the shopper reaches the order-received page, so a deliberate
 *   repurchase of the same items is not blocked.
 */
final class WC_Stripe_Duplicate_Payment_Prevention {

	/**
	 * Per-cart charge lock option name prefix.
	 *
	 * @var string
	 */
	private const LOCK_PREFIX = 'wc_stripe_checkout_charge_lock_';

	/**
	 * Paid-cart record transient name prefix.
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
	 * Query flag on the redirect to the already-paid order.
	 *
	 * @var string
	 */
	public const REDIRECT_QUERY_ARG = 'wc_stripe_duplicate_prevented';

	/**
	 * Lock TTL, in seconds. Covers the worst case of the charge retry loop while the lock is held:
	 * five attempts at the 70-second API timeout plus 10 seconds of backoff. A shorter TTL lets a
	 * concurrent resubmit reclaim the lock mid-charge and create a second intent. The cost is that
	 * a lock left behind by a request that died before its finally block blocks this cart for up to
	 * this long, which is safer than a second charge.
	 *
	 * @var int
	 */
	private const LOCK_TTL = 6 * MINUTE_IN_SECONDS;

	/**
	 * Returns the detection window in seconds; 0 disables the guard.
	 *
	 * @return int
	 */
	public static function get_detection_window(): int {
		/**
		 * Filters how long a paid cart is protected against a duplicate charge.
		 *
		 * @since 11.1.0
		 *
		 * @param int $window Seconds. Return 0 to disable duplicate-charge prevention.
		 */
		$window = (int) apply_filters( 'wc_stripe_duplicate_charge_detection_window', self::DEFAULT_WINDOW );

		return max( 0, $window );
	}

	/**
	 * Builds the key a resubmit of the same cart shares with the original attempt.
	 *
	 * Scoped by cart hash, billing email, and customer ID so a resubmit matches but a different
	 * shopper's identical cart does not. Empty when the guard cannot apply (no window, cart hash,
	 * or email).
	 *
	 * @param WC_Order $order The order being processed.
	 * @return string The md5 cart key, or '' when the guard does not apply.
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
	 * @param string $owner    The owner token from {@see acquire_lock()}.
	 * @return void
	 */
	public static function release_lock( string $cart_key, string $owner ): void {
		WC_Stripe_Option_Lock::release( self::LOCK_PREFIX . $cart_key, $owner );
	}

	/**
	 * Returns a still-paid order recently created from this cart, if one exists.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return WC_Order|null The earlier paid order to redirect to, or null when there is none.
	 */
	public static function get_recent_paid_order( WC_Order $order ): ?WC_Order {
		$cart_key = self::get_cart_key( $order );
		if ( '' === $cart_key ) {
			return null;
		}

		$record = self::get_record( $cart_key );
		if ( null === $record ) {
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
		if ( ! $paid_order instanceof WC_Order || ! self::has_been_charged( $paid_order ) ) {
			return null;
		}

		// Cancelled/refunded/pending orders keep date_paid, so a status check is also needed to let a
		// genuine repurchase through. Failed is not excluded: a paid order marked failed after the
		// charge (for example by an error in later processing) still holds the shopper's money.
		if ( $paid_order->has_status( [ OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::PENDING ] ) ) {
			return null;
		}

		return $paid_order;
	}

	/**
	 * Whether the current request comes from the shopper who paid the recorded order for this cart.
	 *
	 * The cart key only holds the cart, email, and customer ID, so two guests with the same email
	 * and cart share it. Redirecting a different guest to the paid order would show them its
	 * details, so a guest must also be in the browser session that paid. A logged-in customer is
	 * already scoped by customer ID.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return bool
	 */
	public static function is_paid_by_current_shopper( WC_Order $order ): bool {
		if ( $order->get_customer_id() > 0 ) {
			return true;
		}

		$record = self::get_record( self::get_cart_key( $order ) );
		if ( null === $record ) {
			return false;
		}

		$current_session = self::get_current_session_id();

		return '' !== $current_session && hash_equals( (string) ( $record['session'] ?? '' ), $current_session );
	}

	/**
	 * Returns the paid-cart record for a cart key, or null when there is none.
	 *
	 * @param string $cart_key The key from {@see get_cart_key()}.
	 * @return array|null
	 */
	private static function get_record( string $cart_key ): ?array {
		if ( '' === $cart_key ) {
			return null;
		}

		$record = get_transient( self::RECORD_PREFIX . $cart_key );
		if ( ! is_array( $record ) || empty( $record['order_id'] ) ) {
			return null;
		}

		return $record;
	}

	/**
	 * Returns the WooCommerce session customer ID of the current request, or '' without a session.
	 *
	 * Webhooks and other server-side contexts have no session; their records then never match a guest.
	 *
	 * @return string
	 */
	private static function get_current_session_id(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->session instanceof WC_Session || ! is_callable( [ WC()->session, 'get_customer_id' ] ) ) {
			return '';
		}

		return (string) WC()->session->get_customer_id();
	}

	/**
	 * Whether Stripe holds the shopper's money for this order.
	 *
	 * A captured charge sets date_paid. An authorization (manual capture) or a charge Stripe still
	 * reports as pending only puts the order on-hold with the charge as its transaction ID. The
	 * transaction ID keeps out on-hold orders that were never charged, like BACS or cheque.
	 *
	 * @param WC_Order $order The order to check.
	 * @return bool
	 */
	private static function has_been_charged( WC_Order $order ): bool {
		return (bool) $order->get_date_paid()
			|| ( $order->has_status( OrderStatus::ON_HOLD ) && '' !== (string) $order->get_transaction_id() );
	}

	/**
	 * Explains on the order-received page why a resubmit landed on an earlier order.
	 *
	 * Only text changes, and the page itself still requires the order key, so the query flag
	 * needs no further checks. A Store API notice would not work here: it clears notices after
	 * process_payment().
	 *
	 * @param mixed $text The order-received text from the filter.
	 * @return mixed
	 */
	public static function filter_order_received_text( $text ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_string( $text ) || empty( $_GET[ self::REDIRECT_QUERY_ARG ] ) ) {
			return $text;
		}

		return __( 'We already received your payment for this cart, so you were not charged again. Here are your order details.', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Records that this cart produced a paid order.
	 *
	 * A no-op unless the order was charged or authorized. Safe on both the sync charge and the async
	 * return; it reads the order's stored cart hash, not the live cart.
	 *
	 * @param WC_Order $order The order that was paid.
	 * @return void
	 */
	public static function record_paid_order( WC_Order $order ): void {
		if ( ! self::has_been_charged( $order ) ) {
			return;
		}

		$cart_key = self::get_cart_key( $order );
		if ( '' === $cart_key ) {
			return;
		}

		set_transient(
			self::RECORD_PREFIX . $cart_key,
			[
				'order_id' => $order->get_id(),
				'paid_at'  => time(),
				'session'  => self::get_current_session_id(),
			],
			self::get_detection_window()
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
