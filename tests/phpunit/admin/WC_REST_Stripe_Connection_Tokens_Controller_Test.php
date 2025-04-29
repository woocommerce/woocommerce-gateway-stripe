<?php
/**
 * Class WC_REST_Stripe_Connection_Tokens_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_REST_Stripe_Connection_Tokens_Controller
 */

/**
 * WC_REST_Stripe_Connection_Tokens_Controller unit tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_REST_Stripe_Connection_Tokens_Controller_Test extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const CONNECTION_TOKENS_ROUTE = '/wc/v3/wc_stripe/connection_tokens';

	public function test_token_request_requires_auth() {
		wp_set_current_user( 0 );

		// Unauthenticated user should not be able to access route.
		$request  = new WP_REST_Request( 'POST', self::CONNECTION_TOKENS_ROUTE );
		$response = rest_do_request( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_token_request_success() {
		wp_set_current_user( 1 );

		// Mock response from Stripe API.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'object' => 'terminal.connection_token',
						'secret' => 'pst_test_12345678901234567890123',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $test_request, 10, 3 );

		// Request for a token should succeed.
		$request  = new WP_REST_Request( 'POST', self::CONNECTION_TOKENS_ROUTE );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'pst_test_12345678901234567890123', $response->get_data()->secret );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}
}
