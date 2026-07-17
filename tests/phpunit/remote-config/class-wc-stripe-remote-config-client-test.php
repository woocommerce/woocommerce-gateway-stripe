<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Client_Test extends WP_UnitTestCase {

	/** @var WC_Stripe_Remote_Config_Client */
	private $client;

	/** @var array Captured `pre_http_request` invocations. */
	private $captured_requests;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'wc_stripe_remote_config_enabled', '__return_true' );
		$this->client            = new WC_Stripe_Remote_Config_Client();
		$this->captured_requests = [];
	}

	public function tear_down(): void {
		remove_filter( 'wc_stripe_remote_config_enabled', '__return_true' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Stubs `pre_http_request` to capture each outbound request and return a
	 * canned 200 response, so a test can assert the request shape without a
	 * live network call. Kept out of set_up() so each test opts into the HTTP
	 * behaviour it needs explicitly.
	 */
	private function stub_successful_response(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->captured_requests[] = [
					'url'  => $url,
					'args' => $args,
				];
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode(
						[
							'flags'        => [ 'optimized_checkout' => [ 'value' => false ] ],
							'generated_at' => '2026-05-09T12:00:00Z',
						]
					),
					'headers'  => [],
				];
			},
			10,
			3
		);
	}

	public function test_fetch_request_shape_and_decoded_body(): void {
		$this->stub_successful_response();

		$result = $this->client->fetch( 'live' );

		$this->assertIsArray( $result );
		$this->assertSame( false, $result['flags']['optimized_checkout']['value'] );

		$this->assertCount( 1, $this->captured_requests );
		$url  = $this->captured_requests[0]['url'];
		$args = $this->captured_requests[0]['args'];

		$this->assertStringStartsWith( 'https://public-api.wordpress.com/wpcom/v2/woocommerce/stripe/remote-config', $url );
		$this->assertStringContainsString( 'mode=live', $url );
		$this->assertStringContainsString( 'plugin_version=' . WC_STRIPE_VERSION, $url );
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 'GET', $args['method'] );
		$this->assertSame( 10, $args['timeout'] );
	}

	public function test_fetch_short_circuits_when_disabled_by_filter(): void {
		add_filter( 'wc_stripe_remote_config_enabled', '__return_false' );

		$result = $this->client->fetch( 'live' );

		// Clean up before asserting so a failed assertion can't leak the filter into later tests.
		remove_filter( 'wc_stripe_remote_config_enabled', '__return_false' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_disabled', $result->get_error_code() );
		$this->assertCount( 0, $this->captured_requests );
	}

	/**
	 * @dataProvider provide_failure_responses
	 */
	public function test_fetch_returns_wp_error_on_failure( $stub, ?string $expected_code ): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () use ( $stub ) {
				return is_callable( $stub ) ? $stub() : $stub;
			}
		);

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
		if ( null !== $expected_code ) {
			$this->assertSame( $expected_code, $result->get_error_code() );
		}
	}

	public function provide_failure_responses(): array {
		return [
			'wp http transport error' => [
				new WP_Error( 'http_request_failed', 'Could not connect' ),
				null, // Error code is whatever WP returns; only assert it's a WP_Error.
			],
			'non-200 response'        => [
				[
					'response' => [
						'code'    => 503,
						'message' => 'Service Unavailable',
					],
					'body'     => '',
					'headers'  => [],
				],
				'wc_stripe_remote_config_http_error',
			],
			'invalid json'            => [
				[
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'not-json',
					'headers'  => [],
				],
				'wc_stripe_remote_config_invalid_json',
			],
			'oversized payload'       => [
				static function () {
					return [
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => str_repeat( 'a', WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES + 1 ),
						'headers'  => [],
					];
				},
				'wc_stripe_remote_config_payload_too_large',
			],
		];
	}

	/**
	 * Runs in a separate process because it defines WC_STRIPE_DISABLE_REMOTE_CONFIG,
	 * which cannot be undefined once set and would otherwise short-circuit every
	 * later test in the run.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_fetch_short_circuits_when_disabled_by_constant(): void {
		define( 'WC_STRIPE_DISABLE_REMOTE_CONFIG', true );

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_disabled', $result->get_error_code() );
		$this->assertCount( 0, $this->captured_requests );
	}
}
