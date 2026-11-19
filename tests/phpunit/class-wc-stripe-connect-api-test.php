<?php

class WC_Stripe_Connect_API_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider provide_deauthorization_responses
	 * @param array $responses     Consecutive HTTP responses.
	 * @param int   $request_count Expected number of attempts.
	 * @param bool  $confirmed     Whether revocation can be confirmed.
	 */
	public function test_deauthorization_retries( $responses, $request_count, $confirmed ) {
		$requests = [];
		$mock     = function ( $preempt, $args, $url ) use ( &$requests, $responses ) {
			$this->assertStringEndsWith( '/stripe/account/deauthorize-by-key', $url );
			$this->assertSame( 'POST', $args['method'] );
			$body = json_decode( $args['body'], true );
			$this->assertSame( 'acct_123', $body['accountId'] );
			$this->assertSame( 'sk_live_example', $body['secretKey'] );
			$this->assertArrayNotHasKey( 'platform', $body );
			$this->assertGreaterThan( 0, $args['timeout'] );
			$this->assertLessThanOrEqual( 5, $args['timeout'] );
			$requests[] = $args;
			return $responses[ min( count( $requests ) - 1, count( $responses ) - 1 ) ];
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );

		try {
			$response = ( new WC_Stripe_Connect_API() )->deauthorize_account( 'acct_123', 'sk_live_example' );
			$this->assertCount( $request_count, $requests );
			$this->assertSame( ! $confirmed, is_wp_error( $response ) );
			if ( $confirmed ) {
				$this->assertSame( 'acct_123', $response->accountId ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		} finally {
			remove_filter( 'pre_http_request', $mock );
		}
	}

	public function provide_deauthorization_responses() {
		$success      = $this->http_response( 200, [ 'accountId' => 'acct_123' ] );
		$unavailable  = $this->http_response( 503, [ 'message' => 'Unavailable' ] );
		$invalid_key  = $this->http_response( 400, [ 'message' => 'The provided key could not be verified for this account.' ] );
		$legacy_error = $this->http_response( 400, [ 'message' => 'Failed to deauthorize account.' ] );

		return [
			'success is not retried'                         => [ [ $success ], 1, true ],
			'transport failure then success'                 => [ [ new WP_Error( 'http_request_failed', 'Connection failed' ), $success ], 2, true ],
			'rate limit then success'                        => [ [ $this->http_response( 429, [] ), $success ], 2, true ],
			'upstream timeout then success'                  => [ [ $this->http_response( 504, [] ), $success ], 2, true ],
			'two failures then success'                      => [ [ $unavailable, $unavailable, $success ], 3, true ],
			'retries exhausted'                              => [ [ $unavailable ], 3, false ],
			'legacy generic upstream failure then success'   => [ [ $legacy_error, $success ], 2, true ],
			'legacy generic upstream failure stays unknown'  => [ [ $legacy_error ], 3, false ],
			'invalid key is not retried'                     => [ [ $invalid_key ], 1, false ],
			'validation failure is not retried'              => [ [ $this->http_response( 400, [ 'message' => 'Invalid request payload input' ] ) ], 1, false ],
			'unauthorized request is not retried'            => [ [ $this->http_response( 401, [] ) ], 1, false ],
			'timeout then invalid key remains unconfirmed'   => [ [ new WP_Error( 'http_request_failed', 'Operation timed out' ), $invalid_key ], 2, false ],
			'missing account confirmation remains uncertain' => [ [ $this->http_response( 200, [] ) ], 1, false ],
			'wrong account confirmation remains uncertain'   => [ [ $this->http_response( 200, [ 'accountId' => 'acct_other' ] ) ], 1, false ],
			'retry after exceeds budget'                     => [ [ $this->http_response( 429, [], [ 'retry-after' => '120' ] ) ], 1, false ],
		];
	}

	/**
	 * Malformed error bodies must not abort disconnection or change valid error data.
	 *
	 * @dataProvider provide_error_response_bodies
	 * @param mixed  $body             Decoded response body.
	 * @param string $expected_message Expected formatted error message.
	 * @param mixed  $expected_data    Data exposed to the error hook.
	 */
	public function test_error_response_fields( $body, $expected_message, $expected_data ) {
		$mock          = function () use ( $body ) {
			return $this->http_response( 400, $body );
		};
		$captured_data = 'not captured';
		$capture_data  = function ( $code, $message, $data ) use ( &$captured_data ) {
			if ( 'wcc_server_error_response' === $code ) {
				$captured_data = $data;
			}
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		add_action( 'wp_error_added', $capture_data, 10, 3 );

		try {
			$response = ( new WC_Stripe_Connect_API() )->deauthorize_account( 'acct_123', 'sk_live_example' );
			$this->assertWPError( $response );
			$this->assertSame( 'wcc_server_error_response', $response->get_error_code() );
			$this->assertSame( $expected_message, $response->get_error_message() );
			$this->assertSame( $expected_data, $captured_data );
			$this->assertSame( empty( $expected_data ) ? null : $expected_data, $response->get_error_data() );
		} finally {
			remove_filter( 'pre_http_request', $mock );
			remove_action( 'wp_error_added', $capture_data );
		}
	}

	public function provide_error_response_bodies() {
		$generic_message = 'Error: The WooCommerce Connect server returned:   ( 400 )';
		$valid_message   = 'Error: The WooCommerce Connect server returned: Bad Request Unavailable ( 400 )';

		return [
			'nonempty array'        => [ [ 'Unavailable' ], $generic_message, '' ],
			'integer'               => [ 123, $generic_message, '' ],
			'boolean'               => [ true, $generic_message, '' ],
			'string'                => [ 'Unavailable', $generic_message, '' ],
			'nested error fields'   => [
				[
					'error'   => (object) [ 'code' => 'invalid' ],
					'message' => [ 'Unavailable' ],
				],
				$generic_message,
				'',
			],
			'valid error with data' => [
				[
					'error'   => 'Bad Request',
					'message' => 'Unavailable',
					'data'    => [ 'invalid_key' ],
				],
				$valid_message,
				[ 'invalid_key' ],
			],
			'explicit null data'    => [
				[
					'error'   => 'Bad Request',
					'message' => 'Unavailable',
					'data'    => null,
				],
				$valid_message,
				null,
			],
			'numeric error code'    => [
				[
					'error'   => 123,
					'message' => 'Unavailable',
				],
				'Error: The WooCommerce Connect server returned: 123 Unavailable ( 400 )',
				'',
			],
		];
	}

	public function test_other_oauth_requests_keep_their_timeout_and_are_not_retried() {
		$count = 0;
		$mock  = function ( $preempt, $args, $url ) use ( &$count ) {
			++$count;
			$this->assertStringEndsWith( '/stripe/app-oauth-keys-refresh', $url );
			$this->assertSame( 60, $args['timeout'] );
			return $this->http_response( 503, [ 'message' => 'Unavailable' ] );
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );

		try {
			$response = ( new WC_Stripe_Connect_API() )->refresh_stripe_app_oauth_keys( 'rt_example' );
			$this->assertWPError( $response );
			$this->assertSame( 1, $count );
		} finally {
			remove_filter( 'pre_http_request', $mock );
		}
	}

	private function http_response( $status, $body, $headers = [] ) {
		return [
			'headers'  => array_merge( [ 'content-type' => 'application/json' ], $headers ),
			'response' => [ 'code' => $status ],
			'body'     => wp_json_encode( $body ),
		];
	}
}
