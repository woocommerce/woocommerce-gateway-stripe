<?php

/**
 * Tests for the Stripe SDK <-> WordPress HTTP API transport adapter.
 *
 * Drives the adapter directly so that:
 *   1. POST/GET/DELETE requests reach `wp_safe_remote_request` with the
 *      headers, body, and URL the SDK contract requires.
 *   2. `pre_http_request`-based mocks (which power the rest of the test suite)
 *      keep working when SDK calls are routed through the adapter.
 *   3. WP HTTP errors surface as `\Stripe\Exception\ApiConnectionException`.
 *   4. The adapter rejects file uploads, which it does not (yet) support.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_SDK_WP_Http_Client
 */
class WC_Stripe_SDK_WP_Http_Client_Test extends WP_UnitTestCase {

	/**
	 * Captured args from the most recent `wp_safe_remote_request` call.
	 *
	 * @var array{url: string, args: array<string, mixed>}|null
	 */
	private $captured = null;

	/**
	 * Return value for the next intercepted HTTP call.
	 *
	 * @var array|WP_Error
	 */
	private $next_response = [
		'response' => [ 'code' => 200 ],
		'body'     => '{}',
		'headers'  => [],
	];

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', [ $this, 'capture_request' ], 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'capture_request' ], 10 );
		$this->captured      = null;
		$this->next_response = [
			'response' => [ 'code' => 200 ],
			'body'     => '{}',
			'headers'  => [],
		];
		parent::tear_down();
	}

	/**
	 * Captures the request and returns the canned response. Hooked to
	 * `pre_http_request` so we can intercept without ever touching the network.
	 */
	public function capture_request( $preempt, $args, $url ) {
		$this->captured = [
			'url'  => $url,
			'args' => $args,
		];
		return $this->next_response;
	}

	/**
	 * v1 POST requests should send a form-encoded body (whatever WP would do
	 * with an array `body` arg) and propagate the SDK headers verbatim.
	 *
	 * @return void
	 */
	public function test_post_v1_sends_form_body_and_headers() {
		$client = new WC_Stripe_SDK_WP_Http_Client();

		$result = $client->request(
			'post',
			'https://api.stripe.com/v1/customers',
			[ 'Authorization: Bearer sk_test_xyz', 'Stripe-Version: 2025-09-30.clover' ],
			[ 'email' => 'shop@example.test' ],
			false
		);

		$this->assertSame( 'POST', $this->captured['args']['method'] );
		$this->assertSame( 'https://api.stripe.com/v1/customers', $this->captured['url'] );
		$this->assertSame( [ 'email' => 'shop@example.test' ], $this->captured['args']['body'] );
		$this->assertSame( 'Bearer sk_test_xyz', $this->captured['args']['headers']['Authorization'] );
		$this->assertSame( '2025-09-30.clover', $this->captured['args']['headers']['Stripe-Version'] );

		$this->assertSame( '{}', $result[0] );
		$this->assertSame( 200, $result[1] );
		$this->assertIsArray( $result[2] );
	}

	/**
	 * v2 POST requests need to be JSON-encoded and carry an `application/json`
	 * Content-Type header so Stripe parses them correctly.
	 *
	 * @return void
	 */
	public function test_post_v2_sends_json_body() {
		$client = new WC_Stripe_SDK_WP_Http_Client();

		$client->request(
			'post',
			'https://api.stripe.com/v2/billing/meter_event_session',
			[ 'Authorization: Bearer sk_test_xyz' ],
			[ 'identifier' => 'evt_123' ],
			false,
			'v2'
		);

		$this->assertSame( wp_json_encode( [ 'identifier' => 'evt_123' ] ), $this->captured['args']['body'] );
		$this->assertSame( 'application/json', $this->captured['args']['headers']['Content-Type'] );
	}

	/**
	 * GET (and DELETE) requests must move params onto the URL query string —
	 * `wp_safe_remote_request` will not put a body on a GET that Stripe would
	 * accept.
	 *
	 * @return void
	 */
	public function test_get_appends_params_as_query_string() {
		$client = new WC_Stripe_SDK_WP_Http_Client();

		$client->request(
			'get',
			'https://api.stripe.com/v1/customers',
			[ 'Authorization: Bearer sk_test_xyz' ],
			[ 'limit' => 10 ],
			false
		);

		$this->assertSame( 'GET', $this->captured['args']['method'] );
		$this->assertStringContainsString( 'limit=10', $this->captured['url'] );
		// WP merges in `body => null` as a default — the assertion is that we
		// don't send a real body, not that the key is absent from the args array.
		$this->assertEmpty( $this->captured['args']['body'] ?? null );
	}

	/**
	 * Connection failures from `wp_safe_remote_request` should surface as the
	 * SDK's `ApiConnectionException` so callers can catch them with the same
	 * exception hierarchy they already use for the SDK.
	 *
	 * @return void
	 */
	public function test_wp_error_response_throws_api_connection_exception() {
		$this->next_response = new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' );
		$client              = new WC_Stripe_SDK_WP_Http_Client();

		$this->expectException( \Stripe\Exception\ApiConnectionException::class );
		$this->expectExceptionMessageMatches( '/Failed to connect/' );

		$client->request(
			'get',
			'https://api.stripe.com/v1/customers',
			[ 'Authorization: Bearer sk_test_xyz' ],
			[],
			false
		);
	}

	/**
	 * Multipart file uploads aren't wired into this adapter yet — confirm we
	 * fail fast with the SDK's `UnexpectedValueException` instead of silently
	 * dropping the file.
	 *
	 * @return void
	 */
	public function test_file_upload_is_rejected() {
		$client = new WC_Stripe_SDK_WP_Http_Client();

		$this->expectException( \Stripe\Exception\UnexpectedValueException::class );

		$client->request(
			'post',
			'https://files.stripe.com/v1/files',
			[ 'Authorization: Bearer sk_test_xyz' ],
			[ 'file' => 'somefile' ],
			true
		);
	}
}
