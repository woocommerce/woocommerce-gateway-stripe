<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Scheduler_Test extends WP_UnitTestCase {

	/**
	 * Saved Stripe settings, restored in tear_down.
	 *
	 * @var array|false
	 */
	private $original_stripe_settings;

	public function set_up(): void {
		parent::set_up();
		update_option( '_wcstripe_remote_config_enabled', 'yes' );
		$this->original_stripe_settings = get_option( 'woocommerce_stripe_settings' );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP );
		}
	}

	public function tear_down(): void {
		delete_option( '_wcstripe_remote_config_enabled' );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		if ( false === $this->original_stripe_settings ) {
			delete_option( 'woocommerce_stripe_settings' );
		} else {
			update_option( 'woocommerce_stripe_settings', $this->original_stripe_settings );
		}
		// Unschedule after restoring the settings: the restore runs the
		// connection-change hook, which can enqueue a new sync.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP );
		}
		parent::tear_down();
	}

	private function configure_modes( bool $live, bool $test ): void {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'             => 'no',
				'publishable_key'      => $live ? 'pk_live_xx' : '',
				'secret_key'           => $live ? 'sk_live_xx' : '',
				'test_publishable_key' => $test ? 'pk_test_xx' : '',
				'test_secret_key'      => $test ? 'sk_test_xx' : '',
			]
		);
	}

	private function get_mock_payload( bool $optimized_checkout_flag_value ): array {
		return [
			'flags'        => [ 'optimized_checkout' => [ 'value' => $optimized_checkout_flag_value ] ],
			'generated_at' => '2026-05-09T12:00:00Z',
		];
	}

	private function get_mock_combined_payload( bool $live_flag_value, bool $test_flag_value ): array {
		return [
			'modes'        => [
				'live' => $this->get_mock_payload( $live_flag_value ),
				'test' => $this->get_mock_payload( $test_flag_value ),
			],
			'generated_at' => '2026-05-09T12:00:00Z',
		];
	}

	public function test_init_hooks_registers_action_callback(): void {
		$scheduler = new WC_Stripe_Remote_Config_Scheduler();
		$scheduler->init_hooks();

		$this->assertNotFalse( has_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [ $scheduler, 'run' ] ) );
		$this->assertNotFalse( has_action( 'woocommerce_stripe_updated', [ WC_Stripe_Remote_Config_Scheduler::class, 'on_plugin_upgrade' ] ) );
		$this->assertNotFalse( has_action( 'update_option_woocommerce_stripe_settings', [ WC_Stripe_Remote_Config_Scheduler::class, 'maybe_sync_on_connection_change' ] ) );
	}

	/**
	 * A settings change that affects the connection (keys or test/live mode)
	 * must enqueue an immediate sync; unrelated settings churn must not.
	 *
	 * @param mixed $old_value    Previous settings option value.
	 * @param mixed $new_value    New settings option value.
	 * @param bool  $expects_sync Whether a sync action must be enqueued.
	 *
	 * @dataProvider provide_connection_change_scenarios
	 */
	public function test_connection_change_enqueues_sync( $old_value, $new_value, bool $expects_sync ): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		WC_Stripe_Remote_Config_Scheduler::maybe_sync_on_connection_change( $old_value, $new_value );

		$this->assertSame( $expects_sync, as_has_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION ) );
	}

	/**
	 * Data provider for {@see test_connection_change_enqueues_sync()}.
	 *
	 * @return array
	 */
	public function provide_connection_change_scenarios(): array {
		return [
			'live secret key added'     => [
				[ 'secret_key' => '' ],
				[ 'secret_key' => 'sk_live_xx' ],
				true,
			],
			'test secret key added'     => [
				[],
				[ 'test_secret_key' => 'sk_test_xx' ],
				true,
			],
			'mode switched'             => [
				[ 'testmode' => 'yes' ],
				[ 'testmode' => 'no' ],
				true,
			],
			'non-array previous value'  => [
				false,
				[ 'secret_key' => 'sk_live_xx' ],
				true,
			],
			'live secret key changed'   => [
				[ 'secret_key' => 'sk_live_old' ],
				[ 'secret_key' => 'sk_live_new' ],
				true,
			],
			'live secret key removed'   => [
				[ 'secret_key' => 'sk_live_xx' ],
				[ 'secret_key' => '' ],
				false,
			],
			'test secret key removed'   => [
				[ 'test_secret_key' => 'sk_test_xx' ],
				[],
				false,
			],
			'account disconnected'      => [
				[
					'testmode'        => 'no',
					'secret_key'      => 'sk_live_xx',
					'test_secret_key' => 'sk_test_xx',
				],
				[
					'testmode'        => 'no',
					'secret_key'      => '',
					'test_secret_key' => '',
				],
				false,
			],
			'unrelated setting changed' => [
				[
					'title'      => 'Cards',
					'secret_key' => 'sk_live_xx',
				],
				[
					'title'      => 'Credit cards',
					'secret_key' => 'sk_live_xx',
				],
				false,
			],
		];
	}

	public function test_on_plugin_upgrade_enqueues_async_sync(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		WC_Stripe_Remote_Config_Scheduler::on_plugin_upgrade();

		$this->assertTrue( as_has_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION ) );
	}

	/**
	 * The first run is anchored at tomorrow 00:00 UTC and shifted by the offset,
	 * so the window spans the whole day: a 0 offset lands at the anchor and the
	 * maximum offset lands at the far end. Pinning the offset keeps this
	 * deterministic; the offset's own range is covered separately.
	 *
	 * @dataProvider provide_schedule_offset_bounds
	 * @return void
	 */
	public function test_maybe_schedule_daily_sync_applies_offset_across_the_full_day( int $offset ): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		// Satisfy the action_scheduler_init guard in maybe_schedule_daily_sync().
		do_action( 'action_scheduler_init' );
		as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP );

		$scheduler = $this->getMockBuilder( WC_Stripe_Remote_Config_Scheduler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_schedule_offset' ] )
			->getMock();
		$scheduler->method( 'get_schedule_offset' )->willReturn( $offset );

		$scheduler->maybe_schedule_daily_sync();

		$next = as_next_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP );
		$this->assertSame( strtotime( 'tomorrow midnight UTC' ) + $offset, $next );
	}

	/**
	 * Data provider for {@see test_maybe_schedule_daily_sync_applies_offset_across_the_full_day()}.
	 *
	 * @return array
	 */
	public function provide_schedule_offset_bounds(): array {
		return [
			'start of the day' => [ 0 ],
			'end of the day'   => [ DAY_IN_SECONDS - 1 ],
		];
	}

	/**
	 * The offset stays within the 24h window, so the first run never spills into
	 * a neighbouring day.
	 *
	 * @return void
	 */
	public function test_get_schedule_offset_stays_within_the_day(): void {
		$method = new ReflectionMethod( WC_Stripe_Remote_Config_Scheduler::class, 'get_schedule_offset' );
		$method->setAccessible( true );
		$scheduler = new WC_Stripe_Remote_Config_Scheduler();

		for ( $i = 0; $i < 100; $i++ ) {
			$offset = $method->invoke( $scheduler );
			$this->assertGreaterThanOrEqual( 0, $offset );
			$this->assertLessThan( DAY_IN_SECONDS, $offset );
		}
	}

	/**
	 * One combined fetch caches both modes' payloads — including the mode
	 * without keys, so a later go-live starts from a warm cache.
	 */
	public function test_run_fetches_once_and_caches_both_modes(): void {
		$this->configure_modes( true, false );

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->once() )
			->method( 'fetch_all' )
			->willReturn( $this->get_mock_combined_payload( false, true ) );

		$rc = new WC_Stripe_Remote_Config();
		( new WC_Stripe_Remote_Config_Scheduler( $client, $rc ) )->run();

		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
		$this->assertSame( true, $rc->get_flag( 'optimized_checkout', 'test' ) );
	}

	/**
	 * A store with no Stripe keys in either mode must not phone home.
	 */
	public function test_run_skips_when_no_mode_connected(): void {
		$this->configure_modes( false, false );

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->never() )->method( 'fetch_all' );

		( new WC_Stripe_Remote_Config_Scheduler( $client, new WC_Stripe_Remote_Config() ) )->run();
	}

	public function test_run_skips_when_disabled_and_swallows_errors(): void {
		$this->configure_modes( true, false );

		// Disabled by override: client must not be called.
		update_option( '_wcstripe_remote_config_enabled', 'no' );
		$disabled_client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$disabled_client->expects( $this->never() )->method( 'fetch_all' );
		( new WC_Stripe_Remote_Config_Scheduler( $disabled_client, new WC_Stripe_Remote_Config() ) )->run();
		update_option( '_wcstripe_remote_config_enabled', 'yes' );

		// Enabled but client returns WP_Error: must not throw, cache stays empty.
		$err_client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$err_client->expects( $this->once() )
			->method( 'fetch_all' )
			->willReturn( new WP_Error( 'wc_stripe_remote_config_http_error', 'boom' ) );
		$rc = new WC_Stripe_Remote_Config();
		( new WC_Stripe_Remote_Config_Scheduler( $err_client, $rc ) )->run();
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'live' ) );
	}

	/**
	 * A combined response missing one mode's payload must apply the other and
	 * leave the missing mode's cache untouched.
	 */
	public function test_run_applies_partial_combined_response(): void {
		$this->configure_modes( true, true );

		$partial = $this->get_mock_combined_payload( false, true );
		unset( $partial['modes']['test'] );

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->once() )
			->method( 'fetch_all' )
			->willReturn( $partial );

		$rc = new WC_Stripe_Remote_Config();
		( new WC_Stripe_Remote_Config_Scheduler( $client, $rc ) )->run();

		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'test' ) );
	}
}
