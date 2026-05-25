<?php

/**
 * Tests for WC_Stripe_API_Outage_Status.
 */
class WC_Stripe_API_Outage_Status_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_transient( WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY );
	}

	public function tear_down() {
		delete_transient( WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY );
		parent::tear_down();
	}

	/**
	 * @dataProvider provide_outage_response_classification
	 */
	public function test_is_outage_response( $response, bool $expected ) {
		$this->assertSame( $expected, WC_Stripe_API_Outage_Status::is_outage_response( $response ) );
	}

	public function provide_outage_response_classification(): array {
		$response_with_code = function ( int $code ) {
			return [
				'response' => [
					'code'    => $code,
					'message' => '',
				],
				'headers'  => [],
				'body'     => '{}',
			];
		};

		return [
			'WP_Error from network failure'          => [ new WP_Error( 'http_request_failed', 'Boom' ), true ],
			'500 Internal Server Error'              => [ $response_with_code( 500 ), true ],
			'502 Bad Gateway'                        => [ $response_with_code( 502 ), true ],
			'503 Service Unavailable'                => [ $response_with_code( 503 ), true ],
			'504 Gateway Timeout'                    => [ $response_with_code( 504 ), true ],
			'200 OK is not an outage'                => [ $response_with_code( 200 ), false ],
			'400 Bad Request is not an outage'       => [ $response_with_code( 400 ), false ],
			'401 Unauthorized is not an outage'      => [ $response_with_code( 401 ), false ],
			'404 Not Found is not an outage'         => [ $response_with_code( 404 ), false ],
			'429 Too Many Requests is not an outage' => [ $response_with_code( 429 ), false ],
			'501 Not Implemented is not an outage'   => [ $response_with_code( 501 ), false ],
		];
	}

	public function test_record_outage_sets_transient_with_detected_at() {
		$before = time();
		WC_Stripe_API_Outage_Status::record_outage();
		$after = time();

		$this->assertTrue( WC_Stripe_API_Outage_Status::is_in_outage() );

		$stored = get_transient( WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY );
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( 'detected_at', $stored );
		$this->assertGreaterThanOrEqual( $before, (int) $stored['detected_at'] );
		$this->assertLessThanOrEqual( $after, (int) $stored['detected_at'] );
	}

	public function test_record_outage_preserves_initial_detected_at() {
		WC_Stripe_API_Outage_Status::record_outage();
		$stored         = get_transient( WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY );
		$first_detected = (int) $stored['detected_at'];

		// Move the recorded detection backwards by writing a known earlier
		// timestamp, then re-record. The second call must keep the earlier
		// timestamp rather than overwriting with `time()`.
		set_transient(
			WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY,
			[ 'detected_at' => $first_detected - 60 ],
			WC_Stripe_API_Outage_Status::OUTAGE_TTL
		);

		WC_Stripe_API_Outage_Status::record_outage();

		$stored_after = get_transient( WC_Stripe_API_Outage_Status::OUTAGE_TRANSIENT_KEY );
		$this->assertSame( $first_detected - 60, (int) $stored_after['detected_at'] );
	}

	public function test_record_success_clears_outage() {
		WC_Stripe_API_Outage_Status::record_outage();
		$this->assertTrue( WC_Stripe_API_Outage_Status::is_in_outage() );

		WC_Stripe_API_Outage_Status::record_success();

		$this->assertFalse( WC_Stripe_API_Outage_Status::is_in_outage() );
	}

	public function test_is_in_outage_defaults_to_false() {
		$this->assertFalse( WC_Stripe_API_Outage_Status::is_in_outage() );
	}
}
