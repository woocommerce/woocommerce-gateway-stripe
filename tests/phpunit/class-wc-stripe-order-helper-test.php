<?php

/**
 * Class WC_Stripe_Order_Helper
 *
 * @package WooCommerce/Stripe/WC_Stripe_Order_Helper
 *
 * Class WC_Stripe_Order_Helper tests.
 */
class WC_Stripe_Order_Helper_Test extends WP_UnitTestCase {
	/**
	 * Order helper instance.
	 *
	 * @var WC_Stripe_Order_Helper
	 */
	protected $helper;

	/**
	 * @inheritDoc
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure the helper is reset before each test.
		$this->helper = new WC_Stripe_Order_Helper();
	}

	/**
	 * A paid status or the redirect-processed flag both count as settled.
	 *
	 * @param string $status             Order status.
	 * @param bool   $redirect_processed Whether the redirect-processed flag is set.
	 * @param bool   $expected           Expected result.
	 * @dataProvider provide_order_payment_settled_cases
	 */
	public function test_is_order_payment_settled( string $status, bool $redirect_processed, bool $expected ): void {
		$order = WC_Helper_Order::create_order();
		$order->set_status( $status );
		$order->save();
		if ( $redirect_processed ) {
			$this->helper->update_stripe_upe_redirect_processed( $order, true );
		}

		$this->assertSame( $expected, $this->helper->is_order_payment_settled( $order ) );
	}

	/**
	 * Data provider for `test_is_order_payment_settled`.
	 *
	 * @return array
	 */
	public function provide_order_payment_settled_cases(): array {
		return [
			'pending'                     => [ 'pending', false, false ],
			'failed'                      => [ 'failed', false, false ],
			'pending, redirect processed' => [ 'pending', true, true ],
			'processing'                  => [ 'processing', false, true ],
			'completed'                   => [ 'completed', false, true ],
			'on-hold'                     => [ 'on-hold', false, true ],
		];
	}

	/**
	 * Tests for getters and setters.
	 *
	 * @return void
	 */
	public function test_properties(): void {
		$order = WC_Helper_Order::create_order();

		// Tests for `is_payment_awaiting_action`, `set_payment_awaiting_action`, and `remove_payment_awaiting_action`.
		$this->helper->set_payment_awaiting_action( $order );
		$this->assertTrue( $this->helper->is_payment_awaiting_action( $order ) );

		$this->helper->remove_payment_awaiting_action( $order );
		$this->assertFalse( $this->helper->is_payment_awaiting_action( $order ) );

		// Tests for `update_stripe_fee`, `get_stripe_fee`, `delete_stripe_fee`,
		// `update_stripe_net`, `get_stripe_net`, and `delete_stripe_net`.
		$this->helper->update_stripe_fee( $order, 100 );
		$this->helper->update_stripe_net( $order, 100 );

		$this->assertEquals( 100, $this->helper->get_stripe_fee( $order ) );
		$this->assertEquals( 100, $this->helper->get_stripe_net( $order ) );

		$this->helper->delete_stripe_fee( $order );
		$this->helper->delete_stripe_net( $order );
		$order->save_meta_data();

		$this->assertEmpty( $this->helper->get_stripe_fee( $order ) );
		$this->assertEmpty( $this->helper->get_stripe_net( $order ) );
	}

	/**
	 * Tests for `get_stripe_refund_id_for_refund`, `update_stripe_refund_id_for_refund`,
	 * and `delete_stripe_refund_id_for_refund`.
	 *
	 * @param bool $pass_null Whether to exercise the null-argument contract instead of a real refund.
	 * @return void
	 * @dataProvider provide_test_stripe_refund_id_for_refund
	 */
	public function test_stripe_refund_id_for_refund( bool $pass_null ): void {
		if ( $pass_null ) {
			$this->assertFalse( $this->helper->get_stripe_refund_id_for_refund( null ) );
			$this->assertFalse( $this->helper->update_stripe_refund_id_for_refund( null, 're_null' ) );
			$this->assertFalse( $this->helper->delete_stripe_refund_id_for_refund( null ) );
			return;
		}

		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 5.00,
			]
		);

		$this->assertEmpty( $this->helper->get_stripe_refund_id_for_refund( $refund ) );

		// The update does not save, mirroring the order-level methods' contract.
		$this->helper->update_stripe_refund_id_for_refund( $refund, 're_123' );
		$this->assertSame( 're_123', $this->helper->get_stripe_refund_id_for_refund( $refund ) );

		$refund->save_meta_data();
		$reloaded = wc_get_order( $refund->get_id() );
		$this->assertSame( 're_123', $this->helper->get_stripe_refund_id_for_refund( $reloaded ) );

		// The parent order's meta is not touched by the per-refund methods.
		$this->assertEmpty( $this->helper->get_stripe_refund_id( wc_get_order( $order->get_id() ) ) );

		$this->helper->delete_stripe_refund_id_for_refund( $reloaded );
		$reloaded->save_meta_data();
		$this->assertEmpty( $this->helper->get_stripe_refund_id_for_refund( wc_get_order( $refund->get_id() ) ) );
	}

	/**
	 * Data provider for `test_stripe_refund_id_for_refund`.
	 *
	 * @return array
	 */
	public function provide_test_stripe_refund_id_for_refund(): array {
		return [
			'real refund record' => [ 'pass_null' => false ],
			'null refund'        => [ 'pass_null' => true ],
		];
	}

	/**
	 * Tests for `get_refunds_with_stripe_refund_ids` and `delete_stripe_refund_ids_from_refunds`.
	 *
	 * @return void
	 */
	public function test_refunds_with_stripe_refund_ids(): void {
		$order = WC_Helper_Order::create_order();

		// No refunds at all.
		$this->assertSame( [], $this->helper->get_refunds_with_stripe_refund_ids( $order ) );

		$tagged_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 5.00,
			]
		);
		$this->helper->update_stripe_refund_id_for_refund( $tagged_refund, 're_1' );
		$tagged_refund->save_meta_data();

		$untagged_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 7.00,
			]
		);

		$order = wc_get_order( $order->get_id() );

		// Only the tagged record is returned.
		$found = $this->helper->get_refunds_with_stripe_refund_ids( $order );
		$this->assertCount( 1, $found );
		$this->assertSame( $tagged_refund->get_id(), current( $found )->get_id() );

		// Bulk deletion erases and persists the tagged record's ID, leaving the other record alone.
		$this->helper->delete_stripe_refund_ids_from_refunds( $order );

		$this->assertEmpty( $this->helper->get_stripe_refund_id_for_refund( wc_get_order( $tagged_refund->get_id() ) ) );
		$this->assertSame( [], $this->helper->get_refunds_with_stripe_refund_ids( wc_get_order( $order->get_id() ) ) );
		$this->assertInstanceOf( WC_Order_Refund::class, wc_get_order( $untagged_refund->get_id() ) );
	}

	/**
	 * Tests for `lock_order_refund`, `get_order_existing_refund_lock`, `unlock_order_refund`,
	 * `lock_order_payment`, `get_order_existing_payment_lock`, and `unlock_order_payment`.
	 *
	 * @return void
	 */
	public function test_lockers(): void {
		// setup
		$order = WC_Helper_Order::create_order();

		// refund
		$this->helper->lock_order_refund( $order );
		$this->assertTrue( $this->helper->get_order_existing_refund_lock( $order ) > 0 );
		$this->helper->unlock_order_refund( $order );
		$this->assertEmpty( $this->helper->get_order_existing_refund_lock( $order ) );

		// payment
		$this->helper->lock_order_payment( $order );
		$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', (string) $this->helper->get_order_existing_payment_lock( $order ) );
		$this->helper->unlock_order_payment( $order );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Reads the order's owner row, translated to the "{expiry}|{token}" lock value it stands for.
	 *
	 * @param WC_Order $order The order.
	 * @return string|null The lock value, the raw row when it cannot be translated, or null when there is no row.
	 */
	private function get_payment_lock_owner_row( WC_Order $order ) {
		global $wpdb;

		$row = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM %i WHERE option_name = %s',
				$wpdb->options,
				'wc_stripe_payment_lock_owner_' . $order->get_id()
			)
		);

		if ( is_string( $row ) && preg_match( '/^([0-9]+):(.+)$/', $row, $matches ) ) {
			return ( (int) $matches[1] + 5 * MINUTE_IN_SECONDS ) . '|' . $matches[2];
		}

		return $row;
	}

	/**
	 * Writes the order's owner row for a "{expiry}|{token}" lock value, or a raw value when it is not one.
	 *
	 * @param WC_Order    $order The order.
	 * @param string|null $value Lock value to store, or null to delete the row.
	 */
	private function set_payment_lock_owner_row( WC_Order $order, ?string $value ): void {
		global $wpdb;

		$option_name = 'wc_stripe_payment_lock_owner_' . $order->get_id();

		// WC_Stripe_Option_Lock stores when the lock was taken, not when it expires.
		if ( null !== $value && preg_match( '/^([0-9]+)\|(.+)$/', $value, $matches ) ) {
			$value = ( (int) $matches[1] - 5 * MINUTE_IN_SECONDS ) . ':' . $matches[2];
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s', $wpdb->options, $option_name ) );

		if ( null !== $value ) {
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)',
					$wpdb->options,
					$option_name,
					$value,
					'no'
				)
			);
		}
	}

	/**
	 * Tests that the acquired lock value is the one stored in both the order meta and the owner row.
	 */
	public function test_acquire_order_payment_lock_returns_the_persisted_owner(): void {
		$order      = WC_Helper_Order::create_order();
		$owned_lock = $this->helper->acquire_order_payment_lock( $order );

		$this->assertIsString( $owned_lock );
		$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', $owned_lock );
		$this->assertSame( $owned_lock, $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame( $owned_lock, $this->get_payment_lock_owner_row( $order ) );
		$this->assertTrue( $this->helper->is_order_payment_lock_owned( $order, $owned_lock ) );

		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );

		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertNull( $this->get_payment_lock_owner_row( $order ) );
	}

	/**
	 * Tests that renewing an owned lock moves both the owner row and the order meta to the new value.
	 */
	public function test_renew_order_payment_lock_if_owned_replaces_both_stores(): void {
		$order      = WC_Helper_Order::create_order();
		$owned_lock = $this->helper->acquire_order_payment_lock( $order );

		$this->assertIsString( $owned_lock );

		$order->update_meta_data( '_unsaved_extension_meta', 'preserved' );
		$renewed_lock = $this->helper->renew_order_payment_lock_if_owned( $order, $owned_lock );

		$this->assertIsString( $renewed_lock );
		$this->assertNotSame( $owned_lock, $renewed_lock );
		$this->assertSame( $renewed_lock, $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) ) );
		$this->assertSame( $renewed_lock, $this->get_payment_lock_owner_row( $order ) );
		$this->assertFalse( $this->helper->is_order_payment_lock_owned( $order, $owned_lock ) );
		$this->assertTrue( $this->helper->is_order_payment_lock_owned( $order, $renewed_lock ) );
		// The caller's unsaved meta is neither lost nor saved.
		$this->assertSame( 'preserved', $order->get_meta( '_unsaved_extension_meta', true ) );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_unsaved_extension_meta', true ) );

		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );
		$this->assertTrue( $this->helper->is_order_payment_lock_owned( $order, $renewed_lock ) );

		$this->helper->unlock_order_payment_if_owned( $order, $renewed_lock );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Tests that a request whose lock was taken over cannot renew it and leaves the new owner in place.
	 */
	public function test_renew_order_payment_lock_if_owned_preserves_a_replacement_owner(): void {
		$order            = WC_Helper_Order::create_order();
		$owned_lock       = $this->helper->acquire_order_payment_lock( $order );
		$replacement_lock = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();

		$this->assertIsString( $owned_lock );

		$this->set_payment_lock_owner_row( $order, $replacement_lock );
		$replacement_order = wc_get_order( $order->get_id() );
		$replacement_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
		$replacement_order->save_meta_data();

		$this->assertFalse( $this->helper->renew_order_payment_lock_if_owned( $order, $owned_lock ) );
		$this->assertSame( $replacement_lock, $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame( $replacement_lock, $this->get_payment_lock_owner_row( $order ) );

		$replacement_order->delete_meta_data( '_stripe_lock_payment' );
		$replacement_order->save_meta_data();
		$this->set_payment_lock_owner_row( $order, null );
	}

	/**
	 * Tests that a subclass reporting a lock it did not record is treated as not acquired.
	 */
	public function test_acquire_order_payment_lock_fails_closed_when_an_override_records_no_owner(): void {
		$order  = WC_Helper_Order::create_order();
		$helper = new class() extends WC_Stripe_Order_Helper {
			/** @inheritDoc */
			public function lock_order_payment( WC_Order $order ): bool {
				return false;
			}
		};

		$previous_logger = WC_Stripe_Logger::$logger;
		$logger          = $this->getMockBuilder( WC_Logger::class )->disableOriginalConstructor()->getMock();
		$logger->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'without recording an owner' ), $this->anything() );
		WC_Stripe_Logger::$logger = $logger;

		try {
			$this->assertFalse( $helper->acquire_order_payment_lock( $order ) );
		} finally {
			WC_Stripe_Logger::$logger = $previous_logger;
		}
	}

	/**
	 * Tests that an unlock from a request that does not own the lock leaves an active lock alone.
	 */
	public function test_unlock_order_payment_without_a_token_preserves_an_active_owner(): void {
		$order        = WC_Helper_Order::create_order();
		$owner_helper = new WC_Stripe_Order_Helper();
		$late_helper  = new WC_Stripe_Order_Helper();

		$this->assertFalse( $owner_helper->lock_order_payment( $order ) );
		$owned_lock = (string) $owner_helper->get_order_existing_payment_lock( $order );

		$late_helper->unlock_order_payment( wc_get_order( $order->get_id() ) );

		$this->assertTrue( $owner_helper->is_order_payment_lock_owned( $order, $owned_lock ) );

		$owner_helper->unlock_order_payment( $order );
		$this->assertEmpty( $owner_helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * A worker that crashes leaves an expired "{expiry}|{token}" value in both the
	 * owner row and the order meta. A later token-less unlock must clear both.
	 */
	public function test_unlock_order_payment_without_a_token_clears_an_expired_owned_lock(): void {
		$order        = WC_Helper_Order::create_order();
		$expired_lock = ( time() - 1 ) . '|' . wp_generate_uuid4();

		$this->set_payment_lock_owner_row( $order, $expired_lock );
		$order->update_meta_data( '_stripe_lock_payment', $expired_lock );
		$order->save_meta_data();

		( new WC_Stripe_Order_Helper() )->unlock_order_payment( wc_get_order( $order->get_id() ) );

		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) ) );
		$this->assertNull( $this->get_payment_lock_owner_row( $order ) );
	}

	/**
	 * Tests that an unlock from a request that never took the lock leaves the owner row alone, so a
	 * concurrent request is not told the order is locked.
	 */
	public function test_unlock_order_payment_without_a_lock_does_not_claim_the_owner_row(): void {
		global $wpdb;

		$order       = WC_Helper_Order::create_order();
		$row_queries = [];
		$capture     = function ( $query ) use ( &$row_queries, $order ) {
			if ( false !== strpos( $query, 'wc_stripe_payment_lock_owner_' . $order->get_id() ) ) {
				$row_queries[] = $query;
			}
			return $query;
		};
		add_filter( 'query', $capture );

		try {
			( new WC_Stripe_Order_Helper() )->unlock_order_payment( wc_get_order( $order->get_id() ) );
		} finally {
			remove_filter( 'query', $capture );
		}

		$this->assertSame( [], $row_queries );
	}

	/**
	 * Tests that an acquisition that fails after writing the meta removes that meta, so no
	 * unreleasable lock is left on the order.
	 */
	public function test_failed_acquisition_removes_the_meta_it_wrote(): void {
		$order  = WC_Helper_Order::create_order();
		$helper = new class() extends WC_Stripe_Order_Helper {
			/** @var int */
			private $reads = 0;

			/**
			 * Reports a different value on the read that verifies the write, so the acquisition fails.
			 *
			 * @param WC_Order $order The order.
			 * @return mixed
			 */
			public function get_order_existing_payment_lock( WC_Order $order ) {
				$lock = parent::get_order_existing_payment_lock( $order );

				return 2 === ++$this->reads ? 'replaced-by-another-request' : $lock;
			}
		};

		$this->assertFalse( $helper->acquire_order_payment_lock( $order ) );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) ) );
		$this->assertNull( $this->get_payment_lock_owner_row( $order ) );
	}

	/**
	 * Tests that a token-less unlock clears a legacy bare-timestamp lock only once it has expired.
	 *
	 * @dataProvider provide_legacy_payment_locks
	 *
	 * @param int  $expiry_offset  Seconds from now at which the legacy lock expires.
	 * @param bool $expect_cleared Whether the unlock should clear it.
	 */
	public function test_unlock_order_payment_without_a_token_only_clears_expired_legacy_metadata( int $expiry_offset, bool $expect_cleared ): void {
		// Providers run at discovery, so the timestamp is built here.
		$legacy_lock = time() + $expiry_offset;
		$order       = WC_Helper_Order::create_order();
		$order->update_meta_data( '_stripe_lock_payment', $legacy_lock );
		$order->save_meta_data();

		$this->helper->unlock_order_payment( $order );

		if ( $expect_cleared ) {
			$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
		} else {
			$this->assertSame( (string) $legacy_lock, (string) $this->helper->get_order_existing_payment_lock( $order ) );
		}
		$this->assertNull( $this->get_payment_lock_owner_row( $order ) );
	}

	/**
	 * Provides legacy bare-timestamp lock offsets and whether a token-less unlock should clear them.
	 *
	 * @return array<string, array{int, bool}>
	 */
	public function provide_legacy_payment_locks(): array {
		return [
			'active legacy lock'  => [ 5 * MINUTE_IN_SECONDS, false ],
			'expired legacy lock' => [ -1, true ],
		];
	}

	/**
	 * Tests that an unreadable owner row is taken over, as WC_Stripe_Option_Lock does for every lock,
	 * while an active lock in the order meta still blocks the payment.
	 */
	public function test_lock_order_payment_takes_over_a_malformed_owner_row_but_honors_the_meta(): void {
		$order = WC_Helper_Order::create_order();

		$this->set_payment_lock_owner_row( $order, 'corrupt-owner-value' );

		try {
			$this->assertFalse( $this->helper->lock_order_payment( $order ) );
		} finally {
			$this->helper->unlock_order_payment( $order );
		}

		// Reload: the unlock above deleted the meta row this object still holds.
		$order       = wc_get_order( $order->get_id() );
		$active_lock = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$this->set_payment_lock_owner_row( $order, 'corrupt-owner-value' );
		$order->update_meta_data( '_stripe_lock_payment', $active_lock );
		$order->save_meta_data();

		$this->assertTrue( $this->helper->lock_order_payment( $order ) );
		$this->assertSame( $active_lock, $this->helper->get_order_existing_payment_lock( $order ) );

		$this->set_payment_lock_owner_row( $order, null );
	}

	/**
	 * Tests that an owner row left behind by an expired lock is taken over.
	 */
	public function test_lock_order_payment_reclaims_a_stale_owner_row(): void {
		$order = WC_Helper_Order::create_order();

		$this->set_payment_lock_owner_row( $order, ( time() - 1 ) . '|' . wp_generate_uuid4() );

		try {
			$this->assertFalse( $this->helper->lock_order_payment( $order ) );
			$acquired_lock = (string) $this->helper->get_order_existing_payment_lock( $order );
			$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', $acquired_lock );
			$this->assertSame( $acquired_lock, $this->get_payment_lock_owner_row( $order ) );
		} finally {
			$this->helper->unlock_order_payment( $order );
		}

		$this->assertNull( $this->get_payment_lock_owner_row( $order ) );
	}

	/**
	 * Tests that only one of two requests that interleave between the lock check and the write gets the lock.
	 */
	public function test_lock_order_payment_rejects_an_interleaved_acquisition(): void {
		$order        = WC_Helper_Order::create_order();
		$first_order  = wc_get_order( $order->get_id() );
		$second_order = wc_get_order( $order->get_id() );

		$second_result = null;
		$second_helper = new WC_Stripe_Order_Helper();
		// A second worker acquires between the first worker's check and write.
		$first_helper = new class(
			function () use ( &$second_result, $second_helper, $second_order ) {
				$second_result = $second_helper->lock_order_payment( $second_order );
			}
		) extends WC_Stripe_Order_Helper {
			/** @var callable|null */
			private $after_unlocked_check;

			/** @param callable $after_unlocked_check Competing lock callback. */
			public function __construct( callable $after_unlocked_check ) {
				$this->after_unlocked_check = $after_unlocked_check;
			}

			/** @inheritDoc */
			protected function is_order_payment_locked( WC_Order $order ): bool {
				$is_locked = parent::is_order_payment_locked( $order );

				if ( ! $is_locked && is_callable( $this->after_unlocked_check ) ) {
					$callback                   = $this->after_unlocked_check;
					$this->after_unlocked_check = null;
					$callback();
				}

				return $is_locked;
			}
		};

		try {
			$first_result = $first_helper->lock_order_payment( $first_order );

			$this->assertFalse( $first_result );
			$this->assertTrue( $second_result );
			$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', (string) $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) ) );
		} finally {
			$first_helper->unlock_order_payment( $first_order );
		}

		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Tests that releasing a lock that was taken over leaves the new owner's lock in place.
	 */
	public function test_unlock_order_payment_if_owned_preserves_a_replacement_lock(): void {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->helper->lock_order_payment( $order ) );
		$owned_lock        = (string) $this->helper->get_order_existing_payment_lock( $order );
		$replacement_lock  = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$replacement_order = wc_get_order( $order->get_id() );

		$this->set_payment_lock_owner_row( $order, $replacement_lock );
		$replacement_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
		$replacement_order->save_meta_data();

		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );

		$this->assertSame( $replacement_lock, $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame( $replacement_lock, $this->get_payment_lock_owner_row( $order ) );

		$replacement_order->delete_meta_data( '_stripe_lock_payment' );
		$replacement_order->save_meta_data();
		$this->set_payment_lock_owner_row( $order, null );
	}

	/**
	 * Tests that releasing the lock does not save or discard the caller's unsaved order changes.
	 */
	public function test_unlock_order_payment_if_owned_preserves_unsaved_caller_metadata(): void {
		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->helper->lock_order_payment( $order ) );
		$owned_lock = (string) $this->helper->get_order_existing_payment_lock( $order );

		$order->update_meta_data( '_unsaved_extension_meta', 'preserved' );
		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );

		$this->assertSame( 'preserved', $order->get_meta( '_unsaved_extension_meta', true ) );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) ) );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_unsaved_extension_meta', true ) );
	}

	/**
	 * @dataProvider provide_malformed_payment_locks
	 * @param mixed $malformed_lock Malformed payment lock metadata.
	 */
	public function test_lock_order_payment_treats_malformed_metadata_as_locked( $malformed_lock ): void {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_stripe_lock_payment', $malformed_lock );
		$order->save_meta_data();

		$this->assertTrue( $this->helper->lock_order_payment( $order ) );
		$this->assertEquals( $malformed_lock, $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/** @return array<string, array{mixed}> */
	public function provide_malformed_payment_locks(): array {
		return [
			'empty array'     => [ [] ],
			'non-empty array' => [ [ time() + 5 * MINUTE_IN_SECONDS ] ],
			'object'          => [ (object) [ 'expires_at' => time() + 5 * MINUTE_IN_SECONDS ] ],
			'non-numeric'     => [ 'corrupt' ],
			'numeric prefix'  => [ '123abc' ],
			'negative expiry' => [ '-1|' . wp_generate_uuid4() ],
		];
	}

	/**
	 * Tests for `add_payment_intent_to_order`.
	 *
	 * @return void
	 */
	public function test_add_payment_intent_to_order(): void {
		// setup
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// add_payment_intent_to_order
		$intent_id = 'pi_123';
		$this->helper->add_payment_intent_to_order( $intent_id, $order );
		$this->assertEquals( $intent_id, $this->helper->get_intent_id_from_order( $order ) );

		$note = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$this->assertStringContainsString( 'Stripe payment intent created (Payment Intent ID: pi_123)', $note->content );
	}

	/**
	 * Test for `validate_minimum_order_amount`.
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function test_validate_minimum_order_amount(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 0.01 );
		$order->save();

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Did not meet minimum amount' );

		$this->helper->validate_minimum_order_amount( $order );
	}

	/**
	 * Tests for `get_owner_details`.
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function test_get_owner_details(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_phone( '+1 123 1234' );
		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_email( 'test@example.com' );
		$order->save_meta_data();

		$owner_details = $this->helper->get_owner_details( $order );

		$this->assertEquals( '+1 123 1234', $owner_details->phone );
		$this->assertEquals( 'John Doe', $owner_details->name );
		$this->assertEquals( 'test@example.com', $owner_details->email );
	}

	/**
	 * Tests for `is_stripe_gateway_order`.
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function test_is_stripe_gateway_order(): void {
		$this->helper = WC_Stripe_Order_Helper::get_instance();

		// Test with a Stripe order (Klarna).
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe_klarna' );
		$this->assertTrue( $this->helper->is_stripe_gateway_order( $order ) );

		// Test with a non-Stripe order.
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'cod' );
		$this->assertFalse( $this->helper->is_stripe_gateway_order( $order ) );

		// Test with an empty order.
		$order = new WC_Order();
		$this->assertFalse( $this->helper->is_stripe_gateway_order( $order ) );
	}

	/**
	 * Tests for `sync_stripe_charge_captured`.
	 *
	 * @param object|string|null $charge          The observed charge value.
	 * @param bool|null          $expected_return The expected return value.
	 * @param string             $expected_meta   The expected stored meta value.
	 *
	 * @dataProvider provide_test_sync_stripe_charge_captured
	 */
	public function test_sync_stripe_charge_captured( $charge, $expected_return, $expected_meta ): void {
		$order = WC_Helper_Order::create_order();

		$this->assertSame( $expected_return, $this->helper->sync_stripe_charge_captured( $order, $charge ) );
		$this->assertSame( $expected_meta, $this->helper->get_stripe_charge_captured( $order ) );

		// The helper must not persist: the recorded state lives only on the in-memory
		// order until the caller saves, so a fresh read still sees the stored value.
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_stripe_charge_captured' ) );
	}

	/**
	 * Provider for `test_sync_stripe_charge_captured`.
	 *
	 * @return array
	 */
	public function provide_test_sync_stripe_charge_captured(): array {
		return [
			'captured charge'          => [ (object) [ 'captured' => true ], true, 'yes' ],
			'uncaptured charge'        => [ (object) [ 'captured' => false ], false, 'no' ],
			'charge without captured'  => [ (object) [ 'id' => 'ch_123' ], null, '' ],
			'string instead of charge' => [ 'ch_123', null, '' ],
			'null charge'              => [ null, null, '' ],
		];
	}

	/**
	 * Tests for `is_stripe_charge_authorized_only`.
	 *
	 * @param bool|null $captured Recorded captured state, or null to leave the flag unwritten.
	 * @param bool      $expected The expected result.
	 *
	 * @dataProvider provide_test_is_stripe_charge_authorized_only
	 */
	public function test_is_stripe_charge_authorized_only( $captured, $expected ): void {
		$order = WC_Helper_Order::create_order();

		if ( null !== $captured ) {
			$this->helper->set_stripe_charge_captured( $order, $captured );
		}

		$this->assertSame( $expected, $this->helper->is_stripe_charge_authorized_only( $order ) );
	}

	/**
	 * Provider for `test_is_stripe_charge_authorized_only`.
	 *
	 * @return array
	 */
	public function provide_test_is_stripe_charge_authorized_only(): array {
		return [
			'recorded uncaptured' => [ false, true ],
			'recorded captured'   => [ true, false ],
			// Never recorded is unknown, not authorize-only: capture/void flows must not act on it.
			'flag never recorded' => [ null, false ],
		];
	}

	/**
	 * Only a real amount drift returns false. The check is skipped (returns true) when there is no
	 * comparable amount: setup intents and intents that omit the amount. A currency other than the
	 * order's returns true only for an Adaptive Pricing checkout (validated on the Checkout Session
	 * path); any other currency mismatch returns false so the caller holds the order.
	 *
	 * @param array $intent_overrides Intent fields merged over a matching payment intent.
	 * @param bool  $expected         Expected result.
	 * @dataProvider provide_intent_amount_matches_order_cases
	 */
	public function test_intent_amount_matches_order( array $intent_overrides, bool $expected ): void {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( '50.00' );
		$order->save();

		$matching_amount = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );
		$intent          = (object) array_merge(
			[
				'object'   => 'payment_intent',
				'amount'   => $matching_amount,
				'currency' => 'usd',
			],
			$intent_overrides
		);

		$this->assertSame( $expected, $this->helper->intent_amount_matches_order( $order, $intent ) );
	}

	/**
	 * Data provider for `test_intent_amount_matches_order`.
	 *
	 * @return array
	 */
	public function provide_intent_amount_matches_order_cases(): array {
		return [
			'amount matches'                      => [ [], true ],
			'amount drifted above'                => [ [ 'amount' => 4500 ], false ],
			'AP presentment currency is deferred' => [
				[
					'currency' => 'eur',
					'amount'   => 4500,
					'metadata' => (object) [ 'checkout_type' => WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE ],
				],
				true,
			],
			'non-AP currency mismatch is held'    => [
				[
					'currency' => 'eur',
					'amount'   => 4500,
				],
				false,
			],
			'setup intent is skipped'             => [ [ 'object' => 'setup_intent' ], true ],
			'missing amount is skipped'           => [ [ 'amount' => null ], true ],
		];
	}
}
