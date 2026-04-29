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
		WC()->session->set( WC_Stripe_Diagnostics_Frontend_Loader::SESSION_KEY, null );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function set_diagnostics_enabled( bool $enabled ): void {
		update_option(
			WC_REST_Stripe_Diagnostics_Controller::ENABLED_OPTION,
			$enabled ? 'yes' : 'no'
		);
	}

	public function test_should_not_localize_when_toggle_off() {
		$this->set_diagnostics_enabled( false );
		WC()->session->set_customer_session_cookie( true );
		$this->assertFalse( $this->loader->should_localize() );
	}

	public function test_should_not_localize_when_no_session_and_logged_out() {
		// No customer session cookie set, and no logged-in user. This is the
		// product-page-first-visit case where writing a session would set a
		// cookie and break full-page caching.
		$this->assertFalse( $this->loader->should_localize() );
	}

	public function test_should_localize_when_session_exists() {
		WC()->session->set_customer_session_cookie( true );
		$this->assertTrue( $this->loader->should_localize() );
	}

	public function test_should_localize_when_user_logged_in_even_without_session_cookie() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->loader->should_localize() );
	}

	public function test_session_id_is_generated_and_stable_across_calls() {
		WC()->session->set_customer_session_cookie( true );

		$first  = $this->loader->get_or_create_session_id();
		$second = $this->loader->get_or_create_session_id();

		$this->assertSame( $first, $second );
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$first
		);
	}

	public function test_session_id_persists_in_wc_session() {
		WC()->session->set_customer_session_cookie( true );
		$id = $this->loader->get_or_create_session_id();
		$this->assertSame(
			$id,
			WC()->session->get( WC_Stripe_Diagnostics_Frontend_Loader::SESSION_KEY )
		);
	}

	public function test_session_id_regenerates_when_stored_value_is_invalid() {
		WC()->session->set_customer_session_cookie( true );
		WC()->session->set(
			WC_Stripe_Diagnostics_Frontend_Loader::SESSION_KEY,
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
