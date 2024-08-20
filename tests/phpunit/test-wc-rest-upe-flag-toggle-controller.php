<?php
/**
 * These tests make assertions against class WC_Stripe_REST_UPE_Flag_Toggle_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_REST_UPE_Flag_Toggle_Controller
 */

/**
 * WC_Stripe_REST_UPE_Flag_Toggle_Controller unit tests.
 */
class WC_Stripe_REST_UPE_Flag_Toggle_Controller_Test extends WP_UnitTestCase {
	/**
	 * Tested REST route.
	 */
	const ROUTE = '/wc/v3/wc_stripe/upe_flag_toggle';

	/**
	 * The system under test.
	 *
	 * @var WC_Stripe_REST_UPE_Flag_Toggle_Controller
	 */
	private $controller;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-upe-flag-toggle-controller.php';

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		// Disable UPE.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->controller = new WC_Stripe_REST_UPE_Flag_Toggle_Controller();
	}

	public function test_get_flag_request_returns_status_code_200() {
		$response = $this->controller->get_flag();
		$expected = [
			'is_upe_enabled' => false,
		];
		$this->assertEquals( $expected, $response->get_data() );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_set_flag_enabled_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'is_upe_enabled', true );

		$response = $this->controller->set_flag( $request );
		$expected = [
			'result' => 'success',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertEquals( 'yes', $settings['upe_checkout_experience_enabled'] );
	}

	public function test_set_flag_disabled_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'is_upe_enabled', false );

		$response = $this->controller->set_flag( $request );
		$expected = [
			'result' => 'success',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertEquals( 'disabled', $settings['upe_checkout_experience_enabled'] );
	}

	public function test_set_flag_missing_request_returns_status_code_400() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );

		$response = $this->controller->set_flag( $request );
		$expected = [
			'result' => 'bad_request',
		];

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );
	}
}
