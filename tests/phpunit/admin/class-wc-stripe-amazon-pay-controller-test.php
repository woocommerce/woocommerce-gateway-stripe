<?php

/**
 * WC_Stripe_Amazon_Pay_Controller_Test class
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */
class WC_Stripe_Amazon_Pay_Controller_Test extends WP_UnitTestCase {
	/**
	 * The controller instance.
	 *
	 * @var WC_Stripe_Amazon_Pay_Controller
	 */
	protected WC_Stripe_Amazon_Pay_Controller $controller;

	/**
	 * Test suite set up.
	 *
	 * @inheritDoc
	 */
	public function setUp(): void {
		parent::setUp();

		// Setup existing keys
		$settings                         = WC_Stripe_Helper::get_stripe_settings();
		$settings['publishable_key']      = 'original-live-key-9999';
		$settings['secret_key']           = '';
		$settings['test_publishable_key'] = 'original-test-key-9999';
		$settings['test_secret_key']      = '';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-amazon-pay-controller.php';
		$this->controller = new WC_Stripe_Amazon_Pay_Controller();
	}

	/**
	 * The Amazon Pay simulator reads its account-country and currency eligibility from the
	 * localized params rather than a client-side copy of the rules; assert they ship along
	 * with the shared gate params.
	 *
	 * @return void
	 */
	public function test_admin_scripts_localizes_simulator_params(): void {
		$this->controller->admin_scripts();

		$data = wp_scripts()->get_data( 'wc-stripe-amazon-pay-settings', 'data' );

		$this->assertIsString( $data );
		$this->assertStringContainsString( 'is_account_connected', $data );
		$this->assertStringContainsString( 'is_https', $data );
		$this->assertStringContainsString( 'is_test_mode', $data );
		$this->assertStringContainsString( 'is_account_country_supported', $data );
		$this->assertStringContainsString( 'supported_currencies', $data );
	}
}
