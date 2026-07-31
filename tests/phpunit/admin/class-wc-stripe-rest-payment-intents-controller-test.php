<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Stripe_REST_Payment_Intents_Controller_Test extends WP_UnitTestCase {
	const ENDPOINT_URL = '/wc/v3/wc_stripe/payment_intents/pi_3TtPY4JlUF0dQbSB0Jb3hqkX';

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
}
