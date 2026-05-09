<?php
/**
 * @package WooCommerce/Stripe
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-flags.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-client.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-scheduler.php';

class WC_Stripe_Remote_Config_Integration_Test extends WP_UnitTestCase {

	/** @var callable */
	private $http_handler;

	/**
	 * Saved Stripe settings, restored in tear_down.
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

		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'        => 'no',
				'publishable_key' => 'pk_live_xx',
				'secret_key'      => 'sk_live_xx',
			]
		);

		$this->http_handler = static function () {
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => wp_json_encode(
					[
						'flags'        => [ 'optimized_checkout' => [ 'value' => false ] ],
						'generated_at' => '2026-05-09T12:00:00Z',
						'ttl'          => 86400,
					]
				),
				'headers'  => [],
			];
		};
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
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

	public function test_full_pull_validate_store_resolve_cycle(): void {
		add_filter( 'pre_http_request', $this->http_handler, 10, 3 );

		$rc        = new WC_Stripe_Remote_Config();
		$scheduler = new WC_Stripe_Remote_Config_Scheduler( new WC_Stripe_Remote_Config_Client(), $rc );

		// Local default is true; remote should override to false.
		$this->assertTrue( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		$scheduler->run();
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
	}

	public function test_second_sync_updates_cache(): void {
		add_filter( 'pre_http_request', $this->http_handler, 10, 3 );

		$rc        = new WC_Stripe_Remote_Config();
		$scheduler = new WC_Stripe_Remote_Config_Scheduler( new WC_Stripe_Remote_Config_Client(), $rc );

		$scheduler->run();
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );

		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode(
						[
							'flags'        => [ 'optimized_checkout' => [ 'value' => true ] ],
							'generated_at' => '2026-05-09T13:00:00Z',
							'ttl'          => 86400,
						]
					),
					'headers'  => [],
				];
			},
			10,
			3
		);

		// Same request would normally memoize; reset to simulate a fresh request.
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		$scheduler->run();
		$this->assertTrue( $rc->resolve( 'optimized_checkout', false, 'live' ) );
	}

	public function test_fetch_failure_preserves_existing_cache(): void {
		add_filter( 'pre_http_request', $this->http_handler, 10, 3 );

		$rc        = new WC_Stripe_Remote_Config();
		$scheduler = new WC_Stripe_Remote_Config_Scheduler( new WC_Stripe_Remote_Config_Client(), $rc );
		$scheduler->run();
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );

		// Next pull fails.
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'boom' );
			}
		);

		$scheduler->run();
		// Cache survives.
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
	}

	public function test_schema_failure_preserves_existing_cache(): void {
		add_filter( 'pre_http_request', $this->http_handler, 10, 3 );

		$rc        = new WC_Stripe_Remote_Config();
		$scheduler = new WC_Stripe_Remote_Config_Scheduler( new WC_Stripe_Remote_Config_Client(), $rc );
		$scheduler->run();
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );

		// Next response has a string where a bool is required.
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode(
						[
							'flags'        => [ 'optimized_checkout' => [ 'value' => 'string-not-bool' ] ],
							'generated_at' => '2026-05-09T13:00:00Z',
							'ttl'          => 86400,
						]
					),
					'headers'  => [],
				];
			},
			10,
			3
		);

		WC_Stripe_Remote_Config::reset_in_memory_cache();
		$scheduler->run();
		// Cache survives.
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
	}
}
