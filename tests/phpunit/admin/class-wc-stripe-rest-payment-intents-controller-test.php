<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Stripe_REST_Payment_Intents_Controller_Test extends WP_UnitTestCase {
	const ENDPOINT_URL = '/wc/v3/wc_stripe/payment_intents';

	/** Initialise REST API */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		do_action( 'rest_api_init' );
	}

	protected function mock_http_call() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) {
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
			},
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

	/** Test that the REST server rejects requests from non-admin users */
	public function test_permission_check_denies_unauthorized_call() {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber_id );

		$response = $this->send_request();

		$this->assertSame( 403, $response->get_status() );
	}

	/** Test that the REST server allows requests from admin users */
	public function test_permission_check_allows_authorized_call() {
		$this->mock_http_call();

		wp_set_current_user( 1 );

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
	 * Test that requests containing malformed created field are rejected.
	 * @dataProvider provide_created_param_wrong_format
	*/
	public function test_created_param_wrong_format( $param_name, $param_value ) {
		wp_set_current_user( 1 );

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
	 * Test that the received parameters are correctly forwarded to Stripe API.
	 *
	 * @dataProvider provide_rest_params
	*/
	public function test_pass_rest_params( $rest_params ) {
		static $controller = null;

		if ( is_null( $controller ) ) {
			$controller = new WC_Stripe_REST_Payment_Intents_Controller();
		}

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
		add_filter(
			'pre_http_request',
			function ( $pre, $parsed_args, $url ) use ( &$pre_http_request ) {
				$url_components = parse_url( $url );

				parse_str( $url_components['query'], $pre_http_request['search_params'] );

				return $pre;
			},
			10,
			3
		);

		wp_set_current_user( 1 );
		rest_get_server()->dispatch( $request );

		$this->assertEquals( $rest_params, $passed_rest_params );
		$this->assertEquals(
			$rest_params,
			array_intersect_key( $pre_http_request['search_params'], $rest_params )
		);
	}
}
