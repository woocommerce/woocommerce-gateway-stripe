<?php
/**
 * Smoke test: Scheduler -> Client -> Remote_Config -> resolver works end-to-end
 * with mocked HTTP. Component-level behaviors (circuit breaker, fetch failure
 * preservation, second-sync update) are covered by the unit tests; this file
 * only verifies the wiring between them.
 *
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Integration_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );
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
	}

	public function tear_down(): void {
		delete_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		parent::tear_down();
	}

	public function test_full_pull_validate_store_resolve_cycle(): void {
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
							'modes'        => [
								'live' => [
									'flags'        => [ 'optimized_checkout' => [ 'value' => false ] ],
									'generated_at' => '2026-05-09T12:00:00Z',
								],
								'test' => [
									'flags'        => [ 'optimized_checkout' => [ 'value' => true ] ],
									'generated_at' => '2026-05-09T12:00:00Z',
								],
							],
							'generated_at' => '2026-05-09T12:00:00Z',
						]
					),
					'headers'  => [],
				];
			},
			10,
			3
		);

		$rc        = new WC_Stripe_Remote_Config();
		$scheduler = new WC_Stripe_Remote_Config_Scheduler( new WC_Stripe_Remote_Config_Client(), $rc );

		// Local default is true; remote should override to false after sync.
		$this->assertTrue( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		$scheduler->run();
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		// The unconnected test mode is cached from the same combined fetch.
		$this->assertTrue( $rc->resolve( 'optimized_checkout', false, 'test' ) );
	}
}
