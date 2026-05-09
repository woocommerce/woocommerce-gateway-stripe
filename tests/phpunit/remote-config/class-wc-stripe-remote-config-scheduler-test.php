<?php
/**
 * @package WooCommerce/Stripe
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-flags.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-client.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-scheduler.php';

class WC_Stripe_Remote_Config_Scheduler_Test extends WP_UnitTestCase {

	/**
	 * Saved Stripe settings, so we can restore them in tear_down.
	 *
	 * @var array|false
	 */
	private $original_stripe_settings;

	public function set_up(): void {
		parent::set_up();
		$this->original_stripe_settings = get_option( 'woocommerce_stripe_settings' );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], 'woocommerce-gateway-stripe' );
		}
	}

	public function tear_down(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [], 'woocommerce-gateway-stripe' );
		}
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		// Restore original Stripe settings to avoid leaking into subsequent tests.
		if ( false === $this->original_stripe_settings ) {
			delete_option( 'woocommerce_stripe_settings' );
		} else {
			update_option( 'woocommerce_stripe_settings', $this->original_stripe_settings );
		}
		parent::tear_down();
	}

	public function test_init_hooks_registers_action_callback(): void {
		$scheduler = new WC_Stripe_Remote_Config_Scheduler();
		$scheduler->init_hooks();

		$this->assertNotFalse( has_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION, [ $scheduler, 'run' ] ) );
		$this->assertNotFalse( has_action( 'upgrader_process_complete', [ $scheduler, 'on_plugin_upgrade' ] ) );
	}

	public function test_on_plugin_upgrade_enqueues_immediate_single_action(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$scheduler = new WC_Stripe_Remote_Config_Scheduler();
		$scheduler->on_plugin_upgrade(
			null,
			[
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => [ 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ],
			]
		);

		$this->assertTrue( as_has_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION ) );
	}

	public function test_on_plugin_upgrade_ignores_unrelated_plugin_updates(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$scheduler = new WC_Stripe_Remote_Config_Scheduler();
		$scheduler->on_plugin_upgrade(
			null,
			[
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => [ 'some-other-plugin/some-other-plugin.php' ],
			]
		);

		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION ) );
	}

	public function test_on_plugin_upgrade_ignores_non_plugin_upgrades(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$scheduler = new WC_Stripe_Remote_Config_Scheduler();
		$scheduler->on_plugin_upgrade(
			null,
			[
				'action' => 'update',
				'type'   => 'theme',
			]
		);

		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Remote_Config_Scheduler::SYNC_ACTION ) );
	}

	public function test_run_calls_client_and_remote_config_for_each_connected_mode(): void {
		// Configure: live connected, test not.
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'             => 'no',
				'publishable_key'      => 'pk_live_xx',
				'secret_key'           => 'sk_live_xx',
				'test_publishable_key' => '',
				'test_secret_key'      => '',
			]
		);

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->once() )
			->method( 'fetch' )
			->with( 'live' )
			->willReturn(
				[
					'flags'        => [ 'optimized_checkout' => [ 'value' => false ] ],
					'generated_at' => '2026-05-09T12:00:00Z',
					'ttl'          => 86400,
				]
			);

		$rc = new WC_Stripe_Remote_Config();

		$scheduler = new WC_Stripe_Remote_Config_Scheduler( $client, $rc );
		$scheduler->run();

		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'test' ) );
	}

	public function test_run_calls_both_modes_when_both_connected(): void {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'             => 'no',
				'publishable_key'      => 'pk_live_xx',
				'secret_key'           => 'sk_live_xx',
				'test_publishable_key' => 'pk_test_xx',
				'test_secret_key'      => 'sk_test_xx',
			]
		);

		$payload = static function ( bool $val ): array {
			return [
				'flags'        => [ 'optimized_checkout' => [ 'value' => $val ] ],
				'generated_at' => '2026-05-09T12:00:00Z',
				'ttl'          => 86400,
			];
		};

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->exactly( 2 ) )
			->method( 'fetch' )
			->willReturnCallback(
				static function ( string $mode ) use ( $payload ) {
					return 'live' === $mode ? $payload( false ) : $payload( true );
				}
			);

		$rc = new WC_Stripe_Remote_Config();

		$scheduler = new WC_Stripe_Remote_Config_Scheduler( $client, $rc );
		$scheduler->run();

		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
		$this->assertSame( true, $rc->get_flag( 'optimized_checkout', 'test' ) );
	}

	public function test_run_skips_when_disabled(): void {
		add_filter( 'wc_stripe_remote_config_enabled', '__return_false' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'        => 'no',
				'publishable_key' => 'pk_live_xx',
				'secret_key'      => 'sk_live_xx',
			]
		);

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->never() )->method( 'fetch' );

		$scheduler = new WC_Stripe_Remote_Config_Scheduler( $client, new WC_Stripe_Remote_Config() );
		$scheduler->run();

		remove_filter( 'wc_stripe_remote_config_enabled', '__return_false' );
	}

	public function test_run_swallows_client_errors(): void {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'        => 'no',
				'publishable_key' => 'pk_live_xx',
				'secret_key'      => 'sk_live_xx',
			]
		);

		$client = $this->createMock( WC_Stripe_Remote_Config_Client::class );
		$client->expects( $this->once() )
			->method( 'fetch' )
			->willReturn( new WP_Error( 'wc_stripe_remote_config_http_error', 'boom' ) );

		$rc        = new WC_Stripe_Remote_Config();
		$scheduler = new WC_Stripe_Remote_Config_Scheduler( $client, $rc );

		$scheduler->run(); // Must not throw.
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'live' ) );
	}
}
