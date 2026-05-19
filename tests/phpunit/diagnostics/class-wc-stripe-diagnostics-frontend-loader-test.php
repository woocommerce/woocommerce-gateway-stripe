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
			WC()->session->get( WC_Stripe_Diagnostics_Frontend_Loader::SESSION_KEY )
		);
	}

	/**
	 * Helper to invoke the private build_inline_config() method and return
	 * the decoded config payload so individual assertions stay terse.
	 *
	 * @return array<string, mixed>
	 */
	private function build_decoded_config(): array {
		$method = new ReflectionMethod(
			WC_Stripe_Diagnostics_Frontend_Loader::class,
			'build_inline_config'
		);
		$method->setAccessible( true );
		$inline = $method->invoke( $this->loader );
		$this->assertNotNull( $inline, 'build_inline_config returned null.' );
		$json = preg_replace( '/^window\.wcStripeDiag = (.+);$/', '$1', $inline );
		return json_decode( $json, true );
	}

	/**
	 * The frontend recorder coalesces flushes inside the server-side rate
	 * window via `wcStripeDiag.rateLimitMs`. Confirm the loader passes the
	 * default (2s, the controller's `DEFAULT_EVENTS_RATE_LIMIT_SEC` × 1000)
	 * straight through so the JS-side throttle matches the PHP-side gate.
	 */
	public function test_inline_config_includes_default_rate_limit_in_ms() {
		$this->simulate_active_wc_session();

		$config = $this->build_decoded_config();

		$this->assertArrayHasKey( 'rateLimitMs', $config );
		$this->assertSame(
			WC_REST_Stripe_Diagnostics_Controller::DEFAULT_EVENTS_RATE_LIMIT_SEC * 1000,
			$config['rateLimitMs']
		);
	}

	/**
	 * Operator overrides via the same filter the controller honours should
	 * flow through to the recorder so client- and server-side windows stay
	 * synchronized.
	 */
	public function test_inline_config_rate_limit_honours_server_filter() {
		$this->simulate_active_wc_session();
		add_filter( 'wc_stripe_diagnostics_events_rate_limit', fn() => 5 );
		try {
			$config = $this->build_decoded_config();
			$this->assertSame( 5000, $config['rateLimitMs'] );
		} finally {
			remove_all_filters( 'wc_stripe_diagnostics_events_rate_limit' );
		}
	}

	/**
	 * A filter that disables the server-side gate (returns 0) should also
	 * disable the client-side throttle; the recorder treats `0` as "no
	 * window" and flushes on every call.
	 */
	public function test_inline_config_rate_limit_is_zero_when_filter_disables_gate() {
		$this->simulate_active_wc_session();
		add_filter( 'wc_stripe_diagnostics_events_rate_limit', '__return_zero' );
		try {
			$config = $this->build_decoded_config();
			$this->assertSame( 0, $config['rateLimitMs'] );
		} finally {
			remove_all_filters( 'wc_stripe_diagnostics_events_rate_limit' );
		}
	}

	public function test_session_id_regenerates_when_stored_value_is_invalid() {
		$this->simulate_active_wc_session();
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
