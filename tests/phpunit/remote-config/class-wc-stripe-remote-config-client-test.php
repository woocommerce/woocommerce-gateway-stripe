<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Client_Test extends WP_UnitTestCase {

	/** @var WC_Stripe_Remote_Config_Client */
	private $client;

	/** @var array Captured `pre_http_request` invocations. */
	private $captured_requests;

	/**
	 * `pre_http_request` stubs added by the current test, removed in tear_down().
	 *
	 * Removed one by one instead of with remove_all_filters(), so the bootstrap
	 * filter that blocks real HTTP requests stays in place for later tests.
	 *
	 * @var array<int, array{callback: callable, priority: int}>
	 */
	private $http_stubs = [];

	public function set_up(): void {
		parent::set_up();
		update_option( '_wcstripe_remote_config_enabled', 'yes' );
		$this->client            = new WC_Stripe_Remote_Config_Client();
		$this->captured_requests = [];
	}

	public function tear_down(): void {
		delete_option( '_wcstripe_remote_config_enabled' );
		foreach ( $this->http_stubs as $stub ) {
			remove_filter( 'pre_http_request', $stub['callback'], $stub['priority'] );
		}
		$this->http_stubs = [];
		parent::tear_down();
	}

	/**
	 * Adds a `pre_http_request` stub and records it for removal in tear_down().
	 *
	 * @param callable $callback Filter callback.
	 * @param int      $priority Filter priority.
	 * @return void
	 */
	private function add_http_stub( callable $callback, int $priority = 10 ): void {
		add_filter( 'pre_http_request', $callback, $priority, 3 );
		$this->http_stubs[] = [
			'callback' => $callback,
			'priority' => $priority,
		];
	}

	/**
	 * Combined envelope returned by stub_successful_response().
	 *
	 * @return array
	 */
	private function get_successful_envelope(): array {
		return [
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
		];
	}

	/**
	 * Stubs `pre_http_request` to capture each outbound request and return a
	 * canned 200 combined envelope, so a test can assert the request shape
	 * without a live network call. Kept out of set_up() so each test opts into
	 * the HTTP behaviour it needs explicitly.
	 */
	private function stub_successful_response(): void {
		$body = wp_json_encode( $this->get_successful_envelope() );
		$this->add_http_stub(
			function ( $preempt, $args, $url ) use ( $body ) {
				$this->captured_requests[] = [
					'url'  => $url,
					'args' => $args,
				];
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $body,
					'headers'  => [],
				];
			}
		);
	}

	public function test_fetch_all_request_shape_and_decoded_body(): void {
		$this->stub_successful_response();

		$result = $this->client->fetch_all();

		$this->assertSame( $this->get_successful_envelope(), $result );

		$this->assertCount( 1, $this->captured_requests );
		$url  = $this->captured_requests[0]['url'];
		$args = $this->captured_requests[0]['args'];

		$this->assertStringStartsWith( 'https://public-api.wordpress.com/wpcom/v2/woocommerce/stripe/remote-config', $url );
		$parsed_url = wp_parse_url( $url );
		$this->assertSame( '/wpcom/v2/woocommerce/stripe/remote-config', $parsed_url['path'] );

		$query_args = [];
		parse_str( $parsed_url['query'], $query_args );
		$this->assertArrayHasKey( 'mode', $query_args );
		$this->assertSame( 'all', $query_args['mode'] );
		$this->assertArrayHasKey( 'plugin_version', $query_args );
		$this->assertSame( WC_STRIPE_VERSION, $query_args['plugin_version'] );
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
		update_option( '_wcstripe_remote_config_enabled', 'no' );

		$result = $this->client->fetch_all();

		// Clean up before asserting so a failed assertion can't leak the override into later tests.
		update_option( '_wcstripe_remote_config_enabled', 'yes' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_disabled', $result->get_error_code() );
		$this->assertCount( 0, $this->captured_requests );
	}

	/**
	 * A pretty-printed body can be more than twice MAX_PAYLOAD_BYTES on the
	 * wire while each mode stays under the limit once decoded. The client must
	 * still decode it, so apply() can do the exact per-mode check.
	 *
	 * @return void
	 */
	public function test_fetch_all_accepts_pretty_printed_body_over_twice_the_payload_limit(): void {
		$payload = [
			'flags'        => [ 'optimized_checkout' => [ 'value' => true ] ],
			'generated_at' => '2026-05-09T12:00:00Z',
			'_padding'     => array_fill( 0, 4000, 'a' ),
		];
		$body    = wp_json_encode(
			[
				'modes'        => [
					'live' => $payload,
					'test' => $payload,
				],
				'generated_at' => '2026-05-09T12:00:00Z',
			],
			JSON_PRETTY_PRINT
		);
		$this->assertGreaterThan( 2 * WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES, strlen( $body ) );
		$this->assertLessThan( WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES, strlen( wp_json_encode( $payload ) ) );

		$this->add_http_stub(
			static function () use ( $body ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $body,
					'headers'  => [],
				];
			}
		);

		$result = $this->client->fetch_all();

		$this->assertIsArray( $result );
		$this->assertSame( $payload, $result['modes']['live'] );
		$this->assertSame( $payload, $result['modes']['test'] );
	}

	/**
	 * @dataProvider provide_failure_responses
	 */
	public function test_fetch_returns_wp_error_on_failure( $stub, ?string $expected_code ): void {
		$this->add_http_stub(
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
						'body'     => str_repeat( 'a', 4 * WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES + 1 ),
						'headers'  => [],
					];
				},
				'wc_stripe_remote_config_payload_too_large',
			],
		];
	}
}
