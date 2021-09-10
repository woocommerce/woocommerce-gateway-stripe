<?php
/**
 * These tests make assertions against class WC_REST_Stripe_Account_Keys_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_REST_Stripe_Account_Keys_Controller
 */

/**
 * WC_REST_Stripe_Account_Keys_Controller unit tests.
 */
class WC_REST_Stripe_Account_Keys_Controller_Test extends WP_UnitTestCase {
	/**
	 * Tested REST route.
	 */
	const ROUTE = '/wc/v3/wc_stripe/upe_flag_toggle';

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Stripe_Account_Keys_Controller
	 */
	private $controller;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-account-keys-controller.php';

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		// Setup existing keys
		$settings = get_option( 'woocommerce_stripe_settings' );
		$settings['publishable_key'] = 'original-live-key-9999';
		$settings['test_publishable_key'] = 'original-test-key-9999';
		update_option( 'woocommerce_stripe_settings', $settings );

		$this->controller = new WC_REST_Stripe_Account_Keys_Controller();
	}

	/**
	 * Test as if the user update publishable_keys, secret_key, webhook
	 */
	public function test_adding_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'live-key-12345' );
		$request->set_param( 'secret_key', 'secret-key-12345' );
		$request->set_param( 'webhook_secret', 'webhook-secret-12345' );

		$response = $this->controller->set_account_keys( $request );
		$expected = [
			'result' => 'success',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( 'live-key-12345', $settings['publishable_key'] );
		$this->assertEquals( 'secret-key-12345', $settings['secret_key'] );
		$this->assertEquals( 'webhook-secret-12345', $settings['webhook_secret'] );

		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	/**
	 * Test as if the user update test publishable_keys, secret_key, webhook
	 */
	public function test_adding_test_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'test_publishable_key', 'test-live-key-12345' );
		$request->set_param( 'test_secret_key', 'test-secret-key-12345' );
		$request->set_param( 'test_webhook_secret', 'test-webhook-secret-12345' );

		$response = $this->controller->set_account_keys( $request );
		$expected = [
			'result' => 'success',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( 'test-live-key-12345', $settings['test_publishable_key'] );
		$this->assertEquals( 'test-secret-key-12345', $settings['test_secret_key'] );
		$this->assertEquals( 'test-webhook-secret-12345', $settings['test_webhook_secret'] );

		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-live-key-9999', $settings['publishable_key'] );
	}

	/**
	 * Test updating only 1 property
	 */
	public function test_update_live_key_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'live-key-12345' );

		$response = $this->controller->set_account_keys( $request );
		$expected = [
			'result' => 'success',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( 'live-key-12345', $settings['publishable_key'] );
		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}
}
