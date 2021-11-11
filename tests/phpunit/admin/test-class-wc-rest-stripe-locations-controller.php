<?php
/**
 * Class WC_REST_Stripe_Locations_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_REST_Stripe_Locations_Controller
 */

/**
 * WC_REST_Stripe_Locations_Controller unit tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_REST_Stripe_Locations_Controller_Test extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const LOCATIONS_REST_BASE = '/wc/v3/wc_stripe/terminal/locations';

	public function test_create_location_missing_params_fails() {
		wp_set_current_user( 1 );

		// Missing display_name or address parameter should be a bad request.
		$request  = new WP_REST_Request( 'POST', self::LOCATIONS_REST_BASE );
		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_create_location_requires_auth() {
		wp_set_current_user( 0 );

		// Unauthenticated user should not be able to access route.
		$request = new WP_REST_Request( 'POST', self::LOCATIONS_REST_BASE );
		$request->set_param( 'display_name', 'Test Store' );
		$request->set_param(
			'address',
			[
				'line1'       => '1 Example St.',
				'city'        => 'Example City',
				'country'     => 'US',
				'state'       => 'CA',
				'postal_code' => '12345',
			]
		);
		$response = rest_do_request( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_create_location_success() {
		wp_set_current_user( 1 );

		// Mock response from Stripe API using request arguments.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'           => 'tml_12345678901234567890123',
						'object'       => 'terminal.location',
						'display_name' => $parsed_args['body']['display_name'],
						'address'      => [
							'line1' => $parsed_args['body']['address']['line1'],
						],
					]
				),
			];
		};
			add_filter( 'pre_http_request', $test_request, 10, 3 );

			$request = new WP_REST_Request( 'POST', self::LOCATIONS_REST_BASE );
			$request->set_param( 'display_name', 'Test Store' );
			$request->set_param(
				'address',
				[
					'line1'       => '1 Example St.',
					'city'        => 'Example City',
					'country'     => 'US',
					'state'       => 'CA',
					'postal_code' => '12345',
				]
			);

		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'terminal.location', $response->get_data()->object );
		$this->assertEquals( 'tml_12345678901234567890123', $response->get_data()->id );
		$this->assertEquals( 'Test Store', $response->get_data()->display_name );
		$this->assertEquals( '1 Example St.', $response->get_data()->address->line1 );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}

	public function test_update_location_requires_auth() {
		wp_set_current_user( 0 );

		// Unauthenticated user should not be able to access route.
		$request  = new WP_REST_Request( 'POST', self::LOCATIONS_REST_BASE . '/tml_12345' );
		$response = rest_do_request( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_update_location_succeeds() {
		wp_set_current_user( 1 );

		// Mock response from Stripe API using request arguments.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'           => 'tml_12345678901234567890123',
						'object'       => 'terminal.location',
						'display_name' => isset( $parsed_args['body']['display_name'] ) ? $parsed_args['body']['display_name'] : 'Not Changed',
						'address'      => isset( $parsed_args['body']['address'] ) ? $parsed_args['body']['address'] : [ 'line1' => 'Not Changed' ],
					]
				),
			];
		};
			add_filter( 'pre_http_request', $test_request, 10, 3 );

			$request = new WP_REST_Request( 'POST', self::LOCATIONS_REST_BASE . '/tml_12345' );
			$request->set_param( 'display_name', 'New Store Name' );

			$response = rest_do_request( $request );
			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals( 'terminal.location', $response->get_data()->object );
			$this->assertEquals( 'tml_12345678901234567890123', $response->get_data()->id );
			$this->assertEquals( 'New Store Name', $response->get_data()->display_name );
			$this->assertEquals( 'Not Changed', $response->get_data()->address->line1 );

			remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}

	public function test_get_store_location_requires_auth() {
		wp_set_current_user( 0 );

		// Unauthenticated user should not be able to access route.
		$request  = new WP_REST_Request( 'GET', self::LOCATIONS_REST_BASE . '/store' );
		$response = rest_do_request( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_get_store_location_returns_correct_location() {
		wp_set_current_user( 1 );

		// Mock response from Stripe API using request arguments.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			if ( 'GET' === $parsed_args['method'] ) {
				// Mock response for getting existing locations.
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode(
						[
							'data' => [
								[
									'id'           => 'tml_00001',
									'display_name' => 'Unused Test Store',
									'address'      => [
										'city'        => 'Example City',
										'country'     => 'US',
										'line1'       => '2 Example St.',
										'postal_code' => '12345',
										'state'       => 'CA',
									],
								],
								[
									'id'           => 'tml_00002',
									'display_name' => 'Test Store',
									'address'      => [
										'city'        => 'Example City',
										'country'     => 'US',
										'line1'       => '1 Example St.',
										'postal_code' => '12345',
										'state'       => 'CA',
									],
								],
							],
						]
					),
				];
			} else {
				// Mock response for generating new location.
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode(
						[
							'id'           => 'tml_00003',
							'display_name' => 'New Test Store',
							'address'      => [
								'city'        => 'Example City',
								'country'     => 'US',
								'line1'       => '3 Example St.',
								'postal_code' => '12345',
								'state'       => 'CA',
							],
						]
					),
				];
			}
		};

			add_filter( 'pre_http_request', $test_request, 10, 3 );

			// Test an existing store, ensure existing location is returned.
			update_option( 'blogname', 'Test Store' );
			update_option( 'woocommerce_store_address', '1 Example St.' );
			update_option( 'woocommerce_store_city', 'Example City' );
			update_option( 'woocommerce_store_postcode', '12345' );
			update_option( 'woocommerce_default_country', 'US:CA' );

			$request  = new WP_REST_Request( 'GET', self::LOCATIONS_REST_BASE . '/store' );
			$response = rest_do_request( $request );
			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals( 'tml_00002', $response->get_data()->id );
			$this->assertEquals( 'Test Store', $response->get_data()->display_name );
			$this->assertEquals( '1 Example St.', $response->get_data()->address->line1 );

			// Test a new store, ensure a new location is returned.
			update_option( 'blogname', 'New Test Store' );
			update_option( 'woocommerce_store_address', '3 Example St.' );

			$request  = new WP_REST_Request( 'GET', self::LOCATIONS_REST_BASE . '/store' );
			$response = rest_do_request( $request );
			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals( 'tml_00003', $response->get_data()->id );
			$this->assertEquals( 'New Test Store', $response->get_data()->display_name );
			$this->assertEquals( '3 Example St.', $response->get_data()->address->line1 );

			remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}

}
