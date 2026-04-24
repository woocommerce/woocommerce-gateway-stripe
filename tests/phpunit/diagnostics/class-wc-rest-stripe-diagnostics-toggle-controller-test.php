<?php

require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-diagnostics-toggle-controller.php';

/**
 * Tests for WC_REST_Stripe_Diagnostics_Toggle_Controller.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_REST_Stripe_Diagnostics_Toggle_Controller_Test extends WP_UnitTestCase {

	/**
	 * @var WC_REST_Stripe_Diagnostics_Toggle_Controller
	 */
	private $controller;

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Build a fresh controller, an administrator account, and reset the
	 * toggle option + trace store before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->controller    = new WC_REST_Stripe_Diagnostics_Toggle_Controller();
		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		delete_option( 'wc_stripe_diagnostics_enabled' );
		$this->clear_store();
	}

	/**
	 * Reset state and the current user so the next test starts clean.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'wc_stripe_diagnostics_enabled' );
		$this->clear_store();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Drop every trace from the underlying store.
	 *
	 * @return void
	 */
	private function clear_store() {
		( new WC_Stripe_Diagnostics_Trace_Store() )->delete_all();
	}

	/**
	 * `manage_woocommerce` capability passes the admin check.
	 *
	 * @return void
	 */
	public function test_admin_check_allows_woocommerce_admin() {
		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->controller->admin_check() );
	}

	/**
	 * A shopper-level user (subscriber) is denied by the admin check.
	 *
	 * @return void
	 */
	public function test_admin_check_denies_shopper() {
		$shopper = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $shopper );
		$this->assertFalse( $this->controller->admin_check() );
	}

	/**
	 * `GET /state` returns `{ enabled: false, count: 0, limit: CAPTURE_LIMIT }`
	 * on a fresh install.
	 *
	 * @return void
	 */
	public function test_get_state_reports_defaults() {
		$response = $this->controller->get_state();
		$data     = $response->get_data();
		$this->assertFalse( $data['enabled'] );
		$this->assertSame( 0, $data['count'] );
		$this->assertSame( WC_Stripe_Diagnostics_Recorder::CAPTURE_LIMIT, $data['limit'] );
	}

	/**
	 * `POST /toggle` flips the option both on and off, and the recorder's
	 * `is_enabled()` reflects each change.
	 *
	 * @return void
	 */
	public function test_toggle_flips_the_option() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'enabled', true );
		$response = $this->controller->toggle( $request );

		$this->assertTrue( $response->get_data()['enabled'] );
		$this->assertTrue( WC_Stripe_Diagnostics_Recorder::is_enabled() );

		$request->set_param( 'enabled', false );
		$response = $this->controller->toggle( $request );
		$this->assertFalse( $response->get_data()['enabled'] );
		$this->assertFalse( WC_Stripe_Diagnostics_Recorder::is_enabled() );
	}
}
