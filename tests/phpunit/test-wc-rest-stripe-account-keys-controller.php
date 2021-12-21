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
	const ROUTE = '/wc/v3/wc_stripe/account_keys';

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

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		// Setup existing keys
		$settings                         = get_option( 'woocommerce_stripe_settings' );
		$settings['publishable_key']      = 'original-live-key-9999';
		$settings['test_publishable_key'] = 'original-test-key-9999';
		update_option( 'woocommerce_stripe_settings', $settings );

		$mock_account = $this->getMockBuilder( WC_Stripe_Account::class )
							 ->disableOriginalConstructor()
							 ->getMock();

		$this->controller = new WC_REST_Stripe_Account_Keys_Controller( $mock_account );
	}

	public function test_get_account_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'GET', self::ROUTE );

		$response = $this->controller->get_account_keys( $request );
		$expected = [
			'test_publishable_key' => 'original-test-key-9999',
			'test_secret_key'      => '',
			'test_webhook_secret'  => '',
			'publishable_key'      => 'original-live-key-9999',
			'secret_key'           => '',
			'webhook_secret'       => '',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );
	}

	/**
	 * Test as if the user update publishable_keys, secret_key, webhook
	 */
	public function test_adding_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'pk_live-key-12345' );
		$request->set_param( 'secret_key', 'sk_live_secret-key-12345' );
		$request->set_param( 'webhook_secret', 'webhook-secret-12345' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( 'pk_live-key-12345', $settings['publishable_key'] );
		$this->assertEquals( 'sk_live_secret-key-12345', $settings['secret_key'] );
		$this->assertEquals( 'webhook-secret-12345', $settings['webhook_secret'] );

		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	/**
	 * Test as if the user update test publishable_keys, secret_key, webhook
	 */
	public function test_adding_test_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'test_publishable_key', 'pk_test-live-key-12345' );
		$request->set_param( 'test_secret_key', 'sk_test-secret-key-12345' );
		$request->set_param( 'test_webhook_secret', 'test-webhook-secret-12345' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( 'pk_test-live-key-12345', $settings['test_publishable_key'] );
		$this->assertEquals( 'sk_test-secret-key-12345', $settings['test_secret_key'] );
		$this->assertEquals( 'test-webhook-secret-12345', $settings['test_webhook_secret'] );

		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-live-key-9999', $settings['publishable_key'] );
	}

	/**
	 * Test updating only 1 property
	 */
	public function test_update_live_key_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'pk_live-key-12345' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( 'pk_live-key-12345', $settings['publishable_key'] );
		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	/**
	 * Test updating a key to "", as if user deleting the key.
	 */
	public function test_setting_blank_live_key_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', '' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = get_option( 'woocommerce_stripe_settings' );

		$this->assertEquals( '', $settings['publishable_key'] );
		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	public function test_validate_publishable_key() {
		$expected_wp_error = new WP_Error( 400, 'The "Live Publishable Key" should start with "pk_live", enter the correct key.' );

		$data_provider = [
			''               => true,
			'asd'            => $expected_wp_error,
			'pk_live_123123' => true,
			'sk_live_123123' => $expected_wp_error,
			'rk_live_123123' => $expected_wp_error,
			'pk_test_123123' => $expected_wp_error,
		];

		foreach ( $data_provider as $param => $expected ) {
			$request = new WP_REST_Request( 'POST', self::ROUTE );
			$request->set_param( 'publishable_key', $param );

			$response = $this->controller->validate_publishable_key( $param, $request, 'publishable_key' );

			$this->assertEquals( $expected, $response, "Testing param: $param" );
		}
	}

	public function test_validate_secret_key() {
		$expected_wp_error = new WP_Error( 400, 'The "Live Secret Key" should start with "sk_live" or "rk_live", enter the correct key.' );

		$data_provider = [
			''               => true,
			'asd'            => $expected_wp_error,
			'pk_live_123123' => $expected_wp_error,
			'sk_live_123123' => true,
			'rk_live_123123' => true,
			'sk_test_123123' => $expected_wp_error,
		];

		foreach ( $data_provider as $param => $expected ) {
			$request = new WP_REST_Request( 'POST', self::ROUTE );
			$request->set_param( 'secret_key', $param );

			$response = $this->controller->validate_secret_key( $param, $request, 'secret_key' );

			$this->assertEquals( $expected, $response, "Testing param: $param" );
		}
	}

	public function test_validate_test_publishable_key() {
		$expected_wp_error = new WP_Error( 400, 'The "Test Publishable Key" should start with "pk_test", enter the correct key.' );

		$data_provider = [
			''               => true,
			'asd'            => $expected_wp_error,
			'pk_test_123123' => true,
			'sk_test_123123' => $expected_wp_error,
			'rk_test_123123' => $expected_wp_error,
			'pk_live_123123' => $expected_wp_error,
		];

		foreach ( $data_provider as $param => $expected ) {
			$request = new WP_REST_Request( 'POST', self::ROUTE );
			$request->set_param( 'test_publishable_key', $param );

			$response = $this->controller->validate_test_publishable_key( $param, $request, 'test_publishable_key' );

			$this->assertEquals( $expected, $response, "Testing param: $param" );
		}
	}

	public function test_validate_test_secret_key() {
		$expected_wp_error = new WP_Error( 400, 'The "Test Secret Key" should start with "sk_test" or "rk_test", enter the correct key.' );

		$data_provider = [
			''               => true,
			'asd'            => $expected_wp_error,
			'pk_test_123123' => $expected_wp_error,
			'sk_test_123123' => true,
			'rk_test_123123' => true,
			'sk_live_123123' => $expected_wp_error,
		];

		foreach ( $data_provider as $param => $expected ) {
			$request = new WP_REST_Request( 'POST', self::ROUTE );
			$request->set_param( 'test_secret_key', $param );

			$response = $this->controller->validate_test_secret_key( $param, $request, 'test_secret_key' );

			$this->assertEquals( $expected, $response, "Testing param: $param" );
		}
	}

}
