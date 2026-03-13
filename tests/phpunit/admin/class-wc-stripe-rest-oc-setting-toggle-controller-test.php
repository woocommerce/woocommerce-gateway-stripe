<?php

use Automattic\WooCommerce\Blocks\Package;

/**
 * Class WC_Stripe_REST_OC_Setting_Toggle_Controller_Test
 *
 * WC_Stripe_REST_OC_Setting_Toggle_Controller unit tests.
 */
class WC_Stripe_REST_OC_Setting_Toggle_Controller_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Tested REST route.
	 */
	const SETTINGS_ROUTE = '/wc/v3/wc_stripe/oc_setting_toggle';

	/**
	 * Controller instance
	 *
	 * @var WC_Stripe_REST_OC_Setting_Toggle_Controller
	 */
	private $controller;

	/**
	 * Enable UPE and store gateway instance.
	 *
	 * We are doing this here because if we did it in set_up(), the method body would get called before every single test
	 * however the REST controller is instantiated only once. If we reloaded gateways then, WC()->payment_gateways()
	 * would contain another gateway instance than the controller.
	 *
	 * @see UPE_Test_Utils::reload_payment_gateways()
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-oc-setting-toggle-controller.php';
	}

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::set_up();

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$gateway->method( 'is_oc_enabled' )
			->willReturn( true );

		$this->controller = new WC_Stripe_REST_OC_Setting_Toggle_Controller( $gateway );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
	}

	/**
	 * Tests for `set_setting` method.
	 *
	 * @return void
	 */
	public function test_get_setting() {
		$request  = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );
		$response = $this->controller->get_setting( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( true, $response->get_data()['is_oc_enabled'] );
	}

	/**
	 * Tests for `get_setting` method.
	 *
	 * @param bool|null $is_oc_enabled Whether the OC setting is enabled.
	 * @param string    $result        Expected result message.
	 * @param int       $status        Expected HTTP status code.
	 * @return void
	 *
	 * @dataProvider provide_test_set_setting
	 */
	public function test_set_setting( $is_oc_enabled, $result, $status ) {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_oc_enabled', $is_oc_enabled );

		$response = $this->controller->set_setting( $request );

		$this->assertSame( $status, $response->get_status() );
		$this->assertSame( $result, $response->get_data()['result'] );
	}

	/**
	 * Provider for `test_set_setting` method.
	 *
	 * @return array
	 */
	public function provide_test_set_setting() {
		return [
			'setting key is missing' => [
				'is_oc_enabled' => null,
				'result'        => 'bad_request',
				'status'        => 400,
			],
			'setting key is true'    => [
				'is_oc_enabled' => true,
				'result'        => 'success',
				'status'        => 200,
			],
			'setting key is false'   => [
				'is_oc_enabled' => false,
				'result'        => 'success',
				'status'        => 200,
			],
		];
	}
}
