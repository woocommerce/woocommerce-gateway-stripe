<?php

namespace WooCommerce\Stripe\Tests;

use ReflectionClass;
use WC_Stripe_API;
use WC_Stripe_Database_Cache;
use WC_Stripe_Helper;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_API
 *
 * @package WooCommerce/Stripe/WC_Stripe_API
 *
 * Class WC_Stripe_API tests.
 */
class WC_Stripe_API_Test extends WP_UnitTestCase {

	/**
	 * Secret key for test mode.
	 */
	const TEST_SECRET_KEY = 'sk_test_key_123';

	/**
	 * Secret key for live mode.
	 */
	const LIVE_SECRET_KEY = 'sk_live_key_123';

	/**
	 * Setup environment for tests.
	 */
	public function set_up() {
		parent::set_up();

		$stripe_settings                    = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']         = 'yes';
		$stripe_settings['testmode']        = 'yes';
		$stripe_settings['secret_key']      = self::LIVE_SECRET_KEY;
		$stripe_settings['test_secret_key'] = self::TEST_SECRET_KEY;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Reset the invalid API keys count cache.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Tear down environment after tests.
	 */
	public function tear_down() {
		// Reset the invalid API keys count cache.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		WC_Stripe_Helper::delete_main_stripe_settings();
		WC_Stripe_API::set_secret_key( null );

		parent::tear_down();
	}

	/**
	 * Test get_secret_key and set_secret_key.
	 */
	public function test_set_secret_key() {
		$secret_key = 'sk_test_key';
		WC_Stripe_API::set_secret_key( $secret_key );

		$this->assertEquals( $secret_key, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::set_secret_key_for_mode() with no parameter.
	 */
	public function test_set_secret_key_for_mode_no_parameter() {
		// Base test - current mode is test.
		WC_Stripe_API::set_secret_key_for_mode();

		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Enable live mode.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Stripe_API::set_secret_key_for_mode();

		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::set_secret_key_for_mode() with mode parameters.
	 */
	public function test_set_secret_key_for_mode_with_parameter() {
		WC_Stripe_API::set_secret_key_for_mode( 'test' );
		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		WC_Stripe_API::set_secret_key_for_mode( 'live' );
		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Invalid parameters will set the secret key to the current mode.
		WC_Stripe_API::set_secret_key_for_mode( 'invalid' );
		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Set the mode to live and test the invalid parameter.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Stripe_API::set_secret_key_for_mode( 'invalid' );
		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::retrieve() when API keys are valid.
	 */
	public function test_retrieve_makes_api_call_when_api_keys_are_valid() {
		// Mock a successful API response
		add_filter( 'pre_http_request', [ $this, 'mock_successful_response' ] );

		// Call the retrieve method
		$result = WC_Stripe_API::retrieve( 'test_endpoint' );

		// Verify the result matches our mock response
		$this->assertEquals( 'success', $result );

		// Clean up
		remove_filter( 'pre_http_request', [ $this, 'mock_successful_response' ] );
	}

	/**
	 * Test WC_Stripe_API::retrieve() returns null without API call after raeching the max threshold.
	 */
	public function test_retrieve_returns_null_without_api_call_after_threshold() {
		$call_count = 0;

		// Mock HTTP to always return 401 and increment the counter.
		add_filter(
			'pre_http_request',
			function () use ( &$call_count ) {
				$call_count++;
				return $this->mock_unauthorized_response();
			}
		);

		$stripe_api_class = new ReflectionClass( WC_Stripe_API::class );
		$threshold  = $stripe_api_class->getConstant( 'INVALID_API_KEY_ERROR_COUNT_THRESHOLD' );

		// Call retrieve up to the threshold, each should make an HTTP call.
		for ( $i = 0; $i < $threshold; $i++ ) {
			WC_Stripe_API::retrieve( 'test_endpoint' );
		}
		$this->assertEquals( $threshold, $call_count, 'Should have made HTTP calls up to the threshold.' );

		// Now, the next call should NOT make an HTTP call, but return null immediately.
		$result = WC_Stripe_API::retrieve( 'test_endpoint' );
		$this->assertNull( $result, 'Expected null after reaching invalid API key threshold.' );
		$this->assertEquals( $threshold, $call_count, 'Should not make another HTTP call after threshold is reached.' );

		remove_all_filters( 'pre_http_request' );
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Test WC_Stripe_API::retrieve() resets the invalid API key count on successful response.
	 */
	public function test_retrieve_resets_invalid_api_key_count_on_successful_response() {
		// 1. Mock a 401 response for the first call.
		add_filter( 'pre_http_request', [ $this, 'mock_unauthorized_response' ] );

		// First call: should set the cache count to 1.
		WC_Stripe_API::retrieve( 'test_endpoint' );
		$count = WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		$this->assertEquals( 1, $count, 'Cache count should be 1 after first 401.' );

		remove_all_filters( 'pre_http_request' );

		// 2. Mock a 200 response for the second call.
		add_filter( 'pre_http_request', [ $this, 'mock_successful_response' ] );

		// Second call: should delete the cache.
		WC_Stripe_API::retrieve( 'test_endpoint' );
		$count = WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		$this->assertNull( $count, 'Cache should be deleted after a successful response.' );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Helper method to mock a successful API response.
	 */
	public function mock_successful_response() {
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => json_encode( 'success' ),
		];
	}

	/**
	 * Helper method to mock an unauthorized API response.
	 */
	public function mock_unauthorized_response() {
		return [
			'response' => [
				'code'    => 401,
				'message' => 'Unauthorized',
			],
			'body'     => json_encode( [ 'error' => 'invalid_api_key' ] ),
		];
	}

	/**
	 * Test WC_Stripe_API::log_error_response() as called from WC_Stripe_API::request() and WC_Stripe_API::retrieve().
	 *
	 * @param array|WP_Error $response     The mock response.
	 * @param string         $api          The API endpoint.
	 * @param string         $method       The HTTP method used for the request.
	 * @param array|null     $request_data The mock request data. Only used for POST requests.
	 * @dataProvider provide_test_log_error_response_tests
	 */
	public function test_log_error_response( $response, string $api, string $method, ?array $request_data = null ) {
		$expected_url = WC_Stripe_API::ENDPOINT . $api;

		$pre_http_filter = function ( $return_value, $parsed_args, $url ) use ( $response, $method, $expected_url ) {
			if ( $url !== $expected_url ) {
				return $return_value;
			}
			if ( ( $parsed_args['method'] ?? null ) !== $method ) {
				return $return_value;
			}

			return $response;
		};

		$mock_logger = $this->createMock( \WC_Logger::class );
		\WC_Stripe_Logger::$logger = $mock_logger;

		$expected_data_keys = [
			'stripe_request_id',
			'response',
		];

		if ( 'POST' === $method ) {
			$expected_data_keys[] = 'idempotency_key';
			$expected_data_keys[] = 'request';
		}

		if (
			is_wp_error( $response ) &&
			'http_request_failed' === $response->get_error_code() &&
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			__( 'A valid URL was not provided.' ) === $response->get_error_message()
		) {
			$expected_data_keys[] = 'resolved_ip_address';
			$expected_data_keys[] = 'validation_details';
		}

		$expected_data_keys_callback = function ( $context ) use ( $expected_data_keys ) {
			$this->assertLessThanOrEqual( count( $context ), count( $expected_data_keys ) );
			foreach ( $expected_data_keys as $key ) {
				$this->assertArrayHasKey( $key, $context );
			}
			return true;
		};

		$mock_logger->expects( $this->once() )
			->method( 'error' )
			->with(
				$this->stringStartsWith( "Stripe API error: $method $api" ),
				$this->callback( $expected_data_keys_callback )
			);

		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		if ( 'GET' === $method ) {
			$result = WC_Stripe_API::retrieve( $api );
		} else {
			$caught_exception = null;
			try {
				$result = WC_Stripe_API::request( $request_data, $api, $method, false );
			} catch ( \WC_Stripe_Exception $stripe_exception ) {
				$caught_exception = $stripe_exception;
			}
		}

		// Clean up before we perform any assertions.
		remove_filter( 'pre_http_request', $pre_http_filter );
		\WC_Stripe_Logger::$logger = null;

		if ( 'GET' === $method ) {
			$this->assertInstanceof( \WP_Error::class, $result );
			$this->assertEquals( 'stripe_error', $result->get_error_code() );
			$this->assertEquals( __( 'There was a problem retrieving data from the Stripe API endpoint.', 'woocommerce-gateway-stripe' ), $result->get_error_message() );
		} else {
			$this->assertInstanceof( \WC_Stripe_Exception::class, $caught_exception );
			$this->assertEquals( print_r( $response, true ), $caught_exception->getMessage() );
			$this->assertEquals( __( 'There was a problem sending a request to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ), $caught_exception->getLocalizedMessage() );
		}
	}

	/**
	 * Data provider for {@see test_log_error_response()}.
	 *
	 * @return array
	 */
	public function provide_test_log_error_response_tests(): array {
		return [
			'generic error for GET account' => [
				'response' => new \WP_Error( 'mock_error', 'Mock Error' ),
				'api' => 'account',
				'method' => 'GET',
			],
			'generic error for POST account' => [
				'response' => new \WP_Error( 'mock_error', 'Mock Error' ),
				'api' => 'account',
				'method' => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
			'general http_request_failed error for GET account' => [
				'response' => new \WP_Error( 'http_request_failed', 'Mock Error' ),
				'api' => 'account',
				'method' => 'GET',
			],
			'general http_request_failed error for POST account' => [
				'response' => new \WP_Error( 'http_request_failed', 'Mock Error' ),
				'api' => 'account',
				'method' => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
			'URL validation http_request_failed error for GET account' => [
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'response' => new \WP_Error( 'http_request_failed', __( 'A valid URL was not provided.' ) ),
				'api' => 'account',
				'method' => 'GET',
			],
			'URL validation http_request_failed error for POST account' => [
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'response' => new \WP_Error( 'http_request_failed', __( 'A valid URL was not provided.' ) ),
				'api' => 'account',
				'method' => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
			'empty response body for GET account' => [
				'response' => [
					'response' => [
						'code' => 200,
						'message' => 'OK',
					],
					'body' => '',
				],
				'api' => 'account',
				'method' => 'GET',
			],
			'empty response body for POST account' => [
				'response' => [
					'response' => [
						'code' => 200,
						'message' => 'OK',
					],
					'body' => '',
				],
				'api' => 'account',
				'method' => 'POST',
				'request_data' => [ 'test' => 'test' ],
			],
		];
	}

	public function provide_test_should_detach_payment_method_from_customer(): array {
		return [
			'test mode from non-admin context should detach' => [
				'expected_return'        => true,
				'is_test_mode'           => true,
				'is_admin_request'       => false,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from non-admin context should detach' => [
				'expected_return'        => true,
				'is_test_mode'           => false,
				'is_admin_request'       => false,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'test mode from admin context should detach' => [
				'expected_return'        => true,
				'is_test_mode'           => true,
				'is_admin_request'       => true,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'test mode from wp cron context should detach' => [
				'expected_return'        => true,
				'is_test_mode'           => true,
				'is_admin_request'       => false,
				'is_cron_request'        => true,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from admin context with no subscription staging site should detach' => [
				'expected_return'        => true,
				'is_test_mode'           => false,
				'is_admin_request'       => true,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from wp cron context with no subscription staging site should detach' => [
				'expected_return'        => true,
				'is_test_mode'           => false,
				'is_admin_request'       => false,
				'is_cron_request'        => true,
				'is_wc_sub_staging_site' => false,
			],
			'live mode from admin context with subscription staging site should not detach' => [
				'expected_return'        => false,
				'is_test_mode'           => false,
				'is_admin_request'       => true,
				'is_cron_request'        => false,
				'is_wc_sub_staging_site' => true,
			],
			'live mode from wp cron context with subscription staging site should not detach' => [
				'expected_return'        => false,
				'is_test_mode'           => false,
				'is_admin_request'       => false,
				'is_cron_request'        => true,
				'is_wc_sub_staging_site' => true,
			],
			// Ideally, we would test multiple environment types, but wp_get_environment_type() uses a
			// static variable that can't be modified between tests.
		];
	}

	/**
	 * @dataProvider provide_test_should_detach_payment_method_from_customer
	 */
	public function test_should_detach_payment_method_from_customer( bool $expected_return, bool $is_test_mode, bool $is_admin_request, bool $is_cron_request, bool $is_wc_sub_staging_site = false ) {
		$initial_test_mode = \WC_Stripe_Mode::is_test();

		$stripe_settings = \WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		\WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$initial_current_screen = null;
		$reset_current_screen   = false;

		if ( $is_admin_request ) {
			$initial_current_screen = $GLOBALS['current_screen'] ?? null;
			$reset_current_screen   = true;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = \WP_Screen::get( 'post.php' );
		}

		$cron_filter_return = $is_cron_request ? '__return_true' : '__return_false';
		add_filter( 'wp_doing_cron', $cron_filter_return, 10, 1 );

		require_once __DIR__ . '/Helpers/WCS_Staging.php';
		\WCS_Staging::set_is_duplicate_site( $is_wc_sub_staging_site );

		$result = \WC_Stripe_API::should_detach_payment_method_from_customer();

		// Reset the environment before running any assertions.
		if ( $reset_current_screen ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = $initial_current_screen;
		}

		if ( $initial_test_mode !== $is_test_mode ) {
			$stripe_settings = \WC_Stripe_Helper::get_stripe_settings();
			$stripe_settings['testmode'] = $initial_test_mode ? 'yes' : 'no';
			\WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}

		remove_filter( 'wp_doing_cron', $cron_filter_return, 10 );

		\WCS_Staging::set_is_duplicate_site( false );

		$this->assertEquals( $expected_return, $result );
	}
}
