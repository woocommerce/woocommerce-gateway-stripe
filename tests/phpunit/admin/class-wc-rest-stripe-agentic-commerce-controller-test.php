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
	const REST_BASE    = '/wc/v3/wc_stripe/agentic-commerce';
	const STATUS_ROUTE = self::REST_BASE . '/status';

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Stripe_Agentic_Commerce_Controller
	 */
	private $controller;

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private $server;

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

		// Enable the Agentic Commerce feature flag so the controller registers
		// its routes (register_routes() is gated on the flag).
		update_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME, 'yes' );

		// Enable the merchant-facing toggle so trigger_sync() does not bail on
		// the disabled-feature gate. Tests that exercise the disabled path
		// override this explicitly.
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );

		$this->controller = new WC_REST_Stripe_Agentic_Commerce_Controller();
		add_action( 'rest_api_init', [ $this->controller, 'register_routes' ] );

		global $wp_rest_server;
		$wp_rest_server = null;
		$this->server   = rest_get_server();

		// Under paratest, each worker runs against an isolated WP install whose
		// administrator role may not have been granted `manage_woocommerce`
		// (WC's role setup only runs during activation, not on plugin load).
		// Grant it explicitly so the REST permission check in this controller
		// resolves the same way it does in production.
		$administrator = get_role( 'administrator' );
		if ( $administrator && ! $administrator->has_cap( 'manage_woocommerce' ) ) {
			$administrator->add_cap( 'manage_woocommerce' );
		}

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
		remove_action( 'rest_api_init', [ $this->controller, 'register_routes' ] );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION );
		// Controller's SYNC_LOCK_OPTION is private; keep the literal in sync by hand if it is renamed.
		delete_option( 'wc_stripe_agentic_sync_lock' );
		delete_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME );
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

		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
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
	// GET /wc/v3/wc_stripe/agentic-commerce/status
	// -------------------------------------------------------------------------

	/**
	 * GET returns 200 with nulls when no sync data exists.
	 */
	public function test_get_status_returns_empty_state(): void {
		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
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

		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
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

		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
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
					'status'        => 'succeeded',
					'timestamp'     => 1699900000,
					'products'      => 10,
					'import_set_id' => 'impset_ok',
					'file_id'       => 'file_ok',
					'error'         => '',
				],
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

		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
		$response = rest_do_request( $request );

		$history = $response->get_data()['history'];
		$this->assertCount( 2, $history );

		// Newest first: the failed entry should be first.
		$entry = $history[0];
		$this->assertArrayHasKey( 'status', $entry );
		$this->assertArrayHasKey( 'timestamp', $entry );
		$this->assertArrayHasKey( 'products', $entry );
		$this->assertArrayHasKey( 'import_set_id', $entry );
		$this->assertArrayHasKey( 'file_id', $entry );
		$this->assertArrayHasKey( 'error', $entry );
		$this->assertEquals( 'failed', $entry['status'] );
		$this->assertEquals( 'file_err', $entry['file_id'] );
		$this->assertEquals( 'Something went wrong', $entry['error'] );

		// Second entry should be the succeeded one.
		$second = $history[1];
		$this->assertEquals( 'succeeded', $second['status'] );
		$this->assertEquals( 10, $second['products'] );
		$this->assertEquals( 'impset_ok', $second['import_set_id'] );
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

		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
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

		$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
		$response = rest_do_request( $request );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertArrayHasKey( 'status', $last_sync );
		$this->assertArrayHasKey( 'timestamp', $last_sync );
		$this->assertArrayHasKey( 'products', $last_sync );
		$this->assertArrayHasKey( 'import_set_id', $last_sync );
		$this->assertArrayHasKey( 'file_id', $last_sync );
		$this->assertArrayHasKey( 'error', $last_sync );
		$this->assertEquals( 'pending', $last_sync['status'] );
		$this->assertNull( $last_sync['timestamp'] );
		$this->assertNull( $last_sync['products'] );
		$this->assertNull( $last_sync['import_set_id'] );
		$this->assertNull( $last_sync['file_id'] );
		$this->assertNull( $last_sync['error'] );
	}

	// -------------------------------------------------------------------------
	// Lazy status refresh from Stripe
	// -------------------------------------------------------------------------

	/**
	 * GET /status refreshes pending entries by polling Stripe for their current status.
	 */
	public function test_get_status_refreshes_pending_entries_from_stripe(): void {
		$history = [
			[
				'status'        => 'pending',
				'timestamp'     => 1700000000,
				'products'      => 5,
				'import_set_id' => 'impset_pending1',
				'file_id'       => 'file_1',
				'error'         => '',
			],
			[
				'status'        => 'succeeded',
				'timestamp'     => 1700000100,
				'products'      => 10,
				'import_set_id' => 'impset_done',
				'file_id'       => 'file_2',
				'error'         => '',
			],
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, end( $history ) );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_pending1' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_pending1',
							'status' => 'succeeded',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		$returned_history = $response->get_data()['history'];
		$pending_entry    = null;
		foreach ( $returned_history as $entry ) {
			if ( 'impset_pending1' === $entry['import_set_id'] ) {
				$pending_entry = $entry;
				break;
			}
		}
		$this->assertNotNull( $pending_entry );
		$this->assertEquals( 'succeeded', $pending_entry['status'] );

		$stored_history = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
		$this->assertEquals( 'succeeded', $stored_history[0]['status'] );
	}

	/**
	 * GET /status does not call Stripe for entries that are already in a terminal state.
	 */
	public function test_get_status_skips_refresh_for_non_pending_entries(): void {
		$history = [
			[
				'status'        => 'succeeded',
				'timestamp'     => 1700000000,
				'products'      => 10,
				'import_set_id' => 'impset_ok',
				'file_id'       => 'file_1',
				'error'         => '',
			],
			[
				'status'        => 'failed',
				'timestamp'     => 1700000100,
				'products'      => 0,
				'import_set_id' => 'impset_fail',
				'file_id'       => 'file_2',
				'error'         => 'oops',
			],
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, end( $history ) );

		$api_called = false;
		$http_stub  = function ( $preempt ) use ( &$api_called ) {
			$api_called = true;
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( $api_called, 'Stripe API should not be called when no entries are pending.' );
	}

	/**
	 * GET /status updates last_sync when the most recent entry transitions from pending.
	 */
	public function test_get_status_updates_last_sync_when_latest_entry_refreshed(): void {
		$entry = [
			'status'        => 'pending',
			'timestamp'     => 1700000000,
			'products'      => 3,
			'import_set_id' => 'impset_latest',
			'file_id'       => 'file_latest',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $entry ] );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $entry );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_latest' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_latest',
							'status' => 'succeeded_with_errors',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertEquals( 'succeeded_with_errors', $last_sync['status'] );

		$stored_last = get_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION );
		$this->assertEquals( 'succeeded_with_errors', $stored_last['status'] );
	}

	/**
	 * GET /status also refreshes entries that have advanced to `creating_records`.
	 *
	 * `creating_records` is an intermediate Stripe ImportSet state (between
	 * `pending` and the terminal states), so entries sitting there must keep
	 * getting polled on dashboard load — otherwise the badge would stay
	 * "Creating records" indefinitely after the first refresh.
	 */
	public function test_get_status_refreshes_creating_records_entries(): void {
		$entry = [
			'status'        => 'creating_records',
			'timestamp'     => 1700000000,
			'products'      => 4,
			'import_set_id' => 'impset_creating',
			'file_id'       => 'file_creating',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $entry ] );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $entry );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_creating' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_creating',
							'status' => 'succeeded',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertEquals( 'succeeded', $last_sync['status'] );

		$stored_history = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
		$this->assertEquals( 'succeeded', $stored_history[0]['status'] );
	}

	/**
	 * GET /status persists the pending → creating_records transition so the
	 * next refresh continues polling from the latest state.
	 */
	public function test_get_status_persists_pending_to_creating_records_transition(): void {
		$entry = [
			'status'        => 'pending',
			'timestamp'     => 1700000000,
			'products'      => 2,
			'import_set_id' => 'impset_inflight',
			'file_id'       => 'file_inflight',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $entry ] );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $entry );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_inflight' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_inflight',
							'status' => 'creating_records',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertEquals( 'creating_records', $last_sync['status'] );

		$stored_history = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
		$this->assertEquals( 'creating_records', $stored_history[0]['status'] );
	}

	/**
	 * GET /status preserves entries appended concurrently during refresh.
	 *
	 * Simulates the race where `store_sync_result()` appends a fresh entry
	 * while the refresh flow is blocked on a Stripe API round-trip: the new
	 * entry must survive the refresh's writeback.
	 */
	public function test_get_status_preserves_concurrent_append_during_refresh(): void {
		$pending = [
			'status'        => 'pending',
			'timestamp'     => 1700000000,
			'products'      => 3,
			'import_set_id' => 'impset_pending_refresh',
			'file_id'       => 'file_pending',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $pending ] );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $pending );

		// While the refresh flow is waiting on Stripe, simulate a concurrent
		// scheduled sync appending a fresh entry via the integration's public
		// store API.
		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_pending_refresh' ) ) {
				( new WC_Stripe_Agentic_Commerce_Integration() )->store_sync_result(
					[
						'status'        => 'succeeded',
						'products'      => 7,
						'import_set_id' => 'impset_concurrent',
						'file_id'       => 'file_concurrent',
						'error'         => '',
					]
				);

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_pending_refresh',
							'status' => 'succeeded',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		$stored_history = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION );
		$this->assertCount( 2, $stored_history, 'Concurrent append must not be clobbered.' );

		$ids = array_column( $stored_history, 'import_set_id' );
		$this->assertContains( 'impset_pending_refresh', $ids );
		$this->assertContains( 'impset_concurrent', $ids );

		$by_id = array_column( $stored_history, null, 'import_set_id' );
		$this->assertEquals( 'succeeded', $by_id['impset_pending_refresh']['status'] );
		$this->assertEquals( 'succeeded', $by_id['impset_concurrent']['status'] );

		// last_sync should track the newest (concurrently appended) entry.
		$stored_last = get_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION );
		$this->assertEquals( 'impset_concurrent', $stored_last['import_set_id'] );
	}

	/**
	 * GET /status handles Stripe API errors gracefully during refresh.
	 */
	public function test_get_status_handles_stripe_error_during_refresh(): void {
		$entry = [
			'status'        => 'pending',
			'timestamp'     => 1700000000,
			'products'      => 3,
			'import_set_id' => 'impset_errcheck',
			'file_id'       => 'file_err',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $entry ] );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $entry );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_errcheck' ) ) {
				return new WP_Error( 'http_request_failed', 'Connection timeout' );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		$last_sync = $response->get_data()['last_sync'];
		$this->assertEquals( 'pending', $last_sync['status'] );
	}

	/**
	 * GET /status refreshes entries with non-terminal statuses (queued, validating,
	 * pending, creating_records, unknown).
	 *
	 * @dataProvider provide_non_terminal_statuses
	 */
	public function test_get_status_refreshes_non_terminal_status_entries( string $initial_status ): void {
		$entry = [
			'status'        => $initial_status,
			'timestamp'     => 1700000000,
			'products'      => 5,
			'import_set_id' => 'impset_nonterminal',
			'file_id'       => 'file_nt',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $entry ] );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $entry );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_nonterminal' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_nonterminal',
							'status' => 'succeeded',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'succeeded', $response->get_data()['last_sync']['status'] );
	}

	/**
	 * Data provider: non-terminal ImportSet statuses that should trigger a refresh.
	 *
	 * @return array[]
	 */
	public function provide_non_terminal_statuses(): array {
		return [
			'queued'           => [ 'queued' ],
			'validating'       => [ 'validating' ],
			'pending'          => [ 'pending' ],
			'creating_records' => [ 'creating_records' ],
			'unknown'          => [ 'unknown' ],
		];
	}

	/**
	 * GET /status skips refresh for all terminal statuses.
	 *
	 * @dataProvider provide_terminal_statuses
	 */
	public function test_get_status_skips_refresh_for_terminal_statuses( string $status ): void {
		$entry = [
			'status'        => $status,
			'timestamp'     => 1700000000,
			'products'      => 10,
			'import_set_id' => 'impset_term',
			'file_id'       => 'file_t',
			'error'         => '',
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [ $entry ] );

		$api_called = false;
		$http_stub  = function ( $preempt ) use ( &$api_called ) {
			$api_called = true;
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( $api_called, "Stripe API should not be called for terminal status '$status'." );
	}

	/**
	 * Data provider: terminal ImportSet statuses that should not trigger a refresh.
	 *
	 * @return array[]
	 */
	public function provide_terminal_statuses(): array {
		return [
			'succeeded'             => [ 'succeeded' ],
			'failed'                => [ 'failed' ],
			'succeeded_with_errors' => [ 'succeeded_with_errors' ],
		];
	}

	// -------------------------------------------------------------------------
	// POST /wc/v3/wc_stripe/agentic-commerce/sync
	// -------------------------------------------------------------------------

	/**
	 * POST /sync succeeds and returns { success: true } when the integration is available.
	 */
	public function test_trigger_sync_returns_success_when_available(): void {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Integration class not loaded' );
		}

		// Enable the Agentic Commerce feature flag so sync_feed() does not bail early.
		update_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME, 'yes' );

		// Set a test secret key so check_setup() passes.
		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']        = 'yes';
		$settings['test_secret_key'] = 'sk_test_fake';
		update_option( 'woocommerce_stripe_settings', $settings );

		// Create a simple product so the walker finds at least one.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( '10.00' );
		$product->set_status( 'publish' );
		$product->save();

		// Stub the Files API cURL upload (which bypasses pre_http_request).
		$files_stub = function () {
			return [ 'id' => 'file_stub' ];
		};
		add_filter( 'wc_stripe_agentic_commerce_files_api_pre_request', $files_stub, 10, 2 );

		// Stub the ImportSet creation (uses wp_remote_post).
		$http_stub = function () {
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'id'     => 'impset_stub',
						'status' => 'pending',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'wc_stripe_agentic_commerce_files_api_pre_request', $files_stub, 10 );
			remove_filter( 'pre_http_request', $http_stub, 10 );
			delete_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME );
			$product->delete( true );
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * POST /sync returns 500 when sync_feed() returns false.
	 *
	 * Leaves the feature flag enabled (so the routes remain registered) and
	 * forces a downstream failure by clearing the Stripe API key, which makes
	 * `check_setup()` — and therefore `sync_feed()` — return false.
	 */
	public function test_trigger_sync_returns_500_when_sync_fails(): void {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Integration class not loaded' );
		}

		$original_settings           = WC_Stripe_Helper::get_stripe_settings();
		$settings                    = $original_settings;
		$settings['testmode']        = 'yes';
		$settings['test_secret_key'] = '';
		$settings['secret_key']      = '';
		update_option( 'woocommerce_stripe_settings', $settings );

		try {
			$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
			$response = rest_do_request( $request );
		} finally {
			update_option( 'woocommerce_stripe_settings', $original_settings );
		}

		$this->assertEquals( 500, $response->get_status() );
	}

	/**
	 * POST /sync returns 409 when a sync lock is active.
	 */
	public function test_trigger_sync_returns_409_when_locked(): void {
		// Seed the lock option with a fresh timestamp so acquire_sync_lock() treats it as active.
		// Controller's SYNC_LOCK_OPTION is private; keep the literal in sync by hand if it is renamed.
		update_option(
			'wc_stripe_agentic_sync_lock',
			time(),
			false
		);

		$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
		$response = rest_do_request( $request );

		$this->assertEquals( 409, $response->get_status() );
		$this->assertStringContainsString( 'already in progress', $response->get_data()['message'] );
	}

	/**
	 * POST /sync returns 409 when the merchant has disabled the feature, even
	 * if the developer feature flag and Stripe credentials are otherwise valid.
	 *
	 * Regression: a stale admin tab or a direct curl POST must not push the
	 * catalog to Stripe after the merchant flips the toggle off.
	 */
	public function test_trigger_sync_returns_409_when_merchant_toggle_off(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );

		$api_called = false;
		$http_stub  = function ( $preempt ) use ( &$api_called ) {
			$api_called = true;
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 409, $response->get_status() );
		$this->assertSame( 'stripe_agentic_commerce_disabled', $response->get_data()['code'] );
		$this->assertFalse( $api_called, 'Stripe API must not be called when the merchant toggle is off.' );
	}

	// -------------------------------------------------------------------------
	// GET /wc/v3/wc_stripe/agentic-commerce/settings
	// -------------------------------------------------------------------------

	/**
	 * GET /settings returns default values when no options are set.
	 */
	public function test_get_settings_returns_defaults(): void {
		// set_up() enables the merchant toggle so trigger_sync tests pass; this
		// test specifically asserts the off-by-default response.
		delete_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );

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
		update_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION, 'whsec_test123' );

		$request  = new WP_REST_Request( 'GET', self::REST_BASE . '/settings' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['is_enabled'] );
		// Real secret must never be returned; the masked placeholder is expected.
		$this->assertSame( WC_REST_Stripe_Agentic_Commerce_Controller::MASKED_WEBHOOK_SECRET, $data['webhook_secret'] );
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
		$this->assertSame( WC_REST_Stripe_Agentic_Commerce_Controller::MASKED_WEBHOOK_SECRET, $response->get_data()['webhook_secret'] );
		$this->assertSame( 'whsec_abc123', get_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION ) );
	}

	/**
	 * POST /settings does not overwrite the stored secret when the masked
	 * placeholder is submitted (e.g. user saved without changing the field).
	 */
	public function test_update_settings_preserves_secret_when_placeholder_sent(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION, 'whsec_original' );

		$request = new WP_REST_Request( 'POST', self::REST_BASE . '/settings' );
		$request->set_body( wp_json_encode( [ 'webhook_secret' => WC_REST_Stripe_Agentic_Commerce_Controller::MASKED_WEBHOOK_SECRET ] ) );
		$request->set_header( 'content-type', 'application/json' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( WC_REST_Stripe_Agentic_Commerce_Controller::MASKED_WEBHOOK_SECRET, $response->get_data()['webhook_secret'] );
		// Stored value must be unchanged.
		$this->assertSame( 'whsec_original', get_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION ) );
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
		$this->assertSame( WC_REST_Stripe_Agentic_Commerce_Controller::MASKED_WEBHOOK_SECRET, $data['webhook_secret'] );
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
		$this->assertSame( WC_REST_Stripe_Agentic_Commerce_Controller::MASKED_WEBHOOK_SECRET, $response->get_data()['webhook_secret'] );
		$this->assertSame( 'whsec_trimmed', get_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION ) );
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

	// -------------------------------------------------------------------------
	// Authorization — subscriber role
	// -------------------------------------------------------------------------

	/**
	 * Authenticated but unauthorized users (subscriber) get 403.
	 *
	 * @dataProvider unauthorized_route_provider
	 */
	public function test_restricted_routes_return_403_for_subscriber( string $method, string $route ): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new WP_REST_Request( $method, $route ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Data provider for unauthorized route tests.
	 *
	 * @return array
	 */
	public static function unauthorized_route_provider(): array {
		return [
			'GET status' => [ 'GET', self::REST_BASE . '/status' ],
			'POST sync'  => [ 'POST', self::REST_BASE . '/sync' ],
		];
	}

	// -------------------------------------------------------------------------
	// last_sync not clobbered by older pending entry refresh
	// -------------------------------------------------------------------------

	/**
	 * Refreshing an older pending entry does not overwrite last_sync if a newer entry already succeeded.
	 */
	public function test_get_status_refresh_does_not_clobber_last_sync(): void {
		$history = [
			[
				'status'        => 'pending',
				'timestamp'     => 1700000000,
				'products'      => 5,
				'import_set_id' => 'impset_pending1',
				'file_id'       => 'file_1',
				'error'         => '',
			],
			[
				'status'        => 'succeeded',
				'timestamp'     => 1700000100,
				'products'      => 10,
				'import_set_id' => 'impset_done',
				'file_id'       => 'file_2',
				'error'         => '',
			],
		];
		update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );
		update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, end( $history ) );

		$http_stub = function ( $preempt, $args, $url ) {
			if ( str_contains( $url, 'impset_pending1' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'     => 'impset_pending1',
							'status' => 'succeeded',
						]
					),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10 );
		}

		$this->assertEquals( 200, $response->get_status() );

		// The last_sync should still point to the newer succeeded entry, not the older refreshed one.
		$last_sync = $response->get_data()['last_sync'];
		$this->assertSame( 'impset_done', $last_sync['import_set_id'] );

		$stored_last = get_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION );
		$this->assertSame( 'impset_done', $stored_last['import_set_id'] );
	}

	// -------------------------------------------------------------------------
	// next_sync / reschedule contract
	// -------------------------------------------------------------------------

	/**
	 * After a successful POST /sync, GET /status returns a non-null next_sync within the expected window.
	 */
	public function test_trigger_sync_reschedules_next_sync(): void {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Integration class not loaded' );
		}

		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available' );
		}

		// Enable the Agentic Commerce feature flag.
		update_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME, 'yes' );

		// Set a test secret key so check_setup() passes.
		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']        = 'yes';
		$settings['test_secret_key'] = 'sk_test_fake';
		update_option( 'woocommerce_stripe_settings', $settings );

		// Create a simple product so the walker finds at least one.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( '10.00' );
		$product->set_status( 'publish' );
		$product->save();

		// Stub the Files API cURL upload.
		$files_stub = function () {
			return [ 'id' => 'file_stub' ];
		};
		add_filter( 'wc_stripe_agentic_commerce_files_api_pre_request', $files_stub, 10, 2 );

		// Stub the ImportSet creation.
		$http_stub = function () {
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'id'     => 'impset_stub',
						'status' => 'pending',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$before   = time();
			$request  = new WP_REST_Request( 'POST', self::REST_BASE . '/sync' );
			$response = rest_do_request( $request );

			$this->assertEquals( 200, $response->get_status() );

			// Now check that next_sync is set.
			$status_request  = new WP_REST_Request( 'GET', self::STATUS_ROUTE );
			$status_response = rest_do_request( $status_request );
			$next_sync       = $status_response->get_data()['next_sync'];

			$this->assertNotNull( $next_sync, 'next_sync should be non-null after a successful sync' );
			$this->assertGreaterThan( $before, $next_sync );
			$this->assertLessThanOrEqual(
				$before + WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL + 5,
				$next_sync
			);
		} finally {
			remove_filter( 'wc_stripe_agentic_commerce_files_api_pre_request', $files_stub, 10 );
			remove_filter( 'pre_http_request', $http_stub, 10 );
			delete_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME );
			$product->delete( true );

			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			}
		}
	}
}
