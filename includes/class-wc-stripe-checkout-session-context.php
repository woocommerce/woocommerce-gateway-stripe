<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and validates local context for Stripe Checkout Sessions.
 */
class WC_Stripe_Checkout_Session_Context {
	/**
	 * Cache key prefix for Checkout Session ownership and amount context.
	 *
	 * @var string
	 */
	private const CONTEXT_CACHE_PREFIX = 'checkout_session_context_';

	/**
	 * Option key prefix for short-lived Checkout Session mutation locks.
	 *
	 * @var string
	 */
	private const MUTATION_LOCK_OPTION_PREFIX = 'wc_stripe_checkout_session_lock_';

	/**
	 * Stable marker for support/admin code to distinguish an automatic safety disable from a manual setting change.
	 *
	 * @var string
	 */
	private const ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION = 'wc_stripe_adaptive_pricing_session_amount_mismatch_detected';

	/**
	 * WooCommerce rewrites the session customer ID when checkout creates an account.
	 *
	 * @var string
	 */
	private const MIGRATED_GUEST_SESSION_ID_KEY = 'wc_stripe_migrated_guest_session_id';

	/**
	 * Context lifetime. Stripe Checkout Sessions expire after 24 hours by default.
	 *
	 * @var int
	 */
	private const CONTEXT_TTL = DAY_IN_SECONDS;

	/**
	 * Lock lifetime for coordinating cart updates with order linking. The update path holds the
	 * lock while waiting for Stripe, so this must not be shorter than WC_Stripe_API's request timeout.
	 *
	 * @var int
	 */
	private const MUTATION_LOCK_TTL = 70;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init_hooks(): void {
		add_action(
			'woocommerce_guest_session_to_user_id',
			[ self::class, 'remember_migrated_guest_session' ],
			10,
			2
		);
	}

	/**
	 * Returns the generic failure message for Checkout Session context validation.
	 *
	 * @return string
	 */
	public static function get_unavailable_message(): string {
		return __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' );
	}

	/**
	 * Get stored context for a Stripe Checkout Session.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return array|null
	 */
	public static function get_context( string $session_id ): ?array {
		$context = WC_Stripe_Database_Cache::get( self::get_context_cache_key( $session_id ) );
		return is_array( $context ) ? $context : null;
	}

	/**
	 * Check if an amount mismatch was detected.
	 *
	 * @return bool
	 */
	public static function was_amount_mismatch_detected(): bool {
		return 'yes' === get_option( self::ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION, 'no' );
	}

	/**
	 * Store context for a Stripe Checkout Session.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @param array  $context Checkout Session context.
	 * @return void
	 */
	public static function set_context( string $session_id, array $context ): void {
		if ( '' === $session_id ) {
			return;
		}

		$owner_keys = self::normalize_owner_keys( $context['owner_keys'] ?? null );
		$owner_key  = isset( $context['owner_key'] ) ? (string) $context['owner_key'] : '';

		if ( '' === $owner_key ) {
			$owner_keys = self::get_current_owner_keys();
			$owner_key  = $owner_keys[0] ?? '';
		} elseif ( [] === $owner_keys ) {
			$owner_keys = [ $owner_key ];
		}

		$context['session_id'] = $session_id;
		$context['updated_at'] = time();
		$context['created_at'] = $context['created_at'] ?? $context['updated_at'];
		$context['owner_key']  = $owner_key;
		$context['owner_keys'] = $owner_keys;
		$context['order_id']   = isset( $context['order_id'] ) ? (int) $context['order_id'] : 0;
		$context['amount']     = isset( $context['amount'] ) ? (int) $context['amount'] : 0;
		$context['currency']   = isset( $context['currency'] ) ? strtolower( (string) $context['currency'] ) : '';
		$context['locked_at']  = isset( $context['locked_at'] ) ? (int) $context['locked_at'] : 0;

		WC_Stripe_Database_Cache::set(
			self::get_context_cache_key( $session_id ),
			$context,
			self::CONTEXT_TTL
		);
	}

	/**
	 * Preserve the guest owner key WooCommerce replaces during checkout account creation.
	 *
	 * @param string $guest_session_id Former guest session ID.
	 * @param string $user_session_id Current user session ID.
	 * @return void
	 */
	public static function remember_migrated_guest_session( string $guest_session_id, string $user_session_id ): void {
		if (
			0 !== strpos( $guest_session_id, 't_' )
			|| ! WC()->session
			|| ! is_callable( [ WC()->session, 'get_customer_id' ] )
			|| ! is_callable( [ WC()->session, 'set' ] )
			|| (string) WC()->session->get_customer_id() !== (string) $user_session_id
		) {
			return;
		}

		WC()->session->set( self::MIGRATED_GUEST_SESSION_ID_KEY, $guest_session_id );
	}

	/**
	 * Store Checkout Session context for the current browser session.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @param array  $cart_context Current cart context.
	 * @return void
	 * @throws Exception When the current checkout session cannot be identified.
	 */
	public static function store_for_cart( string $session_id, array $cart_context ): void {
		$owner_keys = self::get_current_owner_keys();
		if ( [] === $owner_keys ) {
			throw new Exception( self::get_unavailable_message() );
		}

		$existing_context = self::get_context( $session_id ) ?? [];

		self::set_context(
			$session_id,
			array_merge(
				$existing_context,
				$cart_context,
				[
					'owner_key'  => $owner_keys[0],
					'owner_keys' => $owner_keys,
					'order_id'   => isset( $existing_context['order_id'] ) ? (int) $existing_context['order_id'] : 0,
				]
			)
		);
	}

	/**
	 * Delete stored context for a Stripe Checkout Session.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return void
	 */
	public static function delete_context( string $session_id ): void {
		if ( '' === $session_id ) {
			return;
		}

		WC_Stripe_Database_Cache::delete( self::get_context_cache_key( $session_id ) );
		self::release_mutation_lock( $session_id );
	}

	/**
	 * Run a callback while holding a Checkout Session mutation lock.
	 *
	 * @param string   $session_id Stripe Checkout Session ID.
	 * @param callable $callback Callback to run while the lock is held.
	 * @return mixed
	 * @throws Exception When the Checkout Session is currently locked.
	 */
	public static function with_mutation_lock( string $session_id, callable $callback ) {
		if ( ! self::acquire_mutation_lock( $session_id ) ) {
			throw new Exception( self::get_unavailable_message() );
		}

		try {
			return $callback();
		} finally {
			self::release_mutation_lock( $session_id );
		}
	}

	/**
	 * Validate that the current request may update a Checkout Session.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return void
	 * @throws Exception When the session is unknown, owned by another browser session, or already linked to an order.
	 */
	public static function validate_update_request( string $session_id ): void {
		$context = self::get_context( $session_id );
		if ( empty( $context ) ) {
			throw new Exception( self::get_unavailable_message() );
		}

		self::validate_owner( $context );

		if ( ! empty( $context['order_id'] ) ) {
			throw new Exception( self::get_unavailable_message() );
		}

		$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $session_id );
		if ( $order instanceof WC_Order ) {
			throw new Exception( self::get_unavailable_message() );
		}
	}

	/**
	 * Validate that a Checkout Session can be linked to the given order.
	 *
	 * @param string   $session_id Stripe Checkout Session ID.
	 * @param WC_Order $order WooCommerce order.
	 * @return array Stored Checkout Session context.
	 * @throws Exception When the Checkout Session context is missing or does not match the order.
	 */
	public static function validate_for_order( string $session_id, WC_Order $order ): array {
		$context = self::get_context( $session_id );
		if ( empty( $context ) ) {
			throw new Exception( self::get_unavailable_message() );
		}

		self::validate_owner( $context );

		$context_order_id = isset( $context['order_id'] ) ? (int) $context['order_id'] : 0;
		if ( $context_order_id > 0 && $context_order_id !== $order->get_id() ) {
			throw new Exception( self::get_unavailable_message() );
		}

		$order_currency = strtolower( $order->get_currency() );
		$order_amount   = WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $order_currency );

		if (
			(int) ( $context['amount'] ?? 0 ) !== $order_amount
			|| strtolower( (string) ( $context['currency'] ?? '' ) ) !== $order_currency
		) {
			self::disable_adaptive_pricing_after_amount_mismatch( $session_id, $order, $context, $order_amount, $order_currency );

			throw new Exception( __( 'The payment amount no longer matches the order total. Please refresh the page and try again.', 'woocommerce-gateway-stripe' ) );
		}

		return $context;
	}

	/**
	 * Mark the Checkout Session context as linked to an order.
	 *
	 * @param string   $session_id Stripe Checkout Session ID.
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	public static function mark_linked_to_order( string $session_id, WC_Order $order ): void {
		$context = self::get_context( $session_id );
		if ( empty( $context ) ) {
			return;
		}

		$context['order_id']  = $order->get_id();
		$context['locked_at'] = time();

		self::set_context( $session_id, $context );
	}

	/**
	 * Attempt to acquire the Checkout Session mutation lock.
	 *
	 * Best-effort: the lock only serializes the short update/link window so a stale validation
	 * result cannot race a concurrent cart update or order submission. The authoritative guard
	 * against amount manipulation is the order-id and amount/currency checks, so the rare race
	 * where two requests reclaim the same expired lock is acceptable.
	 *
	 * `add_option()` is atomic on the option name, so the common (uncrashed) path is race-free.
	 * A lock abandoned by a crashed request is reclaimed once MUTATION_LOCK_TTL has elapsed.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return bool True when the caller acquired the lock.
	 */
	private static function acquire_mutation_lock( string $session_id ): bool {
		$option_name = self::get_mutation_lock_option_name( $session_id );
		$now         = time();

		if ( add_option( $option_name, $now, '', false ) ) {
			return true;
		}

		$existing = (int) get_option( $option_name, 0 );
		if ( $existing > 0 && ( $now - $existing ) < self::MUTATION_LOCK_TTL ) {
			return false;
		}

		// Reclaim a lock abandoned by a crashed request; the loser of a concurrent
		// reclaim is rejected by add_option()'s atomic insert. Still best-effort.
		delete_option( $option_name );
		return add_option( $option_name, $now, '', false );
	}

	/**
	 * Release the Checkout Session mutation lock.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return void
	 */
	private static function release_mutation_lock( string $session_id ): void {
		delete_option( self::get_mutation_lock_option_name( $session_id ) );
	}

	/**
	 * Validate that the stored Checkout Session context belongs to the current browser session.
	 *
	 * @param array $context Stored Checkout Session context.
	 * @return void
	 * @throws Exception When the current browser session does not own the Checkout Session.
	 */
	private static function validate_owner( array $context ): void {
		$current_owner_keys = self::get_current_owner_keys();
		$stored_owner_keys  = self::normalize_owner_keys( $context['owner_keys'] ?? null );

		if ( isset( $context['owner_key'] ) && '' !== (string) $context['owner_key'] ) {
			$stored_owner_keys[] = (string) $context['owner_key'];
		}

		$stored_owner_keys = array_values( array_unique( $stored_owner_keys ) );

		if ( [] === $current_owner_keys || [] === $stored_owner_keys || [] === array_intersect( $current_owner_keys, $stored_owner_keys ) ) {
			throw new Exception( self::get_unavailable_message() );
		}
	}

	/**
	 * A stored Checkout Session total that disagrees with the order means the store may have an
	 * incompatible totals integration, so future shoppers should use the standard intent flow.
	 *
	 * @param string   $session_id Stripe Checkout Session ID.
	 * @param WC_Order $order WooCommerce order.
	 * @param array    $context Stored Checkout Session context.
	 * @param float|int $order_amount Order amount in Stripe minor units.
	 * @param string   $order_currency Order currency.
	 * @return void
	 */
	private static function disable_adaptive_pricing_after_amount_mismatch( string $session_id, WC_Order $order, array $context, $order_amount, string $order_currency ): void {
		update_option( self::ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION, 'yes', false );

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		if ( 'no' !== ( $stripe_settings['adaptive_pricing'] ?? 'no' ) ) {
			$stripe_settings['adaptive_pricing'] = 'no';
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}

		self::delete_context( $session_id );

		WC_Stripe_Logger::warning(
			'Adaptive Pricing disabled after Checkout Session amount mismatch.',
			[
				'checkout_session_id' => $session_id,
				'order_id'            => $order->get_id(),
				'session_amount'      => $context['amount'],
				'order_amount'        => $order_amount,
				'session_currency'    => $context['currency'],
				'order_currency'      => $order_currency,
			]
		);
	}

	/**
	 * Get the current browser/customer owner keys for a Checkout Session.
	 *
	 * @return string[]
	 */
	private static function get_current_owner_keys(): array {
		$owner_keys = [];

		if ( is_user_logged_in() ) {
			$session_token = wp_get_session_token();
			if ( is_string( $session_token ) && '' !== $session_token ) {
				// The auth-session token identifies this browser session; store only a digest because the raw token is credential-like.
				$owner_keys[] = 'wp-session:' . hash( 'sha256', $session_token );
			}
		}

		if ( WC()->session && is_callable( [ WC()->session, 'get_customer_id' ] ) ) {
			$customer_id = WC()->session->get_customer_id();
			if ( is_scalar( $customer_id ) && '' !== (string) $customer_id ) {
				$owner_keys[] = 'session:' . (string) $customer_id;
			}

			if ( is_callable( [ WC()->session, 'get' ] ) ) {
				$migrated_guest_session_id = WC()->session->get( self::MIGRATED_GUEST_SESSION_ID_KEY, '' );
				if ( is_scalar( $migrated_guest_session_id ) && 0 === strpos( (string) $migrated_guest_session_id, 't_' ) ) {
					$owner_keys[] = 'session:' . (string) $migrated_guest_session_id;
				}
			}
		}

		if ( is_user_logged_in() ) {
			$owner_keys[] = 'user:' . (string) get_current_user_id();
		}

		if ( WC()->customer instanceof WC_Customer && WC()->customer->get_id() ) {
			$owner_keys[] = 'customer:' . (string) WC()->customer->get_id();
		}

		return array_values( array_unique( $owner_keys ) );
	}

	/**
	 * Normalize owner keys to unique string values.
	 *
	 * @param mixed $owner_keys Owner keys.
	 * @return string[]
	 */
	private static function normalize_owner_keys( $owner_keys ): array {
		if ( ! is_array( $owner_keys ) ) {
			return [];
		}

		$owner_keys = array_filter(
			array_map(
				static function ( $owner_key ): string {
					return is_scalar( $owner_key ) ? (string) $owner_key : '';
				},
				$owner_keys
			)
		);

		return array_values( array_unique( $owner_keys ) );
	}

	/**
	 * Build the cache key for Checkout Session context.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return string
	 */
	private static function get_context_cache_key( string $session_id ): string {
		return self::CONTEXT_CACHE_PREFIX . md5( $session_id );
	}

	/**
	 * Build the option name for the Checkout Session mutation lock.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return string
	 */
	private static function get_mutation_lock_option_name( string $session_id ): string {
		return self::MUTATION_LOCK_OPTION_PREFIX . md5( $session_id );
	}
}
