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
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], 'woocommerce-gateway-stripe' );
		}
	}

	public function tear_down(): void {
		delete_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION );
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

	public function test_run_iterates_only_connected_modes(): void {
		$this->configure_modes( true, false );

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->once() )
			->method( 'fetch' )
			->with( 'live' )
			->willReturn( $this->get_mock_payload( false ) );

		$rc = new WC_Stripe_Remote_Config();
		( new WC_Stripe_Remote_Config_Scheduler( $client, $rc ) )->run();

		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'test' ) );
	}

	public function test_run_iterates_both_modes_when_both_connected(): void {
		$this->configure_modes( true, true );

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->exactly( 2 ) )
			->method( 'fetch' )
			->willReturnCallback(
				function ( string $mode ): array {
					return $this->get_mock_payload( 'test' === $mode );
				}
			);

		$rc = new WC_Stripe_Remote_Config();
		( new WC_Stripe_Remote_Config_Scheduler( $client, $rc ) )->run();

		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
		$this->assertSame( true, $rc->get_flag( 'optimized_checkout', 'test' ) );
	}

	public function test_run_skips_when_disabled_and_swallows_errors(): void {
		$this->configure_modes( true, false );

		// Disabled by override: client must not be called.
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'no' );
		$disabled_client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$disabled_client->expects( $this->never() )->method( 'fetch' );
		( new WC_Stripe_Remote_Config_Scheduler( $disabled_client, new WC_Stripe_Remote_Config() ) )->run();
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );

		// Enabled but client returns WP_Error: must not throw, cache stays empty.
		$err_client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$err_client->expects( $this->once() )
			->method( 'fetch' )
			->willReturn( new WP_Error( 'wc_stripe_remote_config_http_error', 'boom' ) );
		$rc = new WC_Stripe_Remote_Config();
		( new WC_Stripe_Remote_Config_Scheduler( $err_client, $rc ) )->run();
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'live' ) );
	}
}
