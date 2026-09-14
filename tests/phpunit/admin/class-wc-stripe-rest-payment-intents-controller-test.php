<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Stripe_REST_Payment_Intents_Controller_Test extends WP_UnitTestCase {
	private const SINGLE_INTENT_ENDPOINT_URL = '/wc/v3/wc_stripe/payment_intents/pi_test_9876543210';
	private const ALL_INTENTS_ENDPOINT_URL   = '/wc/v3/wc_stripe/payment_intents';
	private const SEARCH_INTENTS_ENDPOINT_URL   = '/wc/v3/wc_stripe/payment_intents/search';

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

	public static function pre_http_request_mock_handler( bool $preempt, array $request_args, $url ) {
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
	private function send_request( string $url, array $params = [] ) {
		$request = new WP_REST_Request(
			WP_REST_Server::READABLE,
			$url
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

		$response = $this->send_request( self::SINGLE_INTENT_ENDPOINT_URL );

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

		$response = $this->send_request( self::SINGLE_INTENT_ENDPOINT_URL );

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

		$response = $this->send_request( self::SINGLE_INTENT_ENDPOINT_URL );

		$this->assertSame( 200, $response->get_status() );
	}

	public static function provide_single_intent_filtering_test_data(): array {
		$response_allowed_part = '"object": "payment_intent",
			"id": "pi_3TbL9RJlUF0dQbSB00q0FJS2",
			"amount": 2460,
			"amount_received": 2500,
			"currency": "usd",
			"status": "succeeded",
			"description": "Lorem ipsum",
			"latest_charge": {
				"balance_transaction": {
					"fee": 1000,
					"net": 2000,
					"currency": "usd"
				},
				"billing_details": {
					"address": {
						"city": "Bucuresti Sector 3",
						"country": "RO",
						"line1": "Bd. Octavian Goga nr. 4",
						"line2": "",
						"postal_code": "030982",
						"state": "B"
					},
					"email": "admin@example.com",
					"name": "Adrian Dobrescu",
					"phone": "+40722112945",
					"tax_id": null
				}
			}';
		$response_as_string    = '{
			' . $response_allowed_part . ',
			"amount_details": {
				"tip": {}
			},
			"payment_details": {
				"customer_reference": null,
				"order_reference": "in_1TbKCUJlUF0dQbSBCq67mKfR"
			},
			"payment_method": null,
			"payment_method_configuration_details": null,
			"payment_method_options": {
				"card": {
					"installments": null,
					"mandate_options": null,
					"network": null,
					"request_three_d_secure": "automatic"
				},
				"link": {
					"persistent_token": null
				}
			},
			"payment_method_types": [
				"card",
				"link"
			]
		}';
		return [
			[
				$response_as_string,
				'{' . $response_allowed_part . '}',
			],
		];
	}

	/**
	 * @dataProvider provide_single_intent_filtering_test_data
	*/
	public function test_single_intent_response_filtering( string $response_as_string, string $response_allowed_part ) {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$http_code_401_mock = function ( $pre, $parsed_args, $url ) use ( $response_as_string ) {
			return [
				'headers'  => [],
				'body'     => $response_as_string,
				'response' => [
					'code'    => 200,
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

		$response               = $this->send_request( self::SINGLE_INTENT_ENDPOINT_URL );
		$expected_response_data = json_decode( $response_allowed_part );

		remove_filter(
			'pre_http_request',
			$http_code_401_mock,
			10,
			3
		);

		$this->assertEquals( $expected_response_data, $response->data );
	}

	/** Send a malformed payment intent id. */
	public function test_malformed_payment_intent_id() {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber_id );

		$response = $this->send_request( self::SINGLE_INTENT_ENDPOINT_URL . '/../../other' );

		$this->assertSame( 404, $response->get_status() );
	}

	public static function provide_intent_list_malformed_param(): array {
		return [
			[ 'created', '' ],
			[ 'created', -1779802569 ],
			[ 'created', 'a1779802569' ],
			[
				'created',
				[
					0 => '1779802569',
				],
			],
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
			[ 'starting_after', 'xyz' ],
			[ 'ending_before', 'xyz' ],
			[ 'customer', 'xyz' ],
			[ 'customer_account', 'xyz' ],
		];
	}

	/**
	 * Create an admin user and send requests containing wrong format args.
	 *
	 * @dataProvider provide_intent_list_malformed_param
	 *
	 * @param string $param_name
	 * @param mixed $param_value
	*/
	public function test_intent_list_malformed_param( string $param_name, $param_value ) {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$response = $this->send_request( self::ALL_INTENTS_ENDPOINT_URL, [ $param_name => $param_value ] );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Create an admin user and send requests containing wrong format args.
	 *
	 * @param string $param_name
	 * @param mixed $param_value
	*/
	public function test_intent_list_with_both_starting_after_and_ending_before() {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$response = $this->send_request(
			self::ALL_INTENTS_ENDPOINT_URL,
			[
				'starting_after' => 'pi_test',
				'ending_before'  => 'pi_test2',
			]
		);

		$this->assertSame( 400, $response->get_status() );
	}

	public static function provide_intent_list_params(): array {
		return [
			[
				[ 'created' => 0 ],
			],
			[
				[
					'created'        => '1779802569',
					'starting_after' => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
				],
			],
			[
				[
					'created'       =>
						[
							'lt' => '1779802569',
						],
					'ending_before' => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
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
					'ending_before'    => 'pi_3TbL9RJlUF0dQbSB00q0FJS2',
				],
			],
			[
				[
					'created' =>
						[
							'lt' => '1779802821',
							'gt' => '1779802569',
						],
				],
			],
			[
				[
					'created' =>
						[
							'lte' => '1779802821',
							'gte' => '1779802569',
						],
				],
				[
					'created' =>
						[
							'lte' => '1779802821',
							'gte' => '0',
						],
				],
				[
					'created' =>
						[
							'lte' => '0',
							'gte' => '0',
						],
				],
			],
		];
	}

	/**
	 * Send requests containing valid parameters and check they are forwarded correctly to the Stripe API
	 * using a 'pre_http_request' hook.
	 *
	 * @dataProvider provide_intent_list_params
	*/
	public function test_pass_intent_list_params( array $rest_params ) {
		$controller = new WC_Stripe_REST_Payment_Intents_Controller();

		$reflection_class = new ReflectionClass( WC_Stripe_REST_Payment_Intents_Controller::class );
		$r_const          = $reflection_class->getReflectionConstant( 'STRIPE_LIST_EXPAND_PARAM' );

		$expand                = $r_const->getValue();
		$rest_params['expand'] = $expand;

		$request = new WP_REST_Request(
			WP_REST_Server::READABLE,
			self::ALL_INTENTS_ENDPOINT_URL,
		);

		foreach ( $rest_params as $rest_param_name => $rest_param_value ) {
			$request->set_param( $rest_param_name, $rest_param_value );
		}

		$passed_rest_params = $request->get_params();

		$pre_http_request_params = [];

		$this->mock_http_call();
		$http_stub = function ( $pre, $parsed_args, $url ) use ( &$pre_http_request_params ) {
				$url_components = parse_url( $url );

				parse_str( $url_components['query'], $pre_http_request_params['search_params'] );

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

		try {
			rest_get_server()->dispatch( $request );
		} finally {
			remove_filter( 'pre_http_request', $http_stub, 10, 3 );
		}

		$this->assertEquals( $rest_params, $passed_rest_params );
		$this->assertEquals(
			$rest_params,
			array_intersect_key( $pre_http_request_params['search_params'], $rest_params )
		);
		$test = array_diff_key( $pre_http_request_params['search_params'], $rest_params );

		if ( array_key_exists( 'limit', $rest_params ) ) {
			$this->assertEmpty( array_diff_key( $pre_http_request_params['search_params'], $rest_params ) );
		} else {
			// We default the `limit` argument when not supplied by the caller.
			$this->assertEquals(
				[ 'limit' => 10 ],
				array_diff_key( $pre_http_request_params['search_params'], $rest_params )
			);
		}
	}

	public static function provide_intent_list_filtering_test_data(): Generator {
		$test_data_directory = __DIR__ . '/stripe-api-test-response-payloads';

		$test_data_files = glob( $test_data_directory . '/*.json' );

		$test_data_groups = [];

		foreach ( $test_data_files as $test_data_file ) {
			$name = basename( $test_data_file );

			if ( ! preg_match( '/^(\d+)-/', $name, $matches ) ) {
				continue;
			}

			$test_data_groups[ $matches[1] ][] = $test_data_file;
		}

		ksort( $test_data_groups, SORT_NUMERIC );

		foreach ( $test_data_groups as $id => $files ) {
			$test_file     = null;
			$expected_file = null;

			foreach ( $files as $file ) {
				if ( str_contains( basename( $file ), 'expected' ) ) {
					$expected_file = $file;
				} else {
					$test_file = $file;
				}
			}

			$test_file_description     = str_replace( [ '-', '_', '.json' ], ' ', preg_replace( '/^[0-9]+/', '', basename( $test_file ) ) );
			$expected_file_description = str_replace( [ '-', '_', '.json' ], ' ', preg_replace( '/^[0-9]+/', '', basename( $expected_file ) ) );

			$test_description = 'Received: ' . ucfirst( trim( $test_file_description ) ) . PHP_EOL . 'Expected: ' . ucfirst( trim( $expected_file_description ) );

			yield "case {$test_description}" => [
				file_get_contents( $test_file ),
				file_get_contents( $expected_file ),
			];
		}
	}

	/**
	 * @dataProvider provide_intent_list_filtering_test_data
	*/
	public function test_intent_list_response_filtering( string $response_as_string, string $response_allowed_part ) {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$http_code_401_mock = function ( $pre, $parsed_args, $url ) use ( $response_as_string ) {
			return [
				'headers'  => [],
				'body'     => $response_as_string,
				'response' => [
					'code'    => 200,
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

		$response               = $this->send_request( self::ALL_INTENTS_ENDPOINT_URL );
		$expected_response_data = json_decode( $response_allowed_part );

		remove_filter(
			'pre_http_request',
			$http_code_401_mock,
			10,
			3
		);

		$this->assertEquals( $expected_response_data, $response->data );
	}

	public static function provide_intent_search_malformed_param(): array {
		return [
			[ 'query', '' ],
			[ 'page', '' ],
		];
	}

	/**
	 * Create an admin user and send requests containing wrong format args.
	 *
	 * @dataProvider provide_intent_search_malformed_param
	 *
	 * @param string $param_name
	 * @param mixed $param_value
	*/
	public function test_intent_search_malformed_param( string $param_name, $param_value ) {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$response = $this->send_request( self::SEARCH_INTENTS_ENDPOINT_URL, [ $param_name => $param_value ] );

		$this->assertSame( 400, $response->get_status() );
	}
}
