<?php

/**
 * WC_Stripe_Link_Controller_Test class
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */
class WC_Stripe_Link_Controller_Test extends WP_UnitTestCase {
	/**
	 * The controller instance.
	 *
	 * @var WC_Stripe_Link_Controller
	 */
	protected WC_Stripe_Link_Controller $controller;

	/**
	 * Test suite set up.
	 *
	 * @inheritDoc
	 */
	public function setUp(): void {
		parent::setUp();

		$settings                         = WC_Stripe_Helper::get_stripe_settings();
		$settings['publishable_key']      = 'original-live-key-9999';
		$settings['test_publishable_key'] = 'original-test-key-9999';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-link-controller.php';
		$this->controller = new WC_Stripe_Link_Controller();
	}

	/**
	 * Tests for `admin_scripts` method.
	 *
	 * @return void
	 */
	public function test_admin_scripts(): void {
		$this->controller->admin_scripts();

		$this->assertTrue( wp_script_is( 'wc-stripe-link-settings', 'registered' ) );
		$this->assertTrue( wp_script_is( 'wc-stripe-link-settings', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wc-stripe-link-settings', 'registered' ) );
		$this->assertTrue( wp_style_is( 'wc-stripe-link-settings', 'enqueued' ) );
	}

	/**
	 * The settings page simulator relies on these gate params being localized; assert they ship.
	 *
	 * @return void
	 */
	public function test_admin_scripts_localizes_simulator_gate_params(): void {
		$this->controller->admin_scripts();

		$data = wp_scripts()->get_data( 'wc-stripe-link-settings', 'data' );

		$this->assertIsString( $data );
		$this->assertStringContainsString( 'is_account_connected', $data );
		$this->assertStringContainsString( 'is_https', $data );
		$this->assertStringContainsString( 'is_test_mode', $data );
	}
}
