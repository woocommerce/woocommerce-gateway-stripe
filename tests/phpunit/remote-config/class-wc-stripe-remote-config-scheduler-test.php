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
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );
		$this->original_stripe_settings = get_option( 'woocommerce_stripe_settings' );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		delete_option( WC_Stripe_Remote_Config_Scheduler::FAILURE_COUNT_OPTION );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], 'woocommerce-gateway-stripe' );
		}
	}

	public function tear_down(): void {
		delete_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION );
		delete_option( WC_Stripe_Remote_Config_Scheduler::FAILURE_COUNT_OPTION );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], 'woocommerce-gateway-stripe' );
		}
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		if ( false === $this->original_stripe_settings ) {
			delete_option( 'woocommerce_stripe_settings' );
		} else {
			update_option( 'woocommerce_stripe_settings', $this->original_stripe_settings );
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
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'no' );
		$disabled_client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$disabled_client->expects( $this->never() )->method( 'fetch_all' );
		( new WC_Stripe_Remote_Config_Scheduler( $disabled_client, new WC_Stripe_Remote_Config() ) )->run();
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );

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
	 * Builds a scheduler whose client always fails with the given error code.
	 *
	 * @param string $error_code WP_Error code the client returns.
	 * @return WC_Stripe_Remote_Config_Scheduler
	 */
	private function get_failing_scheduler( string $error_code = 'wc_stripe_remote_config_http_error' ): WC_Stripe_Remote_Config_Scheduler {
		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->method( 'fetch_all' )->willReturn( new WP_Error( $error_code, 'boom' ) );

		return new WC_Stripe_Remote_Config_Scheduler( $client, new WC_Stripe_Remote_Config() );
	}

	/**
	 * A failed fetch must schedule the next backoff retry at the delay for its
	 * attempt number and increment the consecutive-failure counter.
	 *
	 * @param int $attempt        In-cycle attempt number of the failing run.
	 * @param int $expected_delay Expected seconds until the scheduled retry.
	 *
	 * @dataProvider provide_retry_attempts
	 */
	public function test_failed_fetch_schedules_backoff_retry( int $attempt, int $expected_delay ): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$this->configure_modes( true, false );

		$before = time();
		$this->get_failing_scheduler()->run( $attempt );

		$timestamp = as_next_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [ $attempt + 1 ], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP );
		$this->assertIsInt( $timestamp, 'A retry must be scheduled for the next attempt.' );
		$this->assertGreaterThanOrEqual( $before + $expected_delay, $timestamp );
		$this->assertLessThanOrEqual( time() + $expected_delay, $timestamp );
		$this->assertSame( 1, (int) get_option( WC_Stripe_Remote_Config_Scheduler::FAILURE_COUNT_OPTION ) );
	}

	/**
	 * Data provider for {@see test_failed_fetch_schedules_backoff_retry()}.
	 *
	 * @return array
	 */
	public function provide_retry_attempts(): array {
		return [
			'first failure retries in 1h'  => [ 0, HOUR_IN_SECONDS ],
			'second failure retries in 4h' => [ 1, 4 * HOUR_IN_SECONDS ],
		];
	}

	/**
	 * The last in-cycle retry failing must not schedule another attempt; the
	 * next contact is the daily run. The failure is still counted.
	 */
	public function test_last_retry_failure_schedules_no_further_attempt(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$this->configure_modes( true, false );

		$this->get_failing_scheduler()->run( 2 );

		$this->assertFalse( as_next_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [ 3 ], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP ) );
		$this->assertSame( 1, (int) get_option( WC_Stripe_Remote_Config_Scheduler::FAILURE_COUNT_OPTION ) );
	}

	/**
	 * The deliberate disabled-channel error must not spawn a retry chain.
	 */
	public function test_disabled_error_is_not_retried(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$this->configure_modes( true, false );

		$this->get_failing_scheduler( 'wc_stripe_remote_config_disabled' )->run();

		$this->assertFalse( as_next_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [ 1 ], WC_Stripe_Remote_Config_Scheduler::SCHEDULER_GROUP ) );
	}

	/**
	 * A successful fetch must reset the consecutive-failure counter so the
	 * count always reflects the current outage, not history.
	 */
	public function test_successful_fetch_resets_failure_counter(): void {
		$this->configure_modes( true, false );
		update_option( WC_Stripe_Remote_Config_Scheduler::FAILURE_COUNT_OPTION, 5, false );

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->method( 'fetch_all' )->willReturn( $this->get_mock_combined_payload( false, true ) );

		( new WC_Stripe_Remote_Config_Scheduler( $client, new WC_Stripe_Remote_Config() ) )->run();

		$this->assertSame( 0, (int) get_option( WC_Stripe_Remote_Config_Scheduler::FAILURE_COUNT_OPTION, 0 ) );
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
