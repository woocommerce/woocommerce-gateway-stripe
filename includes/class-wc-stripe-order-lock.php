<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Primitives for a short-lived lock scoped to a single order, stored as an expiry timestamp in
 * order meta.
 *
 * Unlike {@see WC_Stripe_Option_Lock}, this lock lives on the order object, so it travels with the
 * order and works under HPOS. It has no owner token and is a check-then-set rather than an atomic
 * compare-and-swap: it guards a resource already scoped to one order (payment or refund processing)
 * against overlapping runs, and callers accept the narrow read-then-write window.
 *
 * These are stateless helpers on purpose: the order-scoped orchestration (which meta key, when to
 * check) stays on {@see WC_Stripe_Order_Helper}, whose overridable methods third-party subclasses
 * may replace. The value stored is the expiry timestamp; older payment locks stored
 * `<expiry>|<token>`, so {@see self::is_active()} reads the expiry from the first `|`-delimited
 * segment and a bare timestamp parses unchanged, keeping a lock written by an older deploy readable
 * during an upgrade.
 */
final class WC_Stripe_Order_Lock {

	/**
	 * Default lifetime of a lock, after which it is treated as abandoned.
	 *
	 * @var int
	 */
	public const DEFAULT_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Writes the lock, marking the order locked until now plus the TTL.
	 *
	 * @param WC_Order $order    The order to lock.
	 * @param string   $meta_key The meta key the lock is stored under.
	 * @param int      $ttl      Seconds the lock stays active before it is treated as abandoned.
	 */
	public static function set( WC_Order $order, string $meta_key, int $ttl = self::DEFAULT_TTL ): void {
		// Stored as a string so the value round-trips as the same type it is read back as.
		$order->update_meta_data( $meta_key, (string) ( time() + $ttl ) );
		$order->save_meta_data();
	}

	/**
	 * Clears the lock.
	 *
	 * The lock carries no owner token, so this always clears it; callers only release a lock they
	 * acquired earlier in the same flow.
	 *
	 * @param WC_Order $order    The order to unlock.
	 * @param string   $meta_key The meta key the lock is stored under.
	 */
	public static function release( WC_Order $order, string $meta_key ): void {
		$order->delete_meta_data( $meta_key );
		$order->save_meta_data();
	}

	/**
	 * Returns the raw stored lock value for the order.
	 *
	 * Re-reads meta from storage first so a value written by a concurrent request is not masked by
	 * a stale in-memory copy on this order object.
	 *
	 * @param WC_Order $order    The order to read the lock from.
	 * @param string   $meta_key The meta key the lock is stored under.
	 * @return mixed The stored value, or an empty string when no lock is set.
	 */
	public static function get( WC_Order $order, string $meta_key ) {
		$order->read_meta_data( true );
		return $order->get_meta( $meta_key, true );
	}

	/**
	 * Reports whether a stored lock value represents an active (non-expired) lock.
	 *
	 * @param mixed $stored_value The value returned by {@see self::get()}.
	 * @return bool True while the value is set and its expiry is in the future.
	 */
	public static function is_active( $stored_value ): bool {
		if ( ! $stored_value ) {
			return false;
		}

		$expiration = (int) explode( '|', (string) $stored_value )[0];

		return time() <= $expiration;
	}
}
