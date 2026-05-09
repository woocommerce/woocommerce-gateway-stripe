<?php
/**
 * @package WooCommerce/Stripe
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-client.php';

class WC_Stripe_Remote_Config_Client_Test extends WP_UnitTestCase {

	/** @var WC_Stripe_Remote_Config_Client */
	private $client;

	/** @var array Captured `pre_http_request` invocations. */
	private $captured_requests;

	public function set_up(): void {
		parent::set_up();
		$this->client            = new WC_Stripe_Remote_Config_Client();
		$this->captured_requests = [];

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
							'ttl'          => 86400,
						]
					),
					'headers'  => [],
				];
			},
			10,
			3
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	public function test_fetch_hits_pinned_wpcom_url_with_query_params(): void {
		$result = $this->client->fetch( 'live' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->captured_requests );

		$url = $this->captured_requests[0]['url'];
		$this->assertStringStartsWith( 'https://public-api.wordpress.com/wpcom/v2/woocommerce/stripe/remote-config', $url );
		$this->assertStringContainsString( 'mode=live', $url );
		$this->assertStringContainsString( 'plugin_version=' . WC_STRIPE_VERSION, $url );
	}

	public function test_fetch_uses_sslverify_true_and_get_method(): void {
		$this->client->fetch( 'live' );

		$args = $this->captured_requests[0]['args'];
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 'GET', $args['method'] );
		$this->assertSame( 10, $args['timeout'] );
	}

	public function test_fetch_returns_decoded_body(): void {
		$result = $this->client->fetch( 'live' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'flags', $result );
		$this->assertSame( false, $result['flags']['optimized_checkout']['value'] );
	}

	public function test_fetch_does_not_route_through_connect_api_filters(): void {
		// The deprecated Connect API filters must not affect this client.
		$tampered = false;
		add_filter(
			'wc_connect_server_url',
			function ( $url ) use ( &$tampered ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $url required by filter signature
				$tampered = true;
				return 'https://evil.example.com';
			}
		);
		add_filter(
			'wc_connect_request_args',
			function ( $args ) use ( &$tampered ) {
				$tampered          = true;
				$args['sslverify'] = false;
				return $args;
			}
		);

		$this->client->fetch( 'live' );

		$this->assertFalse( $tampered, 'Connect_API filters must not run for remote-config requests' );
		$this->assertStringStartsWith( 'https://public-api.wordpress.com', $this->captured_requests[0]['url'] );
		$this->assertTrue( $this->captured_requests[0]['args']['sslverify'] );
	}

	public function test_fetch_short_circuits_when_disabled_by_filter(): void {
		add_filter( 'wc_stripe_remote_config_enabled', '__return_false' );

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_disabled', $result->get_error_code() );
		$this->assertCount( 0, $this->captured_requests );

		remove_filter( 'wc_stripe_remote_config_enabled', '__return_false' );
	}

	public function test_fetch_returns_wp_error_on_http_error(): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Could not connect' );
			}
		);

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
	}

	public function test_fetch_returns_wp_error_on_non_200_response(): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [
						'code'    => 503,
						'message' => 'Service Unavailable',
					],
					'body'     => '',
					'headers'  => [],
				];
			}
		);

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_http_error', $result->get_error_code() );
	}

	public function test_fetch_returns_wp_error_on_invalid_json(): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'not-json',
					'headers'  => [],
				];
			}
		);

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_invalid_json', $result->get_error_code() );
	}

	public function test_fetch_rejects_oversized_payload(): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => str_repeat( 'a', WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES + 1 ),
					'headers'  => [],
				];
			}
		);

		$result = $this->client->fetch( 'live' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_stripe_remote_config_payload_too_large', $result->get_error_code() );
	}

	/**
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
