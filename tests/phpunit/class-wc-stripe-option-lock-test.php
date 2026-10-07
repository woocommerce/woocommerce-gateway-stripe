<?php

/**
 * Tests for the WC_Stripe_Option_Lock class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Option_Lock
 *
 * WC_Stripe_Option_Lock_Test Class.
 */
class WC_Stripe_Option_Lock_Test extends WP_UnitTestCase {

	private const LOCK_NAME = 'wc_stripe_test_lock';
	private const TTL       = MINUTE_IN_SECONDS;

	/**
	 * A free lock is acquired and the owner value lands in the options table.
	 */
	public function test_acquire_takes_a_free_lock(): void {
		$owner = WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, self::TTL );

		$this->assertIsString( $owner );
		$this->assertMatchesRegularExpression( '/^\d+:[0-9a-f-]{36}$/', $owner );
		$this->assertSame( $owner, $this->read_lock() );
	}

	/**
	 * A lock held by another request is respected or reclaimed depending on its age, including
	 * the bare-timestamp values written by releases that predate the owner token.
	 *
	 * @param string $seeded_value     The value another request stored in the lock row.
	 * @param bool   $expect_acquired  Whether the caller should get the lock.
	 * @dataProvider provide_held_lock_values
	 */
	public function test_acquire_respects_or_reclaims_a_held_lock( string $seeded_value, bool $expect_acquired ): void {
		$this->seed_lock( $seeded_value );

		$owner = WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, self::TTL );

		if ( $expect_acquired ) {
			$this->assertIsString( $owner );
			$this->assertSame( $owner, $this->read_lock(), 'The reclaimed row must carry the new owner.' );
		} else {
			$this->assertNull( $owner );
			$this->assertSame( $seeded_value, $this->read_lock(), 'A live lock must not be touched.' );
		}
	}

	/**
	 * Data provider for `test_acquire_respects_or_reclaims_a_held_lock`.
	 *
	 * @return array
	 */
	public function provide_held_lock_values(): array {
		$fresh = time() - 5;
		$stale = time() - self::TTL - 5;

		return [
			'fresh lock with token'  => [ $fresh . ':other-request', false ],
			'stale lock with token'  => [ $stale . ':crashed-request', true ],
			'fresh legacy timestamp' => [ (string) $fresh, false ],
			'stale legacy timestamp' => [ (string) $stale, true ],
			'unparseable value'      => [ 'garbage', true ],
		];
	}

	/**
	 * A lock read through the options API before it was written, or while it held a stale owner,
	 * must show the new owner after acquisition even though the row is written directly.
	 *
	 * @param string|null $seeded_value A stale lock value to seed, or null for a free lock.
	 * @dataProvider provide_cached_option_states
	 */
	public function test_acquire_refreshes_the_options_cache( ?string $seeded_value ): void {
		if ( null !== $seeded_value ) {
			$this->seed_lock( $seeded_value );
		}
		// Prime the options cache: a miss lands in `notoptions`, a hit caches the stale value.
		$this->assertSame( $seeded_value ?? false, get_option( self::LOCK_NAME ) );

		$owner = WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, self::TTL );

		$this->assertIsString( $owner );
		$this->assertSame( $owner, get_option( self::LOCK_NAME ) );
	}

	/**
	 * Data provider for `test_acquire_refreshes_the_options_cache`.
	 *
	 * @return array
	 */
	public function provide_cached_option_states(): array {
		return [
			'cached miss on a free lock' => [ null ],
			'cached stale owner'         => [ ( time() - self::TTL - 5 ) . ':crashed-request' ],
		];
	}

	/**
	 * Two requests can read the same expired lock. Only the one whose compare-and-swap lands
	 * gets it; the other must not believe it holds the lock.
	 */
	public function test_acquire_loses_a_contested_stale_reclaim(): void {
		global $wpdb;

		$rival_owner = time() . ':rival-request';
		$this->seed_lock( ( time() - self::TTL - 5 ) . ':crashed-request' );

		// The rival reclaims the stale lock after this request has read it but before its swap runs.
		$rival_reclaims = static function ( $query ) use ( &$rival_reclaims, $wpdb, $rival_owner ) {
			if ( is_string( $query ) && 0 === stripos( ltrim( $query ), 'UPDATE' ) && false !== strpos( $query, self::LOCK_NAME ) ) {
				remove_filter( 'query', $rival_reclaims );
				$wpdb->update( $wpdb->options, [ 'option_value' => $rival_owner ], [ 'option_name' => self::LOCK_NAME ] );
			}
			return $query;
		};
		add_filter( 'query', $rival_reclaims );

		try {
			$owner = WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, self::TTL );
		} finally {
			remove_filter( 'query', $rival_reclaims );
		}

		$this->assertNull( $owner );
		$this->assertSame( $rival_owner, $this->read_lock() );
	}

	/**
	 * Releasing with the owner value removes the row and drops the cached option value.
	 */
	public function test_release_removes_an_owned_lock(): void {
		$owner = WC_Stripe_Option_Lock::acquire( self::LOCK_NAME, self::TTL );
		// Prime the options cache the way a consumer that reads the lock through the options API would.
		$this->assertSame( $owner, get_option( self::LOCK_NAME ) );

		WC_Stripe_Option_Lock::release( self::LOCK_NAME, $owner );

		$this->assertNull( $this->read_lock() );
		$this->assertFalse( get_option( self::LOCK_NAME ) );
	}

	/**
	 * A holder that lost its lock to a reclaim must not remove the new owner's lock.
	 */
	public function test_release_ignores_a_lock_owned_by_another_request(): void {
		$rival_owner = time() . ':rival-request';
		$this->seed_lock( $rival_owner );

		WC_Stripe_Option_Lock::release( self::LOCK_NAME, ( time() - 10 ) . ':stale-owner' );

		$this->assertSame( $rival_owner, $this->read_lock() );
	}

	/**
	 * Forced release removes the lock whoever holds it.
	 */
	public function test_force_release_removes_any_lock(): void {
		$this->seed_lock( time() . ':other-request' );

		WC_Stripe_Option_Lock::force_release( self::LOCK_NAME );

		$this->assertNull( $this->read_lock() );
	}

	/**
	 * Writes a lock row directly so the options cache is not involved.
	 *
	 * @param string $value The value another request stored.
	 */
	private function seed_lock( string $value ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => self::LOCK_NAME,
				'option_value' => $value,
				'autoload'     => 'off',
			]
		);
	}

	/**
	 * The cleanup removes lock rows older than a day under the known lock names, and keeps live
	 * locks and options that only look similar.
	 *
	 * @param string $name           The option name.
	 * @param int    $age            Seconds since the lock was taken.
	 * @param bool   $bare_timestamp Whether the value is a bare timestamp (older deploys).
	 * @param bool   $expect_deleted Whether the cleanup removes the row.
	 *
	 * @dataProvider provide_cleanup_rows
	 *
	 * @return void
	 */
	public function test_delete_stale_locks_removes_only_abandoned_lock_rows( string $name, int $age, bool $bare_timestamp, bool $expect_deleted ): void {
		global $wpdb;

		$locked_at = time() - $age;
		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => $name,
				'option_value' => $bare_timestamp ? (string) $locked_at : $locked_at . ':' . wp_generate_uuid4(),
				'autoload'     => 'off',
			]
		);

		WC_Stripe_Option_Lock::delete_stale_locks( 50 );

		$exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		$this->assertSame( ! $expect_deleted, $exists );
	}

	/**
	 * Data provider for test_delete_stale_locks_removes_only_abandoned_lock_rows.
	 *
	 * @return array<string, array{0: string, 1: int, 2: bool, 3: bool}>
	 */
	public function provide_cleanup_rows(): array {
		return [
			'abandoned session lock'          => [ 'wc_stripe_checkout_session_lock_abc', 2 * DAY_IN_SECONDS, false, true ],
			'live session lock'               => [ 'wc_stripe_checkout_session_lock_def', 30, false, false ],
			'abandoned bare-timestamp lock'   => [ 'wc_stripe_user_customer_lock_42', 2 * DAY_IN_SECONDS, true, true ],
			'abandoned fixed-name lock'       => [ 'wc_stripe_agentic_sync_lock', 2 * DAY_IN_SECONDS, false, true ],
			'old option outside the prefixes' => [ 'wc_stripe_other_lock_1', 2 * DAY_IN_SECONDS, false, false ],
			'underscore is not a wildcard'    => [ 'wc_stripe_checkout_sessionXlock_1', 2 * DAY_IN_SECONDS, false, false ],
		];
	}

	/**
	 * The daily cleanup is scheduled once, and unscheduling removes it.
	 *
	 * @return void
	 */
	public function test_daily_cleanup_is_scheduled_once_and_can_be_unscheduled(): void {
		WC_Stripe_Option_Lock::unschedule_daily_cleanup();

		WC_Stripe_Option_Lock::maybe_schedule_daily_cleanup();
		WC_Stripe_Option_Lock::maybe_schedule_daily_cleanup();

		$pending = as_get_scheduled_actions(
			[
				'hook'   => WC_Stripe_Option_Lock::CLEANUP_ACTION,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		$this->assertCount( 1, $pending );

		WC_Stripe_Option_Lock::unschedule_daily_cleanup();
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Option_Lock::CLEANUP_ACTION ) );
	}

	/**
	 * The cleanup stops at the limit, so one run cannot delete an unbounded number of rows.
	 *
	 * @return void
	 */
	public function test_delete_stale_locks_respects_the_limit(): void {
		global $wpdb;

		foreach ( [ 'a', 'b', 'c' ] as $suffix ) {
			$wpdb->insert(
				$wpdb->options,
				[
					'option_name'  => 'wc_stripe_checkout_session_lock_' . $suffix,
					'option_value' => ( time() - 2 * DAY_IN_SECONDS ) . ':owner',
					'autoload'     => 'off',
				]
			);
		}

		$this->assertSame( 2, WC_Stripe_Option_Lock::delete_stale_locks( 2 ) );
		$this->assertSame( 1, WC_Stripe_Option_Lock::delete_stale_locks( 2 ) );
	}

	/**
	 * Reads the lock row straight from the database.
	 *
	 * @return string|null The stored owner value, or null when no lock row exists.
	 */
	private function read_lock(): ?string {
		global $wpdb;

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_NAME ) );

		return is_string( $value ) ? $value : null;
	}
}
