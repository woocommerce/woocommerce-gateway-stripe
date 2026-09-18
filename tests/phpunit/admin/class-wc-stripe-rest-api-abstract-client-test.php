<?php
/**
 * Class WC_Stripe_REST_API_Abstract_Client_Test
 */
class WC_Stripe_REST_API_Abstract_Client_Test extends WP_UnitTestCase {
	/**
	 *	Tests that only the specified request parameters are added to the list of parameters to be forwarded to Stripe API.
	 */
	public function test_build_params_to_forward() {
		$all_params = [
			'test_param'            => 'test_val',
			'test_param_to_forward' => 'test_val_to_forward'
		];

		$params_to_forward = [ 'test_param_to_forward' ];
		$expand_param      = [ 'test_expand_param' ];

		$request = new WP_REST_Request();

		foreach ( $all_params as $param_name => $param_value ) {
			$request->set_param( $param_name, $param_value );
		}

		$forwarded_params = WC_Stripe_REST_API_Abstract_Client::build_params_to_forward( $request, $params_to_forward, $expand_param );

		$this->assertEquals( 2, count( $forwarded_params ) );
	
		$this->assertTrue( array_key_exists( 'test_param_to_forward', $forwarded_params) );
		$this->assertEquals( $all_params[ 'test_param_to_forward' ], $forwarded_params[ 'test_param_to_forward' ]);

		$this->assertTrue( array_key_exists( 'expand', $forwarded_params) );
		$this->assertEquals( $expand_param, $forwarded_params[ 'expand' ]);
	}

	/**
	 * Tests that only specified request parameters are forwarded to Stripe API.
	 */
	public function test_fetch_from_stripe() {
		$params = [
			'param1' => 'val1',
			'param2' => 'val2',
		];

		$forwarded_params = [];

		$pre_http_request_handler = function ( $pre, $parsed_args, $url ) use ( &$forwarded_params ) {
			$query_string = parse_url( $url, PHP_URL_QUERY );
			parse_str( $query_string, $forwarded_params );

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			];
		};

		add_filter(
			'pre_http_request',
			$pre_http_request_handler,
			10,
			3
		);

		WC_Stripe_REST_API_Abstract_Client::fetch_from_stripe( 'test_endpoint', $params );

		remove_filter(
			'pre_http_request',
			$pre_http_request_handler,
			10,
			3
		);

		$this->assertEquals( $params, $forwarded_params );
	}
}
