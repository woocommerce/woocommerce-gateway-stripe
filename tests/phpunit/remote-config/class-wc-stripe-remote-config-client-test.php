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
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );
		$this->client            = new WC_Stripe_Remote_Config_Client();
		$this->captured_requests = [];
	}

	public function tear_down(): void {
		delete_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Stubs `pre_http_request` to capture each outbound request and return a
	 * canned 200 combined envelope, so a test can assert the request shape
	 * without a live network call. Kept out of set_up() so each test opts into
	 * the HTTP behaviour it needs explicitly.
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
							'modes'        => [
								'live' => [
									'flags'        => [ 'optimized_checkout' => [ 'value' => false ] ],
									'generated_at' => '2026-05-09T12:00:00Z',
								],
								'test' => [
									'flags'        => [ 'optimized_checkout' => [ 'value' => true ] ],
									'generated_at' => '2026-05-09T12:00:00Z',
								],
							],
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

	public function test_fetch_all_request_shape_and_decoded_body(): void {
		$this->stub_successful_response();

		$result = $this->client->fetch_all();

		$this->assertIsArray( $result );
		$this->assertSame( false, $result['modes']['live']['flags']['optimized_checkout']['value'] );
		$this->assertSame( true, $result['modes']['test']['flags']['optimized_checkout']['value'] );

		$this->assertCount( 1, $this->captured_requests );
		$url  = $this->captured_requests[0]['url'];
		$args = $this->captured_requests[0]['args'];

		$this->assertStringStartsWith( 'https://public-api.wordpress.com/wpcom/v2/woocommerce/stripe/remote-config', $url );
		$this->assertStringContainsString( 'mode=all', $url );
		$this->assertStringContainsString( 'plugin_version=' . WC_STRIPE_VERSION, $url );
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 'GET', $args['method'] );
		$this->assertSame( 10, $args['timeout'] );
	}

	public function test_fetch_all_sends_store_identity_query_params(): void {
		$this->stub_successful_response();
		// The account country can differ between a dual-keyed store's live and
		// test accounts, so both travel under mode-prefixed params.
		WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, [ 'country' => 'US' ], DAY_IN_SECONDS, 'live' );
		WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, [ 'country' => 'BR' ], DAY_IN_SECONDS, 'test' );

		$this->client->fetch_all();

		$query = [];
		wp_parse_str( (string) wp_parse_url( $this->captured_requests[0]['url'], PHP_URL_QUERY ), $query );

		$this->assertSame( 'all', $query['mode'] );
		$this->assertSame( WC_STRIPE_VERSION, $query['plugin_version'] );
		$this->assertSame( WC_VERSION, $query['wc_version'] );
		$this->assertSame( 'US', $query['live_account_country'] );
		$this->assertSame( 'BR', $query['test_account_country'] );
		$this->assertSame( get_woocommerce_currency(), $query['store_currency'] );
		// `WC_Subscriptions` stub is loaded by the test bootstrap; `WC_Pre_Orders` has no stub.
		$this->assertSame( '1', $query['subscriptions_enabled'] );
		$this->assertSame( '0', $query['pre_orders_enabled'] );

		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'live' );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'test' );
	}

	public function test_fetch_all_omits_account_countries_when_cache_missing(): void {
		$this->stub_successful_response();
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'live' );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'test' );

		$this->client->fetch_all();

		$query = [];
		wp_parse_str( (string) wp_parse_url( $this->captured_requests[0]['url'], PHP_URL_QUERY ), $query );
		$this->assertArrayNotHasKey( 'live_account_country', $query );
		$this->assertArrayNotHasKey( 'test_account_country', $query );
	}

	public function test_fetch_short_circuits_when_disabled_by_override(): void {
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'no' );

		$result = $this->client->fetch_all();

		// Clean up before asserting so a failed assertion can't leak the override into later tests.
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );

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

		$result = $this->client->fetch_all();

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
						'body'     => str_repeat( 'a', 2 * WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES + 1 ),
						'headers'  => [],
					];
				},
				'wc_stripe_remote_config_payload_too_large',
			],
		];
	}
}
