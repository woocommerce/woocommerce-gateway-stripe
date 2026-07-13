<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Stripe_REST_Payment_Intents_Controller_Test extends WP_UnitTestCase {
	const ENDPOINT_URL = '/wc/v3/wc_stripe/payment_intents';

	/** Initialise REST API, make WC_Stripe_REST_Payment_Intents_Controller instance available for testing*/
	public static function set_up_before_class() {
		parent::set_up_before_class();

		do_action( 'rest_api_init' );
	}

	public function teardown(): void {
		remove_filter(
			'pre_http_request',
			[ static::class, 'pre_http_request_mock_handler' ],
			10,
			3
		);
	}

	public static function pre_http_request_mock_handler( $preempt, $request_args, $url ) {
		if ( false === strpos( $url, 'payment_intents' ) ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => wp_json_encode(
				[
					'data'     => [],
					'has_more' => false,
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];
	}
	/** Mock stripe API calls to avoid making real HTTP requests. */
	protected function mock_http_call() {
		add_filter(
			'pre_http_request',
			[ static::class, 'pre_http_request_mock_handler' ],
			10,
			3
		);
	}
	/**
	 * Send a request to the REST server.
	 *
	 * If non-empty, adds the entries from $params to the request object.
	 */
	private function send_request( $params = [] ) {
		$request = new WP_REST_Request(
			WP_REST_Server::READABLE,
			self::ENDPOINT_URL
		);

		if ( $params ) {
			foreach ( $params as $param_name => $param_value ) {
				$request->set_param( $param_name, $param_value );
			}
		}

		$response = rest_get_server()->dispatch( $request );

		return $response;
	}

	/** Create a non-admin user, set it as current user and send an API request. */
	public function test_permission_check_denies_unauthorized_call() {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber_id );

		$response = $this->send_request();

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_wrong_stripe_api_key() {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$http_code_401_mock = function ( $pre, $parsed_args, $url ) {
			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'error' => [
							'code'    => 'invalid_request_error',
							'message' => 'Invalid API Key provided',
						],
					]
				),
				'response' => [
					'code'    => 401,
					'message' => 'OK',
				],
			];
		};

		add_filter(
			'pre_http_request',
			$http_code_401_mock,
			10,
			3
		);

		$response = $this->send_request();

		remove_filter(
			'pre_http_request',
			$http_code_401_mock,
			10,
			3
		);

		$this->assertSame( 401, $response->get_status() );
	}

	/** Create an admin user, set it as current user and send a API request. */
	public function test_permission_check_allows_authorized_call() {
		$this->mock_http_call();

		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$response = $this->send_request();

		$this->assertSame( 200, $response->get_status() );
	}

	public static function provide_created_param_wrong_format(): array {
		return [
			[
				'created',
				[
					'lt3' => '1779802569',
				],
			],
			[
				'created',
				[
					'lt'  => '1779802569',
					'gt3' => '1779802569',
				],
			],
		];
	}
	/**
	 * Create an admin user and send requests containing wrong format args.
	 *
	 * @dataProvider provide_created_param_wrong_format
	*/
	public function test_created_param_wrong_format( $param_name, $param_value ) {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$response = $this->send_request( [ $param_name => $param_value ] );

		$this->assertSame( 400, $response->get_status() );
	}

	public static function provide_rest_params(): array {
		return [
			[
				[
					'created'        =>
						[
							'lt' => '1779802569',
						],
					'starting_after' => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
					'ending_before'  => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
				],
			],
			[
				[
					'limit'            => 100,
					'customer'         => 'cus_sad8s6dasd',
					'customer_account' => 'cus_sad8s6dasdxsa123',
					'created'          =>
						[
							'lt' => '1779802569',
						],
					'starting_after'   => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
					'ending_before'    => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
				],
			],
		];
	}
	/**
	 * Send requests containing valid parameters and check they are forwarded correctly to the Stripe API
	 * using a 'pre_http_request' hook.
	 *
	 * @dataProvider provide_rest_params
	*/
	public function test_pass_rest_params( $rest_params ) {
		$controller = new WC_Stripe_REST_Payment_Intents_Controller();

		$request = new WP_REST_Request(
			WP_REST_Server::READABLE,
			self::ENDPOINT_URL
		);

		foreach ( $rest_params as $rest_param_name => $rest_param_value ) {
			$request->set_param( $rest_param_name, $rest_param_value );
		}

		$passed_rest_params = $controller->build_http_query_array_from_request( $request );

		$pre_http_request = [];

		$this->mock_http_call();
		$http_stub = function ( $pre, $parsed_args, $url ) use ( &$pre_http_request ) {
				$url_components = parse_url( $url );

				parse_str( $url_components['query'], $pre_http_request['search_params'] );

				return $pre;
		};
		add_filter(
			'pre_http_request',
			$http_stub,
			10,
			3
		);

		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		rest_get_server()->dispatch( $request );

		remove_filter(
			'pre_http_request',
			$http_stub,
			10,
			3
		);

		$this->assertEquals( $rest_params, $passed_rest_params );
		$this->assertEquals(
			$rest_params,
			array_intersect_key( $pre_http_request['search_params'], $rest_params )
		);
	}
}
