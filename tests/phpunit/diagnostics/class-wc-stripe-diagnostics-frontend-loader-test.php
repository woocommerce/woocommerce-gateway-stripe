<?php

require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-diagnostics-controller.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/diagnostics/class-wc-stripe-diagnostics-frontend-loader.php';

/**
 * Tests for WC_Stripe_Diagnostics_Frontend_Loader.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Frontend_Loader_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Diagnostics_Frontend_Loader
	 */
	private $loader;

	public function set_up() {
		parent::set_up();
		$this->loader = new WC_Stripe_Diagnostics_Frontend_Loader();
		$this->set_diagnostics_enabled( true );
		WC()->initialize_session();
	}

	public function tear_down() {
		$this->set_diagnostics_enabled( false );
		WC()->session->set( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Frontend_Loader::class, 'SESSION_KEY', 'string' ), null );
		unset( $_COOKIE[ 'wp_woocommerce_session_' . COOKIEHASH ] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Make WC_Session_Handler::has_session() return true without going
	 * through set_customer_session_cookie(), which calls wc_setcookie()
	 * and triggers a "headers already sent" notice in the PHPUnit CLI
	 * environment. has_session() is satisfied by isset($_COOKIE[...]).
	 */
	private function simulate_active_wc_session(): void {
		$_COOKIE[ 'wp_woocommerce_session_' . COOKIEHASH ] = 'test-session';
	}

	private function set_diagnostics_enabled( bool $enabled ): void {
		$settings = get_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$settings[ WC_REST_Stripe_Diagnostics_Controller::SETTINGS_KEY ] = $enabled ? 'yes' : 'no';
		update_option( WC_REST_Stripe_Diagnostics_Controller::SETTINGS_OPTION, $settings );
	}

	public function test_should_not_localize_when_toggle_off() {
		$this->set_diagnostics_enabled( false );
		$this->simulate_active_wc_session();
		$this->assertFalse( $this->loader->should_localize() );
	}

	public function test_should_not_localize_when_no_session_and_logged_out() {
		// No customer session cookie set, and no logged-in user. This is the
		// product-page-first-visit case where writing a session would set a
		// cookie and break full-page caching.
		$this->assertFalse( $this->loader->should_localize() );
	}

	public function test_should_localize_when_session_exists() {
		$this->simulate_active_wc_session();
		$this->assertTrue( $this->loader->should_localize() );
	}

	public function test_should_localize_when_user_logged_in_even_without_session_cookie() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->loader->should_localize() );
	}

	public function test_session_id_is_generated_and_stable_across_calls() {
		$this->simulate_active_wc_session();

		$first  = $this->loader->get_or_create_session_id();
		$second = $this->loader->get_or_create_session_id();

		$this->assertSame( $first, $second );
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$first
		);
	}

	public function test_session_id_persists_in_wc_session() {
		$this->simulate_active_wc_session();
		$id = $this->loader->get_or_create_session_id();
		$this->assertSame(
			$id,
			WC()->session->get( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Frontend_Loader::class, 'SESSION_KEY', 'string' ) )
		);
	}

	public function test_session_id_regenerates_when_stored_value_is_invalid() {
		$this->simulate_active_wc_session();
		WC()->session->set(
			WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Frontend_Loader::class, 'SESSION_KEY', 'string' ),
			'not-a-uuid'
		);

		$id = $this->loader->get_or_create_session_id();

		$this->assertNotSame( 'not-a-uuid', $id );
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$id
		);
	}
}
