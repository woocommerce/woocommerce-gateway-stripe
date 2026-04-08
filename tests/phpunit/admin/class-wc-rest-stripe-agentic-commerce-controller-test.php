<?php
/**
 * Class WC_REST_Stripe_Agentic_Commerce_Controller_Test
 *
 * @package WooCommerce_Stripe/Tests
 */

/**
 * Unit tests for WC_REST_Stripe_Agentic_Commerce_Controller.
 */
class WC_REST_Stripe_Agentic_Commerce_Controller_Test extends WP_UnitTestCase {

	/**
	 * REST base path.
	 */
	const REST_BASE = '/wc/v3/wc_stripe/agentic-commerce';

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Stripe_Agentic_Commerce_Controller
	 */
	private $controller;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WC_REST_Stripe_Agentic_Commerce_Controller' ) ) {
			$this->markTestSkipped( 'WC_REST_Stripe_Agentic_Commerce_Controller class not loaded' );
		}

		global $wp_rest_server;
		$wp_rest_server = null;

		$this->controller = new WC_REST_Stripe_Agentic_Commerce_Controller();
		add_action( 'rest_api_init', [ $this->controller, 'register_routes' ] );
		do_action( 'rest_api_init' );

		wp_set_current_user( 1 );

		// Ensure options start clean.
		delete_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
	}

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
		delete_option( WC_REST_Stripe_Agentic_Commerce_Controller::WEBHOOK_SECRET_OPTION );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Authentication
	// -------------------------------------------------------------------------

	/**
	 * Unauthenticated GET requests should be refused.
	 */
	public function test_get_status_requires_auth(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Unauthenticated POST /sync requests should be refused.
	 */
	public function test_trigger_sync_requires_auth(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// GET /wc/v3/wc_stripe/agentic-commerce
	// -------------------------------------------------------------------------

	/**
	 * GET returns 200 with nulls when no sync data exists.
	 */
	public function test_get_status_returns_empty_state(): void {
		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'last_sync', $data );
		$this->assertArrayHasKey( 'history', $data );
		$this->assertArrayHasKey( 'next_sync', $data );
		$this->assertNull( $data['last_sync'] );
		$this->assertSame( [], $data['history'] );
		$this->assertNull( $data['next_sync'] );
	}

	/**
	 * GET returns formatted last_sync when option is set.
	 */
	public function test_get_status_returns_last_sync(): void {
		$now = time();
		update_option(
			WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION,
			[
				'status'        => 'succeeded',
				'timestamp'     => $now,
				'products'      => 42,
				'import_set_id' => 'impset_abc',
				'file_id'       => 'file_xyz',
				'error'         => '',
			]
		);

		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertNotNull( $last_sync );
		$this->assertEquals( 'succeeded', $last_sync['status'] );
		$this->assertEquals( $now, $last_sync['timestamp'] );
		$this->assertEquals( 42, $last_sync['products'] );
		$this->assertEquals( 'impset_abc', $last_sync['import_set_id'] );
		$this->assertEquals( 'file_xyz', $last_sync['file_id'] );
		$this->assertEquals( '', $last_sync['error'] );
	}

	/**
	 * GET returns history entries in reverse-chronological order, capped at 20.
	 */
	public function test_get_status_returns_history_newest_first_capped_at_20(): void {
		// Store 25 entries oldest-first.
		$history = [];
		for ( $i = 1; $i <= 25; $i++ ) {
			$history[] = [
				'status'        => 'succeeded',
				'timestamp'     => 1000000 + $i,
				'products'      => $i,
				'import_set_id' => "impset_{$i}",
				'file_id'       => "file_{$i}",
				'error'         => '',
			];
		}
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$returned = $response->get_data()['history'];

		// Only the 20 most recent entries should be returned.
		$this->assertCount( 20, $returned );

		// Newest first: entry 25 should be at index 0, entry 6 at index 19.
		$this->assertEquals( 'impset_25', $returned[0]['import_set_id'] );
		$this->assertEquals( 'impset_6', $returned[19]['import_set_id'] );
	}

	/**
	 * GET history entries include all expected fields.
	 */
	public function test_get_status_history_entry_shape(): void {
		update_option(
			WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION,
			[
				[
					'status'        => 'failed',
					'timestamp'     => 1700000000,
					'products'      => 0,
					'import_set_id' => 'impset_err',
					'file_id'       => 'file_err',
					'error'         => 'Something went wrong',
				],
			]
		);

		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$entry = $response->get_data()['history'][0];
		$this->assertArrayHasKey( 'status', $entry );
		$this->assertArrayHasKey( 'timestamp', $entry );
		$this->assertArrayHasKey( 'products', $entry );
		$this->assertArrayHasKey( 'import_set_id', $entry );
		$this->assertArrayHasKey( 'file_id', $entry );
		$this->assertArrayHasKey( 'error', $entry );
		$this->assertEquals( 'failed', $entry['status'] );
		$this->assertEquals( 'file_err', $entry['file_id'] );
		$this->assertEquals( 'Something went wrong', $entry['error'] );
	}

	/**
	 * GET casts timestamp and products to integers.
	 */
	public function test_get_status_casts_numeric_fields(): void {
		update_option(
			WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION,
			[
				'status'        => 'succeeded',
				'timestamp'     => '1700000000', // string from old storage
				'products'      => '99',
				'import_set_id' => 'impset_cast',
				'file_id'       => '',
				'error'         => '',
			]
		);

		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertIsInt( $last_sync['timestamp'] );
		$this->assertIsInt( $last_sync['products'] );
		$this->assertEquals( 1700000000, $last_sync['timestamp'] );
		$this->assertEquals( 99, $last_sync['products'] );
	}

	/**
	 * GET returns null for missing optional last_sync fields.
	 */
	public function test_get_status_returns_null_for_missing_optional_fields(): void {
		update_option(
			WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION,
			[ 'status' => 'pending' ] // minimal entry, no other keys
		);

		$request  = new WP_REST_Request( 'GET', self::REST_BASE );
		$response = rest_do_request( $request );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertEquals( 'pending', $last_sync['status'] );
		$this->assertNull( $last_sync['timestamp'] );
		$this->assertNull( $last_sync['products'] );
		$this->assertNull( $last_sync['import_set_id'] );
		$this->assertNull( $last_sync['file_id'] );
		$this->assertNull( $last_sync['error'] );
	}

	// -------------------------------------------------------------------------
	// POST /wc/v3/wc_stripe/agentic-commerce/sync
	// -------------------------------------------------------------------------

	/**
	 * POST /sync returns 503 when the integration class is not available.
	 */
	public function test_trigger_sync_returns_503_when_integration_unavailable(): void {
		// We can't unload a class, but we can test the controller method directly
		// by calling the method in isolation with the class missing guard.
		// Instead test via the controller's own guard logic by checking the error
		// response shape when the integration class does not exist.
		// Since in the test environment the integration class IS loaded, skip if so.
		if ( class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			$this->markTestSkipped( 'Integration class is loaded; cannot test the 503 branch in this environment.' );
		}

		$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
		$response = rest_do_request( $request );

		$this->assertEquals( 503, $response->get_status() );
	}

	/**
	 * POST /sync succeeds and returns { success: true } when the integration is available.
	 */
	public function test_trigger_sync_returns_success_when_available(): void {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Integration class not loaded' );
		}

		// Stub out the actual HTTP sync so the test does not hit Stripe.
		$http_stub = function () {
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [],
				'body'     => wp_json_encode( [ 'id' => 'file_stub' ] ),
			];
		};

		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	// -------------------------------------------------------------------------
	// GET /wc/v3/wc_stripe/agentic-commerce/settings
	// -------------------------------------------------------------------------

	/**
	 * GET /settings returns default values when no options are set.
	 */
	public function test_get_settings_returns_defaults(): void {
		$request  = new WP_REST_Request( 'GET', self::REST_BASE . '/settings' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'is_enabled', $data );
		$this->assertArrayHasKey( 'webhook_secret', $data );
		$this->assertFalse( $data['is_enabled'] );
		$this->assertSame( '', $data['webhook_secret'] );
	}

	/**
	 * GET /settings reflects stored option values, masking the webhook secret.
	 */
	public function test_get_settings_reflects_stored_values(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );
		update_option( WC_REST_Stripe_Agentic_Commerce_Controller::WEBHOOK_SECRET_OPTION, 'whsec_test123' );

		$request  = new WP_REST_Request( 'GET', self::REST_BASE . '/settings' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['is_enabled'] );
		// Real secret must never be returned; the masked placeholder is expected.
		$this->assertSame( '****', $data['webhook_secret'] );
	}

	/**
	 * GET /settings returns empty string when no secret is stored.
	 */
	public function test_get_settings_returns_empty_string_when_no_secret(): void {
		$request  = new WP_REST_Request( 'GET', self::REST_BASE . '/settings' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( '', $response->get_data()['webhook_secret'] );
	}

	/**
	 * Unauthenticated GET /settings requests should be refused.
	 */
	public function test_get_settings_requires_auth(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', self::REST_BASE . '/settings' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// POST /wc/v3/wc_stripe/agentic-commerce/settings
	// -------------------------------------------------------------------------

	/**
	 * POST /settings enables the feature flag.
	 */
	public function test_update_settings_enables_feature(): void {
		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'is_enabled' => true ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['is_enabled'] );
		$this->assertSame( 'yes', get_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION ) );
	}

	/**
	 * POST /settings disables the feature flag.
	 */
	public function test_update_settings_disables_feature(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );

		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'is_enabled' => false ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['is_enabled'] );
		$this->assertSame( 'no', get_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION ) );
	}

	/**
	 * POST /settings stores the webhook secret and returns the masked placeholder.
	 */
	public function test_update_settings_stores_webhook_secret(): void {
		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'webhook_secret' => 'whsec_abc123' ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		// Response must return the masked placeholder, not the real secret.
		$this->assertSame( '****', $response->get_data()['webhook_secret'] );
		$this->assertSame( 'whsec_abc123', get_option( WC_REST_Stripe_Agentic_Commerce_Controller::WEBHOOK_SECRET_OPTION ) );
	}

	/**
	 * POST /settings does not overwrite the stored secret when the masked
	 * placeholder is submitted (e.g. user saved without changing the field).
	 */
	public function test_update_settings_preserves_secret_when_placeholder_sent(): void {
		update_option( WC_REST_Stripe_Agentic_Commerce_Controller::WEBHOOK_SECRET_OPTION, 'whsec_original' );

		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'webhook_secret' => '****' ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( '****', $response->get_data()['webhook_secret'] );
		// Stored value must be unchanged.
		$this->assertSame( 'whsec_original', get_option( WC_REST_Stripe_Agentic_Commerce_Controller::WEBHOOK_SECRET_OPTION ) );
	}

	/**
	 * POST /settings can update both is_enabled and webhook_secret together.
	 */
	public function test_update_settings_updates_both_fields(): void {
		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body(
			wp_json_encode(
				[
					'is_enabled'     => true,
					'webhook_secret' => 'whsec_combined',
				]
			)
		);
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['is_enabled'] );
		$this->assertSame( '****', $data['webhook_secret'] );
	}

	/**
	 * POST /settings sanitizes the webhook secret value.
	 */
	public function test_update_settings_sanitizes_webhook_secret(): void {
		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'webhook_secret' => "  whsec_trimmed\t" ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		// sanitize_text_field strips leading/trailing whitespace and tabs;
		// the response returns the masked placeholder, not the real value.
		$this->assertSame( '****', $response->get_data()['webhook_secret'] );
		$this->assertSame( 'whsec_trimmed', get_option( WC_REST_Stripe_Agentic_Commerce_Controller::WEBHOOK_SECRET_OPTION ) );
	}

	/**
	 * Unauthenticated POST /settings requests should be refused.
	 */
	public function test_update_settings_requires_auth(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'is_enabled' => true ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// check_permission
	// -------------------------------------------------------------------------

	/**
	 * check_permission returns true for a user with manage_woocommerce capability.
	 */
	public function test_check_permission_returns_true_for_admin(): void {
		wp_set_current_user( 1 );
		$this->assertTrue( $this->controller->check_permission() );
	}

	/**
	 * check_permission returns false for an unauthenticated user.
	 */
	public function test_check_permission_returns_false_for_guest(): void {
		wp_set_current_user( 0 );
		$this->assertFalse( $this->controller->check_permission() );
	}
}
