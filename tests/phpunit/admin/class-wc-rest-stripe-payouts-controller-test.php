<?php
/**
 * Class WC_REST_Stripe_Payouts_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_REST_Stripe_Payouts_Controller_Test extends WP_UnitTestCase {

	const BALANCE_ROUTE = '/wc/v3/wc_stripe/payouts/balance';
	const PAYOUTS_ROUTE = '/wc/v3/wc_stripe/payouts';

	/**
	 * Per-test counter of intercepted HTTP requests.
	 *
	 * @var int
	 */
	private $http_request_count = 0;

	/**
	 * The last URL passed to the intercepted HTTP request.
	 *
	 * @var string|null
	 */
	private $last_request_url = null;

	private $pre_http_mocks = [];

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-base-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-payouts-controller.php';
	}

	public function set_up() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::set_up();

		$this->http_request_count = 0;
		$this->last_request_url   = null;
		$this->pre_http_mocks     = [];

		// Ensure route is registered.
		do_action( 'rest_api_init' );

		// Clear the balance cache between tests.
		WC_Stripe_Database_Cache::delete( WC_REST_Stripe_Payouts_Controller::get_balance_cache_key() );
	}

	public function tear_down() {
		foreach ( $this->pre_http_mocks as $pre_http_mock ) {
			remove_filter( 'pre_http_request', $pre_http_mock, 10 );
		}
		$this->pre_http_mocks = [];
		parent::tear_down();
	}

	/**
	 * Installs a pre_http_request filter that returns the given response body
	 * and tracks call counts / last URL.
	 *
	 * @param string         $stripe_url The URL to return the response for.
	 * @param array|stdClass $body       The body to return as JSON.
	 * @param int            $status     HTTP status code.
	 */
	private function mock_stripe_http( $stripe_url, $body, $status = 200 ) {
		$pre_http_mock = function ( $preempt, $parsed_args, $url ) use ( $stripe_url, $body, $status ) {
			$this->http_request_count++;
			$this->last_request_url = $url;

			if ( ! str_starts_with( $url, $stripe_url ) ) {
				return $preempt;
			}

			return [
				'response' => [
					'code'    => $status,
					'message' => 'OK',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode( $body ),
			];
		};
		add_filter( 'pre_http_request', $pre_http_mock, 10, 3 );
		$this->pre_http_mocks[] = $pre_http_mock;
	}

	public function test_permission_check_denies_non_admin() {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$controller = new WC_REST_Stripe_Payouts_Controller();
		$this->assertFalse( $controller->check_permission() );
	}

	public function test_permission_check_allows_admin() {
		wp_set_current_user( 1 );

		$controller = new WC_REST_Stripe_Payouts_Controller();
		$this->assertTrue( $controller->check_permission() );
	}

	public function test_get_balance_happy_path() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/balance',
			[
				'available'         => [
					[
						'amount'   => 1500,
						'currency' => 'usd',
					],
				],
				'pending'           => [
					[
						'amount'   => 500,
						'currency' => 'usd',
					],
				],
				'instant_available' => [],
				'livemode'          => false,
			]
		);

		$request  = new WP_REST_Request( 'GET', self::BALANCE_ROUTE );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		// Stripe responses arrive as stdClass objects via json_decode.
		$this->assertSame( 1500, $data['available'][0]->amount );
		$this->assertSame( 'usd', $data['available'][0]->currency );
		$this->assertSame( 500, $data['pending'][0]->amount );
		$this->assertFalse( $data['livemode'] );
	}

	public function test_get_balance_uses_cache_on_second_call() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/balance',
			[
				'available' => [
					[
						'amount'   => 1500,
						'currency' => 'usd',
					],
				],
				'pending'   => [],
			]
		);

		rest_do_request( new WP_REST_Request( 'GET', self::BALANCE_ROUTE ) );
		rest_do_request( new WP_REST_Request( 'GET', self::BALANCE_ROUTE ) );

		$this->assertSame( 1, $this->http_request_count, 'Second balance call should hit the cache.' );
	}

	public function test_get_balance_surfaces_stripe_error() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/balance',
			[
				'error' => [
					'message' => 'Missing API key',
					'type'    => 'invalid_request_error',
				],
			],
			401
		);

		$response = rest_do_request( new WP_REST_Request( 'GET', self::BALANCE_ROUTE ) );

		$this->assertSame( 502, $response->get_status() );
	}

	public function test_get_payouts_happy_path() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/payouts',
			[
				'data'     => [
					[
						'id'           => 'po_1',
						'amount'       => 2500,
						'currency'     => 'usd',
						'status'       => 'paid',
						'arrival_date' => 1700000000,
					],
				],
				'has_more' => false,
			]
		);

		$request  = new WP_REST_Request( 'GET', self::PAYOUTS_ROUTE );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data['data'] );
		$this->assertSame( 'po_1', $data['data'][0]->id );
		$this->assertFalse( $data['has_more'] );
		$this->assertStringContainsString( 'limit=25', $this->last_request_url );
	}

	public function test_get_payouts_forwards_starting_after() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/payouts',
			[
				'data'     => [],
				'has_more' => false,
			]
		);

		$request = new WP_REST_Request( 'GET', self::PAYOUTS_ROUTE );
		$request->set_query_params( [ 'starting_after' => 'po_cursor' ] );
		rest_do_request( $request );

		$this->assertStringContainsString( 'starting_after=po_cursor', $this->last_request_url );
	}

	public function test_get_payouts_forwards_status() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/payouts',
			[
				'data'     => [],
				'has_more' => false,
			]
		);

		$request = new WP_REST_Request( 'GET', self::PAYOUTS_ROUTE );
		$request->set_query_params( [ 'status' => 'pending' ] );
		rest_do_request( $request );

		$this->assertStringContainsString( 'status=pending', $this->last_request_url );
	}

	public function test_get_payouts_rejects_over_max_limit() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/payouts',
			[
				'data'     => [],
				'has_more' => false,
			]
		);

		$request = new WP_REST_Request( 'GET', self::PAYOUTS_ROUTE );
		$request->set_query_params( [ 'limit' => 999 ] );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 0, $this->http_request_count );
	}

	public function test_get_payouts_rejects_invalid_status() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/payouts',
			[
				'data'     => [],
				'has_more' => false,
			]
		);

		$request = new WP_REST_Request( 'GET', self::PAYOUTS_ROUTE );
		$request->set_query_params( [ 'status' => 'fake_status' ] );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_get_payouts_surfaces_stripe_error() {
		wp_set_current_user( 1 );
		$this->mock_stripe_http(
			'https://api.stripe.com/v1/payouts',
			[
				'error' => [
					'message' => 'Nope',
					'type'    => 'invalid_request_error',
				],
			],
			400
		);

		$response = rest_do_request( new WP_REST_Request( 'GET', self::PAYOUTS_ROUTE ) );
		$this->assertSame( 502, $response->get_status() );
	}
}
