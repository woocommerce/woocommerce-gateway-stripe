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
	 * The owner-returning API must return the exact value persisted in both stores.
	 *
	 * @return void
	 */
	public function test_acquire_order_payment_lock_returns_the_exact_persisted_owner(): void {
		global $wpdb;

		$order       = WC_Helper_Order::create_order();
		$option_name = 'wc_stripe_payment_lock_owner_' . $order->get_id();
		$owned_lock  = $this->helper->acquire_order_payment_lock( $order );

		$this->assertIsString( $owned_lock );
		$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', $owned_lock );
		$this->assertSame( $owned_lock, $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame(
			$owned_lock,
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);
		$this->assertTrue( $this->helper->is_order_payment_lock_owned( $order, $owned_lock ) );

		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );

		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertNull(
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);
	}

	/**
	 * An exact owner can renew its lease without creating an unlock gap.
	 *
	 * @return void
	 */
	public function test_renew_order_payment_lock_if_owned_replaces_both_stores_without_an_unlock_gap(): void {
		global $wpdb;

		$order       = WC_Helper_Order::create_order();
		$option_name = 'wc_stripe_payment_lock_owner_' . $order->get_id();
		$owned_lock  = $this->helper->acquire_order_payment_lock( $order );

		$this->assertIsString( $owned_lock );
		$this->assertTrue( is_callable( [ $this->helper, 'renew_order_payment_lock_if_owned' ] ) );

		$order->update_meta_data( '_unsaved_extension_meta', 'preserved' );
		$renewed_lock = $this->helper->renew_order_payment_lock_if_owned( $order, $owned_lock );

		$this->assertIsString( $renewed_lock );
		$this->assertNotSame( $owned_lock, $renewed_lock );
		$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', $renewed_lock );
		$this->assertSame( $renewed_lock, $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) ) );
		$this->assertSame(
			$renewed_lock,
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);
		$this->assertFalse( $this->helper->is_order_payment_lock_owned( $order, $owned_lock ) );
		$this->assertTrue( $this->helper->is_order_payment_lock_owned( $order, $renewed_lock ) );
		$this->assertSame( 'preserved', $order->get_meta( '_unsaved_extension_meta', true ) );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_unsaved_extension_meta', true ) );

		// A stale finally block holding the old token cannot clear the renewed lease.
		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );
		$this->assertTrue( $this->helper->is_order_payment_lock_owned( $order, $renewed_lock ) );

		$this->helper->unlock_order_payment_if_owned( $order, $renewed_lock );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Renewal must fail closed after either lock store has moved to another owner.
	 *
	 * @return void
	 */
	public function test_renew_order_payment_lock_if_owned_preserves_a_replacement_owner(): void {
		global $wpdb;

		$order            = WC_Helper_Order::create_order();
		$owned_lock       = $this->helper->acquire_order_payment_lock( $order );
		$replacement_lock = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$option_name      = 'wc_stripe_payment_lock_owner_' . $order->get_id();

		$this->assertIsString( $owned_lock );

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET option_value = %s WHERE option_name = %s',
				$wpdb->options,
				$replacement_lock,
				$option_name
			)
		);
		$replacement_order = wc_get_order( $order->get_id() );
		$replacement_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
		$replacement_order->save_meta_data();

		$this->assertFalse( $this->helper->renew_order_payment_lock_if_owned( $order, $owned_lock ) );
		$this->assertSame( $replacement_lock, $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame(
			$replacement_lock,
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);

		$replacement_order->delete_meta_data( '_stripe_lock_payment' );
		$replacement_order->save_meta_data();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s', $wpdb->options, $option_name ) );
	}

	/**
	 * Owner-returning acquisition must preserve legacy helper-subclass dispatch.
	 *
	 * @return void
	 */
	public function test_acquire_order_payment_lock_dispatches_through_legacy_lock_override(): void {
		$order  = WC_Helper_Order::create_order();
		$helper = new class() extends WC_Stripe_Order_Helper {
			/**
			 * Whether the legacy override was called.
			 *
			 * @var bool
			 */
			public bool $lock_called = false;

			/**
			 * @inheritDoc
			 */
			public function lock_order_payment( WC_Order $order ): bool {
				$this->lock_called = true;
				return true;
			}
		};

		$this->assertFalse( $helper->acquire_order_payment_lock( $order ) );
		$this->assertTrue( $helper->lock_called );
	}

	/**
	 * A compatible override that reports acquisition without recording an owner must fail closed
	 * with a diagnostic that distinguishes it from a genuinely contended lock.
	 *
	 * @return void
	 */
	public function test_acquire_order_payment_lock_logs_when_legacy_override_does_not_record_an_owner(): void {
		$order  = WC_Helper_Order::create_order();
		$helper = new class() extends WC_Stripe_Order_Helper {
			/**
			 * @inheritDoc
			 */
			public function lock_order_payment( WC_Order $order ): bool {
				return false;
			}
		};

		$previous_logger = WC_Stripe_Logger::$logger;
		$logger          = $this->getMockBuilder( WC_Logger::class )->disableOriginalConstructor()->getMock();
		$logger->expects( $this->once() )
			->method( 'error' )
			->with(
				$this->stringContains( 'reported a successful payment-lock acquisition without recording an owner' ),
				$this->callback(
					function ( $context ) use ( $order ) {
						return $order->get_id() === $context['order_id'];
					}
				)
			);
		WC_Stripe_Logger::$logger = $logger;

		try {
			$this->assertFalse( $helper->acquire_order_payment_lock( $order ) );
		} finally {
			WC_Stripe_Logger::$logger = $previous_logger;
		}
	}

	/**
	 * A late unlock without an owner token must not clear another worker's active lock.
	 *
	 * @return void
	 */
	public function test_unlock_order_payment_without_a_token_preserves_an_active_owner(): void {
		$order        = WC_Helper_Order::create_order();
		$owner_helper = new WC_Stripe_Order_Helper();
		$late_helper  = new WC_Stripe_Order_Helper();

		$this->assertFalse( $owner_helper->lock_order_payment( $order ) );
		$owned_lock = (string) $owner_helper->get_order_existing_payment_lock( $order );

		$late_helper->unlock_order_payment( wc_get_order( $order->get_id() ) );

		$this->assertTrue( $owner_helper->is_order_payment_lock_owned( $order, $owned_lock ) );
		$this->assertSame( $owned_lock, $owner_helper->get_order_existing_payment_lock( $order ) );

		$owner_helper->unlock_order_payment( $order );
	}

	/**
	 * A no-token unlock must preserve an active legacy meta-only lock while cleaning its guard.
	 *
	 * @return void
	 */
	public function test_unlock_order_payment_without_a_token_preserves_active_legacy_metadata(): void {
		global $wpdb;

		$order       = WC_Helper_Order::create_order();
		$legacy_lock = time() + 5 * MINUTE_IN_SECONDS;
		$option_name = 'wc_stripe_payment_lock_owner_' . $order->get_id();
		$order->update_meta_data( '_stripe_lock_payment', $legacy_lock );
		$order->save_meta_data();

		$this->helper->unlock_order_payment( $order );

		$this->assertSame( (string) $legacy_lock, (string) $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertNull(
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);
	}

	/**
	 * A no-token unlock may clear expired legacy metadata and must remove its temporary guard.
	 *
	 * @return void
	 */
	public function test_unlock_order_payment_without_a_token_clears_expired_legacy_metadata(): void {
		global $wpdb;

		$order       = WC_Helper_Order::create_order();
		$option_name = 'wc_stripe_payment_lock_owner_' . $order->get_id();
		$order->update_meta_data( '_stripe_lock_payment', time() - 1 );
		$order->save_meta_data();

		$this->helper->unlock_order_payment( $order );

		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertNull(
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);
	}

	/**
	 * A malformed authoritative owner must fail closed instead of being reclaimed unsafely.
	 *
	 * @return void
	 */
	public function test_lock_order_payment_preserves_a_malformed_authoritative_owner(): void {
		global $wpdb;

		$order           = WC_Helper_Order::create_order();
		$option_name     = 'wc_stripe_payment_lock_owner_' . $order->get_id();
		$malformed_owner = 'corrupt-owner-value';

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)',
				$wpdb->options,
				$option_name,
				$malformed_owner,
				'no'
			)
		);

		$this->assertTrue( $this->helper->lock_order_payment( $order ) );
		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame(
			$malformed_owner,
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s', $wpdb->options, $option_name ) );
	}

	/**
	 * Two workers that overlap between the unlocked read and lock write must not both acquire.
	 *
	 * @return void
	 */
	public function test_lock_order_payment_rejects_an_interleaved_acquisition(): void {
		$order        = WC_Helper_Order::create_order();
		$first_order  = wc_get_order( $order->get_id() );
		$second_order = wc_get_order( $order->get_id() );

		$second_result = null;
		$second_helper = new WC_Stripe_Order_Helper();
		$first_helper  = new class(
			function () use ( &$second_result, $second_helper, $second_order ) {
				$second_result = $second_helper->lock_order_payment( $second_order );
			}
		) extends WC_Stripe_Order_Helper {
			/**
			 * Callback that starts the competing acquisition after the first unlocked read.
			 *
			 * @var callable|null
			 */
			private $after_unlocked_check;

			/**
			 * @param callable $after_unlocked_check Competing acquisition callback.
			 */
			public function __construct( callable $after_unlocked_check ) {
				$this->after_unlocked_check = $after_unlocked_check;
			}

			/**
			 * @inheritDoc
			 */
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
			$current_lock = $this->helper->get_order_existing_payment_lock( wc_get_order( $order->get_id() ) );

			$this->assertFalse( $first_result, 'The first worker should acquire the payment lock.' );
			$this->assertTrue( $second_result, 'The interleaved worker should observe the atomic acquisition guard.' );
			$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', (string) $current_lock );
		} finally {
			$first_helper->unlock_order_payment( $first_order );
		}

		$this->assertEmpty( $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * A worker must not release a lock that has been replaced by a different owner.
	 *
	 * @return void
	 */
	public function test_unlock_order_payment_preserves_a_replacement_lock(): void {
		global $wpdb;

		$order = WC_Helper_Order::create_order();

		$this->assertFalse( $this->helper->lock_order_payment( $order ) );
		$owned_lock        = (string) $this->helper->get_order_existing_payment_lock( $order );
		$replacement_lock  = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$replacement_order = wc_get_order( $order->get_id() );
		$option_name       = 'wc_stripe_payment_lock_owner_' . $order->get_id();

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET option_value = %s WHERE option_name = %s',
				$wpdb->options,
				$replacement_lock,
				$option_name
			)
		);

		$replacement_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
		$replacement_order->save_meta_data();

		$this->helper->unlock_order_payment_if_owned( $order, $owned_lock );

		$this->assertSame( $replacement_lock, $this->helper->get_order_existing_payment_lock( $order ) );
		$this->assertSame(
			$replacement_lock,
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);

		$replacement_order->delete_meta_data( '_stripe_lock_payment' );
		$replacement_order->save_meta_data();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s', $wpdb->options, $option_name ) );
	}

	/**
	 * The legacy unlock signature must remain compatible with third-party overrides.
	 *
	 * @return void
	 */
	public function test_unlock_order_payment_keeps_its_original_signature(): void {
		$method = new ReflectionMethod( WC_Stripe_Order_Helper::class, 'unlock_order_payment' );

		$this->assertSame( 1, $method->getNumberOfParameters() );
		$this->assertSame( 1, $method->getNumberOfRequiredParameters() );
	}

	/**
	 * An owner-aware unlock must not refresh away unsaved metadata on the caller's order object.
	 *
	 * @return void
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
	 * An owner row abandoned by an expired request must be reclaimed atomically.
	 *
	 * @return void
	 */
	public function test_lock_order_payment_reclaims_a_stale_owner(): void {
		global $wpdb;

		$order       = WC_Helper_Order::create_order();
		$option_name = 'wc_stripe_payment_lock_owner_' . $order->get_id();
		$stale_owner = ( time() - 1 ) . '|' . wp_generate_uuid4();

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)',
				$wpdb->options,
				$option_name,
				$stale_owner,
				'no'
			)
		);

		try {
			$this->assertFalse( $this->helper->lock_order_payment( $order ) );
			$acquired_lock = (string) $this->helper->get_order_existing_payment_lock( $order );
			$this->assertMatchesRegularExpression( '/^[1-9][0-9]*\|[0-9a-f-]{36}$/', $acquired_lock );
			$this->assertSame(
				$acquired_lock,
				$wpdb->get_var(
					$wpdb->prepare(
						'SELECT option_value FROM %i WHERE option_name = %s',
						$wpdb->options,
						$option_name
					)
				),
				'The reclaimed owner row remains authoritative for the payment-lock lifetime.'
			);
		} finally {
			$this->helper->unlock_order_payment( $order );
		}

		$this->assertNull(
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM %i WHERE option_name = %s',
					$wpdb->options,
					$option_name
				)
			)
		);
	}

	/**
	 * A malformed structured payment lock must fail closed instead of throwing or being replaced.
	 *
	 * @dataProvider provide_malformed_payment_locks
	 * @param mixed $malformed_lock Malformed payment lock metadata.
	 * @return void
	 */
	public function test_lock_order_payment_treats_malformed_structured_metadata_as_locked( $malformed_lock ): void {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_stripe_lock_payment', $malformed_lock );
		$order->save_meta_data();

		$this->assertTrue( $this->helper->lock_order_payment( $order ) );
		$this->assertEquals( $malformed_lock, $this->helper->get_order_existing_payment_lock( $order ) );
	}

	/**
	 * Data provider for malformed structured payment lock metadata.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function provide_malformed_payment_locks(): array {
		return [
			'empty array'     => [ [] ],
			'non-empty array' => [ [ time() + 5 * MINUTE_IN_SECONDS ] ],
			'object'          => [ (object) [ 'expires_at' => time() + 5 * MINUTE_IN_SECONDS ] ],
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
}
