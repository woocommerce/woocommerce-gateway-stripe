<?php

/**
 * Tests for the WC_Stripe_Option_Lock class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Option_Lock
 *
 * WC_Stripe_Option_Lock_Test Class.
 */
class WC_Stripe_Option_Lock_Test extends WP_UnitTestCase {

	/**
	 * The lock name to use for the test. Note that it needs to be a supported lock name for the code to work.
	 *
	 * @var string
	 */
	private const LOCK_NAME = 'wc_stripe_express_checkout_password_cleanup_lock';
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
	 * Every lock name the plugin uses is accepted, so a missing allow-list entry fails here
	 * rather than silently disabling that lock in production.
	 *
	 * @param string $name A lock option name used by the plugin.
	 * @dataProvider provide_supported_lock_names
	 */
	public function test_supported_lock_names_can_be_acquired_and_released( string $name ): void {
		$owner = WC_Stripe_Option_Lock::acquire( $name, self::TTL );

		try {
			$this->assertIsString( $owner );
			$this->assertSame( $owner, $this->read_lock( $name ) );

			WC_Stripe_Option_Lock::release( $name, $owner );

			$this->assertNull( $this->read_lock( $name ) );
		} finally {
			$this->remove_lock( $name );
		}
	}

	/**
	 * Data provider for {@see test_supported_lock_names_can_be_acquired_and_released()}.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_supported_lock_names(): array {
		$test_cases = [];

		$supported_lock_names = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Option_Lock::class, 'SUPPORTED_LOCK_NAMES', 'array' );

		foreach ( $supported_lock_names as $lock_name ) {
			$test_cases[ 'exact lock ' . $lock_name ] = [ $lock_name ];
		}

		$supported_lock_prefixes = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Option_Lock::class, 'SUPPORTED_LOCK_PREFIXES', 'array' );

		foreach ( $supported_lock_prefixes as $lock_prefix ) {
			$test_cases[ 'lock prefix ' . $lock_prefix ] = [ $lock_prefix . 'test_123' ];
		}

		return $test_cases;
	}

	/**
	 * A name outside the allow-lists is refused, since the daily cleanup would never remove a
	 * row left behind under it.
	 *
	 * @param string $name An unsupported option name.
	 * @dataProvider provide_unsupported_lock_names
	 */
	public function test_acquire_refuses_unsupported_lock_names( string $name ): void {
		$this->assertNull( WC_Stripe_Option_Lock::acquire( $name, self::TTL ) );
		$this->assertNull( $this->read_lock( $name ) );
	}

	/**
	 * Release must not delete an arbitrary option, even when the caller passes its exact value.
	 *
	 * @param string $name An unsupported option name.
	 * @dataProvider provide_unsupported_lock_names
	 */
	public function test_release_ignores_unsupported_lock_names( string $name ): void {
		$owner = time() . ':some-owner';
		$this->seed_lock( $owner, $name );

		WC_Stripe_Option_Lock::release( $name, $owner );

		try {
			$this->assertSame( $owner, $this->read_lock( $name ) );
		} finally {
			$this->remove_lock( $name );
		}
	}

	/**
	 * Forced release must not delete an arbitrary option.
	 *
	 * @param string $name An unsupported option name.
	 * @dataProvider provide_unsupported_lock_names
	 */
	public function test_force_release_ignores_unsupported_lock_names( string $name ): void {
		$value = time() . ':some-owner';
		$this->seed_lock( $value, $name );

		WC_Stripe_Option_Lock::force_release( $name );

		try {
			$this->assertSame( $value, $this->read_lock( $name ) );
		} finally {
			$this->remove_lock( $name );
		}
	}

	/**
	 * Data provider for the unsupported lock name tests.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_unsupported_lock_names(): array {
		return [
			'unknown name'               => [ 'wc_stripe_test_lock' ],
			'fixed-name look-alike'      => [ 'wc_stripe_agentic_sync_lock_backup' ],
			'prefix not at the start'    => [ 'other_wc_stripe_checkout_session_lock_abc' ],
			'prefix with different case' => [ 'WC_STRIPE_TRANSACTION_LOCK_ch_123' ],
			'unrelated core option'      => [ 'blogname_lock' ],
		];
	}

	/**
	 * Writes a lock row directly so the options cache is not involved.
	 *
	 * @param string $value The value another request stored.
	 * @param string $name  The lock option name.
	 */
	private function seed_lock( string $value, string $name = self::LOCK_NAME ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => $name,
				'option_value' => $value,
				'autoload'     => 'off',
			]
		);
	}

	/**
	 * Removes a lock row directly so we can ensure we have a clean state after the test.
	 *
	 * @param string $name The lock option name.
	 */
	private function remove_lock( string $name ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, [ 'option_name' => $name ] );
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
			'abandoned password cleanup lock' => [ 'wc_stripe_express_checkout_password_cleanup_lock', 2 * DAY_IN_SECONDS, false, true ],
			'abandoned transaction lock'      => [ 'wc_stripe_transaction_lock_ch_123', 2 * DAY_IN_SECONDS, false, true ],
			'fixed-name look-alike kept'      => [ 'wc_stripe_agentic_sync_lock_backup', 2 * DAY_IN_SECONDS, false, false ],
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
	 * When a full batch is deleted, the cleanup enqueues another batch instead of waiting a day.
	 *
	 * @return void
	 */
	public function test_cleanup_reenqueues_another_batch_after_a_full_batch(): void {
		global $wpdb;

		as_unschedule_all_actions( WC_Stripe_Option_Lock::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' );

		// Seed a full batch of abandoned locks so one run deletes the batch limit and may leave rows behind.
		$stale  = time() - 2 * DAY_IN_SECONDS;
		$rows   = [];
		$params = [];
		for ( $i = 0; $i < 500; $i++ ) {
			$rows[]   = '(%s, %s, %s)';
			$params[] = 'wc_stripe_checkout_session_lock_' . $i;
			$params[] = $stale . ':owner';
			$params[] = 'off';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES " . implode( ',', $rows ), $params ) );

		WC_Stripe_Option_Lock::cleanup_stale_locks();

		$this->assertTrue( as_has_scheduled_action( WC_Stripe_Option_Lock::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' ) );

		as_unschedule_all_actions( WC_Stripe_Option_Lock::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' );
	}

	/**
	 * When fewer than a full batch is deleted, the cleanup does not enqueue another batch.
	 *
	 * @return void
	 */
	public function test_cleanup_does_not_reenqueue_below_a_full_batch(): void {
		global $wpdb;

		as_unschedule_all_actions( WC_Stripe_Option_Lock::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' );

		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => 'wc_stripe_checkout_session_lock_single',
				'option_value' => ( time() - 2 * DAY_IN_SECONDS ) . ':owner',
				'autoload'     => 'off',
			]
		);

		WC_Stripe_Option_Lock::cleanup_stale_locks();

		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Option_Lock::CLEANUP_ACTION, [], 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Reads the lock row straight from the database.
	 *
	 * @param string $name The lock option name.
	 * @return string|null The stored owner value, or null when no lock row exists.
	 */
	private function read_lock( string $name = self::LOCK_NAME ): ?string {
		global $wpdb;

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		return is_string( $value ) ? $value : null;
	}
}
