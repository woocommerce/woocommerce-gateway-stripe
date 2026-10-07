<?php

/**
 * Tests for the WC_Stripe_Order_Lock class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Order_Lock
 *
 * WC_Stripe_Order_Lock_Test Class.
 */
class WC_Stripe_Order_Lock_Test extends WP_UnitTestCase {

	private const LOCK_KEY = '_stripe_test_lock';

	/**
	 * set() stores a future expiry that get() reads back and is_active() reports as locked.
	 */
	public function test_set_stores_a_future_expiry(): void {
		$order = WC_Helper_Order::create_order();

		WC_Stripe_Order_Lock::set( $order, self::LOCK_KEY );

		$this->assertGreaterThan( time(), (int) WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY ) );
		$this->assertTrue( WC_Stripe_Order_Lock::is_active( WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY ) ) );
	}

	/**
	 * set() honours a custom TTL when computing the expiry.
	 */
	public function test_set_honours_a_custom_ttl(): void {
		$order = WC_Helper_Order::create_order();

		WC_Stripe_Order_Lock::set( $order, self::LOCK_KEY, HOUR_IN_SECONDS );

		$expiry = (int) WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY );
		$this->assertGreaterThan( time() + MINUTE_IN_SECONDS, $expiry );
	}

	/**
	 * release() clears the stored lock so it reads back empty and inactive.
	 */
	public function test_release_clears_the_lock(): void {
		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Lock::set( $order, self::LOCK_KEY );

		WC_Stripe_Order_Lock::release( $order, self::LOCK_KEY );

		$this->assertEmpty( WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY ) );
		$this->assertFalse( WC_Stripe_Order_Lock::is_active( WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY ) ) );
	}

	/**
	 * is_active() parses both a bare expiry timestamp and the legacy `<expiry>|<token>` value the
	 * same way, so payment and refund locks no longer read their stored value differently.
	 *
	 * @param string $stored   The value stored in the lock meta.
	 * @param bool   $expected Whether the value should read as an active lock.
	 * @dataProvider provide_stored_lock_values
	 */
	public function test_is_active_parses_stored_values( string $stored, bool $expected ): void {
		$this->assertSame( $expected, WC_Stripe_Order_Lock::is_active( $stored ) );
	}

	/**
	 * Data provider for `test_is_active_parses_stored_values`.
	 *
	 * @return array
	 */
	public function provide_stored_lock_values(): array {
		$fresh = time() + MINUTE_IN_SECONDS;
		$stale = time() - MINUTE_IN_SECONDS;

		return [
			'fresh bare timestamp'    => [ (string) $fresh, true ],
			'stale bare timestamp'    => [ (string) $stale, false ],
			'fresh legacy with token' => [ $fresh . '|token', true ],
			'stale legacy with token' => [ $stale . '|token', false ],
			'empty value'             => [ '', false ],
			'unparseable value'       => [ 'garbage', false ],
		];
	}

	/**
	 * get() re-reads meta from storage, so a value written behind the in-memory copy is still seen.
	 */
	public function test_get_reads_through_stale_in_memory_meta(): void {
		$order = WC_Helper_Order::create_order();

		// Write directly to the store, then confirm get() surfaces it on the same object.
		$stored = wc_get_order( $order->get_id() );
		WC_Stripe_Order_Lock::set( $stored, self::LOCK_KEY );

		$this->assertNotEmpty( WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY ) );
	}

	/**
	 * Locks under different meta keys are independent: releasing one leaves the other in place.
	 */
	public function test_locks_are_scoped_per_meta_key(): void {
		$order      = WC_Helper_Order::create_order();
		$second_key = '_stripe_test_lock_other';

		WC_Stripe_Order_Lock::set( $order, self::LOCK_KEY );
		WC_Stripe_Order_Lock::set( $order, $second_key );

		WC_Stripe_Order_Lock::release( $order, self::LOCK_KEY );

		$this->assertFalse( WC_Stripe_Order_Lock::is_active( WC_Stripe_Order_Lock::get( $order, self::LOCK_KEY ) ) );
		$this->assertTrue( WC_Stripe_Order_Lock::is_active( WC_Stripe_Order_Lock::get( $order, $second_key ) ) );
	}
}
