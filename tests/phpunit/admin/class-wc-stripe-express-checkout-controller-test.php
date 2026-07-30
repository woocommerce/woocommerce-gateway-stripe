<?php

/**
 * WC_Stripe_Express_Checkout_Controller_Test class
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */
class WC_Stripe_Express_Checkout_Controller_Test extends WP_UnitTestCase {
	/**
	 * The controller instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Controller
	 */
	protected WC_Stripe_Express_Checkout_Controller $controller;

	/**
	 * Test suite set up.
	 *
	 * @inheritDoc
	 */
	public function setUp(): void {
		parent::setUp();

		// Setup existing keys
		$settings                         = WC_Stripe::get_instance()->get_settings();
		$settings['publishable_key']      = 'original-live-key-9999';
		$settings['secret_key']           = '';
		$settings['test_publishable_key'] = 'original-test-key-9999';
		$settings['test_secret_key']      = '';
		WC_Stripe::get_instance()->update_settings( $settings );

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-express-checkout-controller.php';
		$this->controller = new WC_Stripe_Express_Checkout_Controller();
	}

	/**
	 * Tests for `admin_scripts` method.
	 *
	 * @return void
	 */
	public function test_admin_scripts(): void {
		$this->controller->admin_scripts();

		$this->assertTrue( wp_script_is( 'wc-stripe-express-checkout-settings', 'registered' ) );
		$this->assertTrue( wp_script_is( 'wc-stripe-express-checkout-settings', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wc-stripe-express-checkout-settings', 'registered' ) );
		$this->assertTrue( wp_style_is( 'wc-stripe-express-checkout-settings', 'enqueued' ) );

		// The seeded keys must reach the localized params via the gateway-backed settings path.
		$localized_data = wp_scripts()->get_data( 'wc-stripe-express-checkout-settings', 'data' );
		$expected_key   = WC_Stripe_Mode::is_test() ? 'original-test-key-9999' : 'original-live-key-9999';
		$this->assertStringContainsString( $expected_key, $localized_data );
	}

	/**
	 * Tests for `admin_options` method.
	 *
	 * @return void
	 */
	public function test_admin_options(): void {
		ob_start();
		$this->controller->admin_options();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wc-admin-header', $output );
		$this->assertStringContainsString( 'wc-stripe-express-checkout-settings', $output );
	}
}
