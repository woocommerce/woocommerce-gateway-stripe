<?php

/**
 * Tests for WC_Stripe_Express_Checkout_Password_Cleanup.
 */
class WC_Stripe_Express_Checkout_Password_Cleanup_Test extends WP_UnitTestCase {

	/**
	 * Original HPOS setting.
	 *
	 * @var string
	 */
	private $original_hpos_setting;

	/**
	 * Original HPOS compatibility setting.
	 *
	 * @var string
	 */
	private $original_sync_setting;

	/**
	 * Original site administrator email.
	 *
	 * @var string
	 */
	private $original_admin_email;

	/**
	 * Captured email payloads.
	 *
	 * @var array[]
	 */
	private $emails = [];

	/**
	 * Timestamp recorded during set_up() before each test case.
	 * Used as a lower bound for time-based comparisons.
	 *
	 * @var int
	 */
	private $started_at;

	public function set_up(): void {
		parent::set_up();

		$this->emails                = [];
		$this->original_hpos_setting = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		$this->original_sync_setting = get_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		$this->original_admin_email  = get_option( 'admin_email' );

		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		add_filter( 'pre_wp_mail', [ $this, 'capture_email' ], 10, 2 );
		update_option( 'admin_email', 'admin@example.com' );

		foreach ( $this->get_option_names() as $option_name ) {
			delete_option( $option_name );
		}
		$this->delete_user_markers();
		as_unschedule_all_actions( WC_Stripe_Express_Checkout_Password_Cleanup::BATCH_HOOK, [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );

		$this->started_at = time();
	}

	public function tear_down(): void {
		as_unschedule_all_actions( WC_Stripe_Express_Checkout_Password_Cleanup::BATCH_HOOK, [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );
		$this->delete_user_markers();
		foreach ( $this->get_option_names() as $option_name ) {
			delete_option( $option_name );
		}

		update_option( 'woocommerce_custom_orders_table_enabled', $this->original_hpos_setting );
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', $this->original_sync_setting );
		update_option( 'admin_email', $this->original_admin_email );
		remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		remove_filter( 'pre_wp_mail', [ $this, 'capture_email' ], 10 );

		parent::tear_down();
	}

	/**
	 * @dataProvider storage_provider
	 *
	 * @param bool $use_hpos Whether HPOS is authoritative.
	 * @param bool $use_email_fallback Whether the order should omit its customer ID.
	 */
	public function test_cleanup_removes_password_and_retains_matching_user( bool $use_hpos, bool $use_email_fallback ): void {
		$this->configure_storage( $use_hpos );

		$password = 'affected-password';
		$email    = $use_hpos ? 'hpos-customer@example.com' : 'legacy-customer@example.com';
		$user_id  = $this->factory->user->create(
			[
				'user_email' => $email,
				'user_pass'  => $password,
			]
		);

		$order = WC_Helper_Order::create_order();
		$order->set_customer_id( $use_email_fallback ? 0 : $user_id );
		$order->set_billing_email( $email );
		$order->update_meta_data( 'account_password', $password );
		$order->save();

		$this->assertGreaterThan( 0, $this->count_password_rows( $order->get_id() ) );

		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$cleanup->process_batch();

		$result = WC_Stripe_Express_Checkout_Password_Cleanup::get_cleanup_result();
		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertTrue( $result['affected'] );
		$this->assertSame( 1, $result['user_count'] );
		$this->assertContains( $user_id, WC_Stripe_Express_Checkout_Password_Cleanup::get_affected_user_ids() );
		$this->assertCount( 1, $this->emails );
		$this->assertStringNotContainsString( $password, $this->emails[0]['message'] );

		$cleanup->process_batch();
		$this->assertCount( 1, $this->emails );
	}

	/**
	 * Provides authoritative storage scenarios.
	 *
	 * @return array<string, array{bool, bool}>
	 */
	public function storage_provider(): array {
		return [
			'legacy linked customer' => [ false, false ],
			'HPOS email fallback'    => [ true, true ],
		];
	}

	/**
	 * @dataProvider subscription_storage_provider
	 */
	public function test_cleanup_removes_subscription_passwords( bool $use_hpos, bool $sync, bool $affected_parent ): void {
		global $wc_order_types;

		$original_order_types = $wc_order_types;
		wc_register_order_type( 'shop_subscription', [ 'class_name' => 'WC_Subscription' ] );

		try {
			$this->configure_storage( $use_hpos, $sync );
			$password = 'subscription-password';
			$user_id  = $this->factory->user->create( [ 'user_pass' => $password ] );
			$order    = WC_Helper_Order::create_order( $user_id );
			if ( $affected_parent ) {
				$order->update_meta_data( 'account_password', $password );
				$order->save();
			}

			$subscription = new WC_Subscription();
			$subscription->set_customer_id( $user_id );
			$subscription->set_parent_id( $order->get_id() );
			$subscription->update_meta_data( 'account_password', $password );
			$subscription->save();

			$this->assertSame( $sync ? 2 : 1, $this->count_password_rows( $subscription->get_id() ) );
			$this->assertSame( $password, wc_get_order( $subscription->get_id() )->get_meta( 'account_password' ) );

			( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();

			$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
			$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
			$this->assertSame( 0, $this->count_password_rows( $subscription->get_id() ) );
			$this->assertSame( '', wc_get_order( $subscription->get_id() )->get_meta( 'account_password' ) );
			$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( 'account_password' ) );
			$this->assertSame( [ $user_id ], WC_Stripe_Express_Checkout_Password_Cleanup::get_affected_user_ids() );
			$this->assertSame( 1, WC_Stripe_Express_Checkout_Password_Cleanup::get_cleanup_result()['user_count'] );
			$this->assertCount( 1, $this->emails );
		} finally {
			unregister_post_type( 'shop_subscription' );
			$wc_order_types = $original_order_types;
		}
	}

	public function subscription_storage_provider(): array {
		return [
			'legacy subscription only'       => [ false, false, false ],
			'legacy parent and subscription' => [ false, false, true ],
			'HPOS subscription only'         => [ true, false, false ],
			'HPOS parent and subscription'   => [ true, false, true ],
			'synced subscription only'       => [ true, true, false ],
			'synced parent and subscription' => [ true, true, true ],
		];
	}

	/**
	 * @dataProvider storage_provider
	 */
	public function test_cleanup_removes_unregistered_subscription_metadata( bool $use_hpos ): void {
		global $wpdb;

		$this->configure_storage( $use_hpos );
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'account_password', 'historical-password' );
		$order->save();

		if ( $use_hpos ) {
			$wpdb->update( $wpdb->prefix . 'wc_orders', [ 'type' => 'shop_subscription' ], [ 'id' => $order->get_id() ] );
		} else {
			$wpdb->update( $wpdb->posts, [ 'post_type' => 'shop_subscription' ], [ 'ID' => $order->get_id() ] );
		}
		$this->assertFalse( post_type_exists( 'shop_subscription' ) );
		$this->assertSame( 1, $this->count_password_rows( $order->get_id() ) );

		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
	}

	/**
	 * Synchronized copies in both physical stores must be removed together.
	 */
	public function test_cleanup_removes_synchronized_physical_copies(): void {
		global $wpdb;

		$this->configure_storage( true, true );
		$password = 'synchronized-password';
		$user_id  = $this->factory->user->create(
			[
				'user_email' => 'synchronized@example.com',
				'user_pass'  => $password,
			]
		);

		$order = WC_Helper_Order::create_order();
		$order->set_customer_id( $user_id );
		$order->set_billing_email( 'synchronized@example.com' );
		$order->update_meta_data( 'account_password', $password );
		$order->save();

		if ( ! get_post( $order->get_id() ) ) {
			wp_insert_post(
				[
					'import_id'   => $order->get_id(),
					'post_type'   => 'shop_order_placehold',
					'post_status' => 'publish',
					'post_title'  => 'HPOS placeholder',
				]
			);
		}
		add_post_meta( $order->get_id(), 'account_password', $password );

		$hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
		$this->assertGreaterThan( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE order_id = %d AND meta_key = %s', $hpos_meta_table, $order->get_id(), 'account_password' ) ) );
		$this->assertGreaterThan( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $order->get_id(), 'account_password' ) ) );

		$this->assertSame( $password, wc_get_order( $order->get_id() )->get_meta( 'account_password' ) );

		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();

		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( 'account_password' ) );
		$this->assertContains( $user_id, WC_Stripe_Express_Checkout_Password_Cleanup::get_affected_user_ids() );
	}

	/**
	 * Rows beyond the batch boundary remain available for account matching.
	 */
	public function test_cleanup_matches_password_in_later_batch(): void {
		$this->configure_storage( false );
		$password = 'matching-password';
		$user_id  = $this->factory->user->create(
			[
				'user_email' => 'later-batch@example.com',
				'user_pass'  => $password,
			]
		);
		$order    = WC_Helper_Order::create_order( $user_id );

		for ( $index = 0; $index < 20; $index++ ) {
			add_post_meta( $order->get_id(), 'account_password', 'old-password-' . $index );
		}
		add_post_meta( $order->get_id(), 'account_password', $password );

		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$cleanup->process_batch();
		$this->assertSame( 1, $this->count_password_rows( $order->get_id() ) );
		$this->assertNotContains( $user_id, WC_Stripe_Express_Checkout_Password_Cleanup::get_affected_user_ids() );
		$this->run_next_batch();

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertContains( $user_id, WC_Stripe_Express_Checkout_Password_Cleanup::get_affected_user_ids() );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
	}

	/**
	 * Passwords changed before cleanup are removed but do not mark the account for later reset.
	 *
	 * @dataProvider storage_provider
	 */
	public function test_cleanup_does_not_retain_user_with_changed_password( bool $use_hpos ): void {
		$this->configure_storage( $use_hpos );

		$user_id = $this->factory->user->create(
			[
				'user_email' => 'changed-password@example.com',
				'user_pass'  => 'current-password',
			]
		);
		$order   = WC_Helper_Order::create_order();
		$order->set_customer_id( $user_id );
		$order->update_meta_data( 'account_password', 'old-password' );
		$order->save();

		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();

		$result = WC_Stripe_Express_Checkout_Password_Cleanup::get_cleanup_result();
		$this->assertTrue( $result['affected'] );
		$this->assertSame( 0, $result['user_count'] );
		$this->assertNotContains( $user_id, WC_Stripe_Express_Checkout_Password_Cleanup::get_affected_user_ids() );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertTrue( WC_Stripe_Express_Checkout_Password_Cleanup::should_show_notice() );
		$this->assertCount( 1, $this->emails );
		$this->assertStringContainsString( 'removed account password data', $this->emails[0]['message'] );
		$this->assertStringNotContainsString( 'customer accounts were identified', $this->emails[0]['message'] );
	}

	/**
	 * The generic metadata key must not be removed from non-order posts.
	 *
	 * @dataProvider storage_provider
	 */
	public function test_cleanup_leaves_unrelated_post_metadata_untouched( bool $use_hpos ): void {
		$this->configure_storage( $use_hpos );
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'account_password', 'unrelated-value' );

		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();

		$this->assertSame( 'unrelated-value', get_post_meta( $post_id, 'account_password', true ) );
		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertFalse( WC_Stripe_Express_Checkout_Password_Cleanup::get_cleanup_result()['affected'] );
		$this->assertFalse( WC_Stripe_Express_Checkout_Password_Cleanup::should_show_notice() );
		$this->assertEmpty( $this->emails );
	}

	/**
	 * A live lock prevents concurrent cleanup, while an abandoned lock is recoverable.
	 */
	public function test_cleanup_recovers_stale_lock(): void {
		$this->configure_storage( false );
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'account_password', 'password' );
		$order->save();

		$lock_option = $this->get_constant_value( 'LOCK_OPTION' );
		update_option( $lock_option, time(), false );
		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$cleanup->maybe_migrate();
		$this->run_next_batch();

		$this->assertNotSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertGreaterThan( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertFalse( get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );

		update_option( $lock_option, time() - ( 16 * MINUTE_IN_SECONDS ), false );
		$this->run_next_batch();

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
	}

	/**
	 * A stale worker must not release a lock that another worker acquired.
	 */
	public function test_cleanup_releases_only_its_own_lock(): void {
		$lock_option     = $this->get_constant_value( 'LOCK_OPTION' );
		$complete_option = $this->get_constant_value( 'COMPLETE_OPTION' );
		$new_lock        = time() . ':new-worker';
		$replace_lock    = static function ( $value ) use ( $lock_option, $new_lock ) {
			update_option( $lock_option, $new_lock, false );
			return $value;
		};
		add_filter( 'pre_update_option_' . $complete_option, $replace_lock );
		try {
			( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();
		} finally {
			remove_filter( 'pre_update_option_' . $complete_option, $replace_lock );
		}

		$this->assertSame( 'yes', get_option( $complete_option ) );
		$this->assertSame( $new_lock, get_option( $lock_option ) );
	}

	/**
	 * The upgrade only schedules work; running jobs queue bounded successors.
	 */
	public function test_migration_schedules_bounded_batches(): void {
		$this->configure_storage( false );
		$order_ids = [];
		for ( $index = 0; $index <= 20; $index++ ) {
			$order = WC_Helper_Order::create_order();
			$order->update_meta_data( 'account_password', 'password-' . $index );
			$order->save();
			$order_ids[] = $order->get_id();
		}

		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$cleanup->maybe_migrate();
		$cleanup->maybe_migrate();
		$this->assertSame( 21, array_sum( array_map( [ $this, 'count_password_rows' ], $order_ids ) ) );
		$this->assertCount( 1, $this->get_pending_batches() );
		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );
		$this->run_next_batch();
		$this->assertSame( 1, array_sum( array_map( [ $this, 'count_password_rows' ], $order_ids ) ) );
		$this->assertNotSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertEmpty( $this->emails );
		$this->assertCount( 2, $this->get_pending_batches() );
		$this->assert_next_batch_delay( 0 );
		$this->run_next_batch();

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 0, array_sum( array_map( [ $this, 'count_password_rows' ], $order_ids ) ) );
		$this->assertCount( 1, $this->emails );
		$this->assertEmpty( $this->get_pending_batches() );
		$cleanup->maybe_migrate();
		$this->assertEmpty( $this->get_pending_batches() );
	}

	/**
	 * An unavailable fast successor must not remove the delayed recovery.
	 */
	public function test_failed_fast_successor_preserves_recovery(): void {
		$this->configure_storage( false );
		$order = WC_Helper_Order::create_order();
		for ( $index = 0; $index < 21; $index++ ) {
			$order->add_meta_data( 'account_password', 'password' );
		}
		$order->save();
		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->maybe_migrate();
		$fail_fast_action = static function ( $pre, $timestamp, $hook ) {
			if ( WC_Stripe_Express_Checkout_Password_Cleanup::BATCH_HOOK === $hook && $timestamp <= time() ) {
				return 0;
			}
			return $pre;
		};
		add_filter( 'pre_as_schedule_single_action', $fail_fast_action, 10, 3 );
		try {
			$this->run_next_batch();
		} finally {
			remove_filter( 'pre_as_schedule_single_action', $fail_fast_action );
		}

		$this->assertSame( 1, $this->count_password_rows( $order->get_id() ) );
		$this->assertCount( 1, $this->get_pending_batches() );
		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );
		$this->run_next_batch();
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertEmpty( $this->get_pending_batches() );
	}

	/**
	 * A claimed action may fail before execution and cannot replace an unclaimed recovery.
	 */
	public function test_claimed_pending_action_does_not_suppress_recovery(): void {
		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$cleanup->maybe_migrate();
		// Keep claim filters off the shared scheduler store.
		$store = new ActionScheduler_DBStore();
		$claim = $store->stake_claim( 1, as_get_datetime_object( time() + 3 * MINUTE_IN_SECONDS ), [ WC_Stripe_Express_Checkout_Password_Cleanup::BATCH_HOOK ] );
		try {
			$this->assertCount( 1, $claim->get_actions() );
			$cleanup->maybe_migrate();
			$this->assertCount( 2, $this->get_pending_batches() );
			$cleanup->maybe_migrate();
			$this->assertCount( 2, $this->get_pending_batches() );
		} finally {
			$store->release_claim( $claim );
		}
	}

	/**
	 * An already due successor also satisfies a request for delayed recovery.
	 */
	public function test_due_successor_is_not_duplicated(): void {
		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$method  = new ReflectionMethod( $cleanup, 'schedule_batch' );
		$method->setAccessible( true );
		$method->invoke( $cleanup, 0 );
		$method->invoke( $cleanup, 0 );
		$cleanup->maybe_migrate();
		$this->assertCount( 1, $this->get_pending_batches() );
		$this->assert_next_batch_delay( 0 );
	}

	/**
	 * @dataProvider completion_state_provider
	 */
	public function test_cleanup_sends_pending_email( bool $completed ): void {
		if ( $completed ) {
			update_option( $this->get_constant_value( 'COMPLETE_OPTION' ), 'yes', false );
		}
		update_option(
			$this->get_constant_value( 'RESULT_OPTION' ),
			[
				'affected'   => true,
				'user_count' => 1,
			],
			false
		);

		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$cleanup->process_batch();
		$cleanup->process_batch();

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertTrue( WC_Stripe_Express_Checkout_Password_Cleanup::should_show_notice() );
		$this->assertCount( 1, $this->emails );
	}

	public function completion_state_provider(): array {
		return [
			'already marked complete'             => [ true ],
			'interrupted after deleting all rows' => [ false ],
		];
	}

	/**
	 * A failed worker leaves a successor even when it never reaches deletion.
	 */
	public function test_worker_exception_leaves_a_scheduled_retry(): void {
		$this->configure_storage( false );
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'account_password', 'password' );
		$order->save();
		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->maybe_migrate();
		$pending   = $this->get_pending_batches();
		$action_id = (int) reset( $pending );
		$fail_read = static function ( $query ) {
			if ( 0 === strpos( ltrim( $query ), 'SELECT pm.meta_id AS row_id' ) ) {
				throw new RuntimeException( 'Synthetic cleanup interruption.' );
			}
			return $query;
		};
		add_filter( 'query', $fail_read );
		try {
			ActionScheduler::runner()->process_action( $action_id, 'test' );
		} finally {
			remove_filter( 'query', $fail_read );
		}

		$this->assertSame( ActionScheduler_Store::STATUS_FAILED, ActionScheduler::store()->get_status( $action_id ) );
		$this->assertSame( 1, $this->count_password_rows( $order->get_id() ) );
		$this->assertFalse( get_option( $this->get_constant_value( 'LOCK_OPTION' ) ) );
		$this->assertSame( 1, (int) get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );
		$this->run_next_batch();
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertEmpty( $this->get_pending_batches() );
		$this->assertCount( 1, $this->emails );
	}

	/**
	 * @dataProvider completion_failure_provider
	 */
	public function test_failed_completion_retries( bool $fail_email ): void {
		$this->configure_storage( false );
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'account_password', 'password' );
		$order->save();
		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->maybe_migrate();

		$failure_hook = $fail_email ? 'pre_wp_mail' : 'pre_update_option_' . $this->get_constant_value( 'COMPLETE_OPTION' );
		add_filter( $failure_hook, '__return_false', 20 );
		try {
			$this->run_next_batch();
		} finally {
			remove_filter( $failure_hook, '__return_false', 20 );
		}

		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertFalse( get_option( $this->get_constant_value( 'EMAIL_ATTEMPTED_OPTION' ) ) );
		$this->assertCount( 1, $this->get_pending_batches() );
		$this->run_next_batch();
		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'EMAIL_ATTEMPTED_OPTION' ) ) );
		$this->assertEmpty( $this->get_pending_batches() );
		$this->assertCount( $fail_email ? 2 : 1, $this->emails );
	}

	public function completion_failure_provider(): array {
		return [
			'completion write fails' => [ false ],
			'email transport fails'  => [ true ],
		];
	}

	/**
	 * Row identity excludes mutable values and distinguishes physical stores.
	 */
	public function test_record_ids_ignore_values_and_order_but_include_storage(): void {
		$method = new ReflectionMethod( WC_Stripe_Express_Checkout_Password_Cleanup::class, 'get_record_ids' );
		$method->setAccessible( true );
		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$records = [
			[
				'storage'    => 'legacy',
				'row_id'     => 1,
				'meta_value' => 'old-value',
			],
			[
				'storage'    => 'hpos',
				'row_id'     => 1,
				'meta_value' => 'old-value',
			],
		];
		$ids     = $method->invoke( $cleanup, $records );
		$this->assertSame( [ 'hpos:1', 'legacy:1' ], $ids );

		$records[0]['meta_value'] = 'changed-value';
		$this->assertSame( $ids, $method->invoke( $cleanup, array_reverse( $records ) ) );
	}

	/**
	 * @dataProvider storage_provider
	 */
	public function test_migration_stops_when_deletion_makes_no_progress( bool $use_hpos ): void {
		global $wpdb;

		$this->configure_storage( $use_hpos );
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'account_password', 'password' );
		$order->save();

		$meta_table   = $use_hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
		$block_delete = static function ( $query ) use ( $meta_table ) {
			if ( 0 === strpos( $query, "DELETE FROM `$meta_table`" ) && false !== strpos( $query, 'account_password' ) ) {
				return $query . ' AND 1 = 0';
			}
			return $query;
		};
		add_filter( 'query', $block_delete );
		$cleanup = new WC_Stripe_Express_Checkout_Password_Cleanup();
		try {
			$cleanup->process_batch();
		} finally {
			remove_filter( 'query', $block_delete );
		}

		$this->assertNotSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertGreaterThan( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertFalse( get_option( $this->get_constant_value( 'LOCK_OPTION' ) ) );
		$this->assertFalse( WC_Stripe_Express_Checkout_Password_Cleanup::should_show_notice() );
		$this->assertEmpty( $this->emails );

		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );
		$this->run_next_batch();

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertCount( 1, $this->emails );
	}

	/**
	 * @dataProvider failed_read_provider
	 */
	public function test_migration_stops_when_metadata_query_fails( bool $use_hpos, int $failed_read ): void {
		global $wpdb;

		$this->configure_storage( $use_hpos );
		$order = WC_Helper_Order::create_order();
		for ( $index = 0; $index < 21; $index++ ) {
			$order->add_meta_data( 'account_password', 'synthetic-password-' . $index );
		}
		$order->save();

		$select_prefix = $use_hpos ? 'SELECT om.id AS row_id' : 'SELECT pm.meta_id AS row_id';
		$read_count    = 0;
		$fail_query    = static function ( $query ) use ( $select_prefix, $failed_read, &$read_count ) {
			if ( 0 === strpos( ltrim( $query ), $select_prefix ) ) {
				++$read_count;
				if ( $failed_read === $read_count ) {
					return 'SELECT FROM';
				}
			}
			return $query;
		};

		$cleanup         = new WC_Stripe_Express_Checkout_Password_Cleanup();
		$suppress_errors = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_query );
		try {
			$cleanup->process_batch();
		} finally {
			remove_filter( 'query', $fail_query );
			$wpdb->suppress_errors( $suppress_errors );
		}

		$this->assertSame( $failed_read, $read_count );
		$this->assertNotSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 1 === $failed_read ? 21 : 1, $this->count_password_rows( $order->get_id() ) );
		$this->assertFalse( get_option( $this->get_constant_value( 'LOCK_OPTION' ) ) );
		$this->assertEmpty( $this->emails );
		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );

		$this->run_next_batch();
		if ( 1 === $failed_read ) {
			$this->run_next_batch();
		}

		$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertCount( 1, $this->emails );
	}

	public function failed_read_provider(): array {
		return [
			'legacy initial read'      => [ false, 1 ],
			'HPOS initial read'        => [ true, 1 ],
			'legacy after first batch' => [ false, 2 ],
			'HPOS after first batch'   => [ true, 2 ],
		];
	}

	/**
	 * Persistent failures must slow down without abandoning cleanup.
	 *
	 * @dataProvider retry_failure_provider
	 */
	public function test_failures_back_off_and_progress_resets_delay( string $failure ): void {
		global $wpdb;

		$this->configure_storage( false );
		$order = WC_Helper_Order::create_order();
		for ( $index = 0; $index < 21; $index++ ) {
			$order->add_meta_data( 'account_password', 'password' );
		}
		$order->save();
		$fail_query = static function ( $query ) use ( $failure, $wpdb ) {
			if ( 'read' === $failure && 0 === strpos( ltrim( $query ), 'SELECT pm.meta_id AS row_id' ) ) {
				return 'SELECT * FROM wc_stripe_missing_cleanup_table';
			}
			if ( 'delete' === $failure && 0 === strpos( $query, "DELETE FROM `{$wpdb->postmeta}`" ) && false !== strpos( $query, 'account_password' ) ) {
				return $query . ' AND 1 = 0';
			}
			return $query;
		};
		( new WC_Stripe_Express_Checkout_Password_Cleanup() )->maybe_migrate();
		$suppress_errors = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_query );
		try {
			foreach ( [ 2, 4, 8, 16, 32, 60, 60 ] as $index => $minutes ) {
				$this->run_next_batch();
				$this->assertCount( 1, $this->get_pending_batches() );
				$this->assert_next_batch_delay( $minutes * MINUTE_IN_SECONDS );
				$this->assertSame( min( $index + 1, 5 ), (int) get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
			}
		} finally {
			remove_filter( 'query', $fail_query );
			$wpdb->suppress_errors( $suppress_errors );
		}

		$this->assertSame( 21, $this->count_password_rows( $order->get_id() ) );
		$this->run_next_batch();
		$this->assertSame( 1, $this->count_password_rows( $order->get_id() ) );
		$this->assertSame( 0, (int) get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
		$this->assert_next_batch_delay( 0 );
		$this->run_next_batch();
		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertFalse( get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
		$this->assertEmpty( $this->get_pending_batches() );
	}

	public function retry_failure_provider(): array {
		return [
			'failed read'      => [ 'read' ],
			'blocked deletion' => [ 'delete' ],
		];
	}

	/**
	 * Progress must shorten old backoff even when the following read fails.
	 */
	public function test_progress_resets_backoff_before_failed_remaining_read(): void {
		global $wpdb;

		$this->configure_storage( false );
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'account_password', 'password' );
		$order->save();
		update_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ), 5, false );
		$read_count      = 0;
		$fail_read       = static function ( $query ) use ( &$read_count ) {
			if ( 0 === strpos( ltrim( $query ), 'SELECT pm.meta_id AS row_id' ) && 2 === ++$read_count ) {
				return 'SELECT * FROM wc_stripe_missing_cleanup_table';
			}
			return $query;
		};
		$suppress_errors = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_read );
		try {
			( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();
		} finally {
			remove_filter( 'query', $fail_read );
			$wpdb->suppress_errors( $suppress_errors );
		}

		$this->assertSame( 0, $this->count_password_rows( $order->get_id() ) );
		$this->assertSame( 0, (int) get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
		$this->assert_next_batch_delay( 2 * MINUTE_IN_SECONDS );
		$this->assertNotSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
		$this->run_next_batch();
		$this->assertEmpty( $this->get_pending_batches() );
	}

	/**
	 * Existing cleanup recovery is retained; subsequent email retries are hourly.
	 *
	 * @dataProvider completion_state_provider
	 */
	public function test_failed_email_retries_hourly( bool $completed ): void {
		$this->configure_storage( false );
		if ( $completed ) {
			update_option( $this->get_constant_value( 'COMPLETE_OPTION' ), 'yes', false );
			update_option(
				$this->get_constant_value( 'RESULT_OPTION' ),
				[
					'affected'   => true,
					'user_count' => 0,
				],
				false
			);
		} else {
			$order = WC_Helper_Order::create_order();
			$order->update_meta_data( 'account_password', 'password' );
			$order->save();
		}
		add_filter( 'pre_wp_mail', '__return_false', 20 );
		try {
			( new WC_Stripe_Express_Checkout_Password_Cleanup() )->process_batch();
			$this->assertSame( 'yes', get_option( $this->get_constant_value( 'COMPLETE_OPTION' ) ) );
			$this->assertFalse( get_option( $this->get_constant_value( 'RETRY_COUNT_OPTION' ) ) );
			$this->assert_next_batch_delay( $completed ? HOUR_IN_SECONDS : 2 * MINUTE_IN_SECONDS );
			$this->run_next_batch();
			$this->assertCount( 1, $this->get_pending_batches() );
			$this->assert_next_batch_delay( HOUR_IN_SECONDS );
			$this->run_next_batch();
			$this->assertCount( 1, $this->get_pending_batches() );
			$this->assert_next_batch_delay( HOUR_IN_SECONDS );
		} finally {
			remove_filter( 'pre_wp_mail', '__return_false', 20 );
		}

		$this->run_next_batch();
		$this->assertEmpty( $this->get_pending_batches() );
	}

	/**
	 * @dataProvider notice_state_provider
	 */
	public function test_notice_reads_result_only_when_eligible( bool $completed, bool $dismissed, bool $affected, bool $expected ): void {
		update_option( $this->get_constant_value( 'COMPLETE_OPTION' ), $completed ? 'yes' : 'no', false );
		update_option( $this->get_constant_value( 'NOTICE_DISMISSED_OPTION' ), $dismissed ? 'yes' : 'no', false );

		$result_option = $this->get_constant_value( 'RESULT_OPTION' );
		$result_reads  = 0;
		$read_result   = static function () use ( &$result_reads, $affected ) {
			++$result_reads;
			return [ 'affected' => $affected ];
		};
		add_filter( 'pre_option_' . $result_option, $read_result );
		try {
			$this->assertSame( $expected, WC_Stripe_Express_Checkout_Password_Cleanup::should_show_notice() );
			$this->assertSame( $completed && ! $dismissed ? 1 : 0, $result_reads );
		} finally {
			remove_filter( 'pre_option_' . $result_option, $read_result );
		}
	}

	public function notice_state_provider(): array {
		return [
			'incomplete' => [ false, false, true, false ],
			'dismissed'  => [ true, true, true, false ],
			'unaffected' => [ true, false, false, false ],
			'affected'   => [ true, false, true, true ],
		];
	}

	/**
	 * Captures outgoing cleanup email without invoking the configured mail transport.
	 *
	 * @param null|bool $_short_circuit Short-circuit value.
	 * @param array     $attributes Mail attributes.
	 * @return bool
	 */
	public function capture_email( $_short_circuit, array $attributes ): bool {
		if ( '[WooCommerce Stripe] Customer password data removed from order records' === ( $attributes['subject'] ?? '' ) ) {
			$this->emails[] = $attributes;
		}
		return true;
	}

	/**
	 * Configures the active order data store.
	 *
	 * @param bool $use_hpos Enable HPOS.
	 * @param bool $sync Enable compatibility-mode synchronization.
	 * @return void
	 */
	private function configure_storage( bool $use_hpos, bool $sync = false ): void {
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', $sync ? 'yes' : 'no' );
		update_option( 'woocommerce_custom_orders_table_enabled', $use_hpos ? 'yes' : 'no' );
	}

	private function get_pending_batches(): array {
		return as_get_scheduled_actions(
			[
				'hook'   => WC_Stripe_Express_Checkout_Password_Cleanup::BATCH_HOOK,
				'args'   => [],
				'group'  => WC_Stripe_Action_Scheduler_Service::GROUP_ID,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
	}

	private function run_next_batch(): void {
		$pending = $this->get_pending_batches();
		$this->assertNotEmpty( $pending );
		$action_id = (int) reset( $pending );
		$this->assertSame( [], ActionScheduler::store()->fetch_action( $action_id )->get_args() );
		ActionScheduler::runner()->process_action( $action_id, 'test' );
		$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler::store()->get_status( $action_id ) );
	}

	private function assert_next_batch_delay( int $delay ): void {
		$pending = $this->get_pending_batches();
		$this->assertNotEmpty( $pending );
		$timestamp = ActionScheduler::store()->fetch_action( (int) reset( $pending ) )->get_schedule()->get_date()->getTimestamp();
		// Lower bound from a time captured before scheduling, upper bound from now: the batch was
		// scheduled between the two, so this holds without a slack that a slow runner can exceed.
		$this->assertGreaterThanOrEqual( $this->started_at + $delay, $timestamp );
		$this->assertLessThanOrEqual( time() + $delay, $timestamp );
	}

	/**
	 * Counts matching rows across both physical order stores.
	 *
	 * @param int $order_id Order ID.
	 * @return int
	 */
	private function count_password_rows( int $order_id ): int {
		global $wpdb;

		$legacy_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $order_id, 'account_password' ) );
		$hpos_count   = 0;
		$meta_table   = $wpdb->prefix . 'wc_orders_meta';
		if ( $meta_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $meta_table ) ) ) ) {
			$hpos_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE order_id = %d AND meta_key = %s', $meta_table, $order_id, 'account_password' ) );
		}

		return $legacy_count + $hpos_count;
	}

	/**
	 * Returns every option owned by the cleanup.
	 *
	 * @return string[]
	 */
	private function get_option_names(): array {
		return [
			$this->get_constant_value( 'COMPLETE_OPTION' ),
			$this->get_constant_value( 'LOCK_OPTION' ),
			$this->get_constant_value( 'RESULT_OPTION' ),
			$this->get_constant_value( 'EMAIL_ATTEMPTED_OPTION' ),
			$this->get_constant_value( 'NOTICE_DISMISSED_OPTION' ),
			$this->get_constant_value( 'RETRY_COUNT_OPTION' ),
		];
	}

	/**
	 * Returns a private cleanup option name.
	 *
	 * @param string $constant Constant name.
	 * @return string
	 */
	private function get_constant_value( string $constant ): string {
		return WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Express_Checkout_Password_Cleanup::class, $constant, 'string' );
	}

	/**
	 * Removes site-specific affected-account markers.
	 */
	private function delete_user_markers(): void {
		global $wpdb;

		$meta_key = $wpdb->prefix . $this->get_constant_value( 'USER_META_KEY' );
		$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
	}
}
