<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Tests for WC_Stripe_Duplicate_Payment_Prevention.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Duplicate_Payment_Prevention
 */
class WC_Stripe_Duplicate_Payment_Prevention_Test extends WP_UnitTestCase {

	private const CART_HASH = 'cart-hash-abc';
	private const EMAIL     = 'shopper@example.com';

	/**
	 * Removes any window filter left by a test.
	 */
	public function tear_down() {
		remove_all_filters( 'wc_stripe_duplicate_charge_detection_window' );
		parent::tear_down();
	}

	/**
	 * The detection window defaults to two minutes and honours the filter, clamped to a
	 * non-negative value.
	 *
	 * @param mixed $filtered  The value a filter returns, or null to leave the default.
	 * @param int   $expected  The expected window.
	 * @dataProvider provide_detection_windows
	 */
	public function test_get_detection_window( $filtered, int $expected ): void {
		if ( null !== $filtered ) {
			add_filter(
				'wc_stripe_duplicate_charge_detection_window',
				static function () use ( $filtered ) {
					return $filtered;
				}
			);
		}

		$this->assertSame( $expected, WC_Stripe_Duplicate_Payment_Prevention::get_detection_window() );
	}

	/**
	 * Data provider for test_get_detection_window.
	 *
	 * @return array
	 */
	public function provide_detection_windows(): array {
		return [
			'default'            => [ null, 120 ],
			'custom window'      => [ 300, 300 ],
			'disabled with zero' => [ 0, 0 ],
			'negative clamped'   => [ -10, 0 ],
		];
	}

	/**
	 * The cart key is stable for two submissions of the same cart by the same shopper, distinct
	 * for a different shopper, and empty when the guard cannot apply.
	 */
	public function test_get_cart_key_matches_resubmits_and_excludes_others(): void {
		$first  = $this->make_order( self::CART_HASH, self::EMAIL );
		$resend = $this->make_order( self::CART_HASH, self::EMAIL );
		$other  = $this->make_order( self::CART_HASH, 'someone-else@example.com' );

		$key = WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $first );

		$this->assertNotSame( '', $key );
		$this->assertSame( $key, WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $resend ), 'A resubmit of the same cart shares the key.' );
		$this->assertNotSame( $key, WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $other ), 'A different shopper does not collide.' );
	}

	/**
	 * The cart key is empty when the guard cannot apply.
	 *
	 * @param string $cart_hash The order cart hash.
	 * @param string $email     The order billing email.
	 * @param bool   $disable   Whether to disable the window via the filter.
	 * @dataProvider provide_unkeyable_orders
	 */
	public function test_get_cart_key_returns_empty_when_not_applicable( string $cart_hash, string $email, bool $disable ): void {
		if ( $disable ) {
			add_filter( 'wc_stripe_duplicate_charge_detection_window', '__return_zero' );
		}

		$order = $this->make_order( $cart_hash, $email );

		$this->assertSame( '', WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $order ) );
	}

	/**
	 * Data provider for test_get_cart_key_returns_empty_when_not_applicable.
	 *
	 * @return array
	 */
	public function provide_unkeyable_orders(): array {
		return [
			'no cart hash'     => [ '', self::EMAIL, false ],
			'no billing email' => [ self::CART_HASH, '', false ],
			'window disabled'  => [ self::CART_HASH, self::EMAIL, true ],
		];
	}

	/**
	 * A paid cart is recorded and returned for a later submission of the same cart, and never for
	 * the same order that was recorded.
	 */
	public function test_record_and_get_recent_paid_order(): void {
		$paid = $this->make_paid_order( self::CART_HASH, self::EMAIL );

		WC_Stripe_Duplicate_Payment_Prevention::record_paid_order( $paid );

		$resend = $this->make_order( self::CART_HASH, self::EMAIL );
		$found  = WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $resend );

		$this->assertInstanceOf( WC_Order::class, $found );
		$this->assertSame( $paid->get_id(), $found->get_id() );

		$this->assertNull(
			WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $paid ),
			'The recorded order must not match against itself.'
		);
	}

	/**
	 * Only the shopper who paid may be sent to the paid order. Guests share a cart key when the
	 * email and cart match, so a guest must also be in the session that paid; a logged-in
	 * customer is already scoped by customer ID.
	 *
	 * @param string $paid_session    WC session customer ID when the order was paid ('' for none).
	 * @param string $current_session WC session customer ID of the resubmit ('' for none).
	 * @param int    $customer_id     Customer ID on both orders.
	 * @param bool   $expected        Whether the resubmit counts as the same shopper.
	 * @dataProvider provide_paying_shoppers
	 */
	public function test_is_paid_by_current_shopper( string $paid_session, string $current_session, int $customer_id, bool $expected ): void {
		$original_session = WC()->session;

		try {
			$paid = $this->make_order( self::CART_HASH, self::EMAIL );
			$paid->set_customer_id( $customer_id );
			$paid->payment_complete();

			$this->set_session_customer_id( $paid_session );
			WC_Stripe_Duplicate_Payment_Prevention::record_paid_order( wc_get_order( $paid->get_id() ) );

			$resend = $this->make_order( self::CART_HASH, self::EMAIL );
			$resend->set_customer_id( $customer_id );
			$resend->save();

			$this->set_session_customer_id( $current_session );
			$this->assertSame( $expected, WC_Stripe_Duplicate_Payment_Prevention::is_paid_by_current_shopper( $resend ) );
		} finally {
			WC()->session = $original_session;
		}
	}

	/**
	 * Data provider for test_is_paid_by_current_shopper.
	 *
	 * @return array
	 */
	public function provide_paying_shoppers(): array {
		return [
			'guest in the same session'             => [ 't_guest_a', 't_guest_a', 0, true ],
			'guest in another session'              => [ 't_guest_a', 't_guest_b', 0, false ],
			'guest, paid without a session'         => [ '', 't_guest_a', 0, false ],
			'guest, resubmit without a session'     => [ 't_guest_a', '', 0, false ],
			'logged-in customer in another session' => [ 't_guest_a', 't_guest_b', 7, true ],
		];
	}

	/**
	 * Sets the WC session customer ID, or removes the session when empty.
	 *
	 * @param string $customer_id The session customer ID.
	 * @return void
	 */
	private function set_session_customer_id( string $customer_id ): void {
		if ( '' === $customer_id ) {
			WC()->session = null;
			return;
		}

		$session    = new WC_Session_Handler();
		$reflection = new ReflectionClass( $session );
		$property   = $reflection->getProperty( '_customer_id' );
		$property->setAccessible( true );
		$property->setValue( $session, $customer_id );
		WC()->session = $session;
	}

	/**
	 * An unpaid order is never recorded, so a resubmit is not blocked.
	 */
	public function test_record_paid_order_ignores_unpaid_order(): void {
		$unpaid = $this->make_order( self::CART_HASH, self::EMAIL );

		WC_Stripe_Duplicate_Payment_Prevention::record_paid_order( $unpaid );

		$resend = $this->make_order( self::CART_HASH, self::EMAIL );
		$this->assertNull( WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $resend ) );
	}

	/**
	 * An authorized charge (manual capture) puts the order on-hold with the charge ID but no paid
	 * date, and must block a resubmit like a captured one. An on-hold order with no charge, such
	 * as BACS, must not.
	 *
	 * @param string $transaction_id The transaction ID on the on-hold order.
	 * @param bool   $expected       Whether a resubmit is blocked.
	 *
	 * @dataProvider provide_on_hold_orders
	 *
	 * @return void
	 */
	public function test_on_hold_order_blocks_resubmit_only_when_charged( string $transaction_id, bool $expected ): void {
		$authorized = $this->make_order( self::CART_HASH, self::EMAIL );
		$authorized->set_transaction_id( $transaction_id );
		$authorized->set_status( OrderStatus::ON_HOLD );
		$authorized->save();
		$this->assertNull( $authorized->get_date_paid(), 'On-hold must not set a paid date for this test to mean anything.' );

		WC_Stripe_Duplicate_Payment_Prevention::record_paid_order( $authorized );

		$found = WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $this->make_order( self::CART_HASH, self::EMAIL ) );
		if ( $expected ) {
			$this->assertInstanceOf( WC_Order::class, $found );
			$this->assertSame( $authorized->get_id(), $found->get_id() );
		} else {
			$this->assertNull( $found );
		}
	}

	/**
	 * Data provider for test_on_hold_order_blocks_resubmit_only_when_charged.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function provide_on_hold_orders(): array {
		return [
			'authorized charge'  => [ 'ch_authorized', true ],
			'no charge, as BACS' => [ '', false ],
		];
	}

	/**
	 * A recorded order that later lost its paid standing (cancelled, refunded, pending, all of which
	 * keep date_paid) must not block a repurchase; a still-paid one must. A paid order later marked
	 * failed was still charged, so it blocks too.
	 *
	 * @param string $status   The status the recorded order ends in.
	 * @param bool   $expected Whether it should still be returned as a duplicate.
	 * @dataProvider provide_recorded_order_statuses
	 */
	public function test_get_recent_paid_order_respects_status( string $status, bool $expected ): void {
		$paid = $this->make_paid_order( self::CART_HASH, self::EMAIL );
		WC_Stripe_Duplicate_Payment_Prevention::record_paid_order( $paid );

		$paid->set_status( $status );
		$paid->save();

		$resend = $this->make_order( self::CART_HASH, self::EMAIL );
		$found  = WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $resend );

		if ( $expected ) {
			$this->assertInstanceOf( WC_Order::class, $found );
			$this->assertSame( $paid->get_id(), $found->get_id() );
		} else {
			$this->assertNull( $found );
		}
	}

	/**
	 * Data provider for test_get_recent_paid_order_respects_status.
	 *
	 * @return array
	 */
	public function provide_recorded_order_statuses(): array {
		return [
			'processing blocks' => [ OrderStatus::PROCESSING, true ],
			'completed blocks'  => [ OrderStatus::COMPLETED, true ],
			'on-hold blocks'    => [ OrderStatus::ON_HOLD, true ],
			'cancelled allows'  => [ OrderStatus::CANCELLED, false ],
			'refunded allows'   => [ OrderStatus::REFUNDED, false ],
			'failed blocks'     => [ OrderStatus::FAILED, true ],
			'pending allows'    => [ OrderStatus::PENDING, false ],
		];
	}

	/**
	 * A record older than the detection window no longer matches.
	 */
	public function test_get_recent_paid_order_ignores_stale_record(): void {
		$paid = $this->make_paid_order( self::CART_HASH, self::EMAIL );
		$key  = WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $paid );

		// Seed a record whose paid_at is older than the window.
		set_transient(
			'wc_stripe_paid_cart_' . $key,
			[
				'order_id' => $paid->get_id(),
				'paid_at'  => time() - WC_Stripe_Duplicate_Payment_Prevention::get_detection_window() - 5,
			],
			HOUR_IN_SECONDS
		);

		$resend = $this->make_order( self::CART_HASH, self::EMAIL );
		$this->assertNull( WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $resend ) );
	}

	/**
	 * Clearing the record stops it matching a later submission.
	 */
	public function test_clear_paid_record(): void {
		$paid = $this->make_paid_order( self::CART_HASH, self::EMAIL );
		WC_Stripe_Duplicate_Payment_Prevention::record_paid_order( $paid );

		WC_Stripe_Duplicate_Payment_Prevention::clear_paid_record( $paid );

		$resend = $this->make_order( self::CART_HASH, self::EMAIL );
		$this->assertNull( WC_Stripe_Duplicate_Payment_Prevention::get_recent_paid_order( $resend ) );
	}

	/**
	 * The per-cart lock is exclusive: a second acquire fails until the first is released.
	 */
	public function test_lock_is_exclusive_until_released(): void {
		$order = $this->make_order( self::CART_HASH, self::EMAIL );
		$key   = WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $order );

		$owner = WC_Stripe_Duplicate_Payment_Prevention::acquire_lock( $key );
		$this->assertIsString( $owner );
		$this->assertNull( WC_Stripe_Duplicate_Payment_Prevention::acquire_lock( $key ), 'A held lock cannot be re-acquired.' );

		WC_Stripe_Duplicate_Payment_Prevention::release_lock( $key, $owner );
		$this->assertIsString( WC_Stripe_Duplicate_Payment_Prevention::acquire_lock( $key ), 'A released lock is free again.' );
	}

	/**
	 * The lock must outlive the charge retry loop (five 70-second API attempts plus backoff), or a
	 * concurrent resubmit could reclaim it mid-charge. Once past that, an abandoned lock is reclaimed.
	 *
	 * @dataProvider provide_lock_age
	 */
	public function test_lock_is_held_for_the_worst_case_charge_duration( int $age, bool $reclaimable ): void {
		$order = $this->make_order( self::CART_HASH, self::EMAIL );
		$key   = WC_Stripe_Duplicate_Payment_Prevention::get_cart_key( $order );

		$owner = WC_Stripe_Duplicate_Payment_Prevention::acquire_lock( $key );
		$this->assertIsString( $owner );

		global $wpdb;
		$wpdb->update(
			$wpdb->options,
			[ 'option_value' => ( time() - $age ) . ':stale-owner' ],
			[ 'option_name' => 'wc_stripe_checkout_charge_lock_' . $key ]
		);

		$this->assertSame( $reclaimable, is_string( WC_Stripe_Duplicate_Payment_Prevention::acquire_lock( $key ) ) );
	}

	/**
	 * Lock ages, in seconds, and whether a new request may reclaim the lock at that age.
	 *
	 * @return array<string, array{int, bool}>
	 */
	public function provide_lock_age(): array {
		return [
			'held during the retry loop' => [ 5 * 70 + 5, false ],
			'reclaimed once abandoned'   => [ 7 * MINUTE_IN_SECONDS, true ],
		];
	}

	/**
	 * Builds an order carrying a given cart hash and billing email.
	 *
	 * @param string $cart_hash The cart hash to store on the order.
	 * @param string $email     The billing email to store on the order.
	 * @return WC_Order
	 */
	private function make_order( string $cart_hash, string $email ): WC_Order {
		$order = new WC_Order();
		// WC 10.8+ blocks payment_complete() without checkout evidence.
		$order->set_created_via( 'checkout' );
		$order->set_cart_hash( $cart_hash );
		$order->set_billing_email( $email );
		$order->save();

		return $order;
	}

	/**
	 * The order-received text explains a blocked resubmit only when the redirect carries the flag,
	 * and leaves other filter values alone.
	 *
	 * @param mixed  $text     The incoming filter value.
	 * @param bool   $flagged  Whether the request has the redirect flag.
	 * @param string $expected 'explained' or 'unchanged'.
	 *
	 * @dataProvider provide_order_received_texts
	 *
	 * @return void
	 */
	public function test_filter_order_received_text( $text, bool $flagged, string $expected ): void {
		if ( $flagged ) {
			$_GET[ WC_Stripe_Duplicate_Payment_Prevention::REDIRECT_QUERY_ARG ] = '1';
		}

		try {
			$result = WC_Stripe_Duplicate_Payment_Prevention::filter_order_received_text( $text );
		} finally {
			unset( $_GET[ WC_Stripe_Duplicate_Payment_Prevention::REDIRECT_QUERY_ARG ] );
		}

		if ( 'explained' === $expected ) {
			$this->assertStringContainsString( 'you were not charged again', $result );
		} else {
			$this->assertSame( $text, $result );
		}
	}

	/**
	 * Data provider for test_filter_order_received_text.
	 *
	 * @return array<string, array{0: mixed, 1: bool, 2: string}>
	 */
	public function provide_order_received_texts(): array {
		return [
			'flagged redirect'           => [ 'Thank you. Your order has been received.', true, 'explained' ],
			'normal order received'      => [ 'Thank you. Your order has been received.', false, 'unchanged' ],
			'non-string from a callback' => [ null, true, 'unchanged' ],
		];
	}

	/**
	 * Builds a paid order carrying a given cart hash and billing email.
	 *
	 * @param string $cart_hash The cart hash to store on the order.
	 * @param string $email     The billing email to store on the order.
	 * @return WC_Order
	 */
	private function make_paid_order( string $cart_hash, string $email ): WC_Order {
		$order = $this->make_order( $cart_hash, $email );
		$order->payment_complete();

		return wc_get_order( $order->get_id() );
	}
}
