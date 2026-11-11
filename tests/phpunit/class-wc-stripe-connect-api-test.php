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
