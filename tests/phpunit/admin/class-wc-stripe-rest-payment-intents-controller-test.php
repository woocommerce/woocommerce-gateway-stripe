<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller_Test
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Stripe_REST_Payment_Intents_Controller_Test extends WP_UnitTestCase {
	private const SINGLE_INTENT_ENDPOINT_URL = '/wc/v3/wc_stripe/payment_intents/pi_test_9876543210';

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
	private function send_request( $params = [], $url = self::SINGLE_INTENT_ENDPOINT_URL ) {
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

	public static function provide_filtering_test_data(): array {
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
				},
				"payment_method_details": {
					"card": {
						"amount_authorized": 7900,
						"authorization_code": "747254",
						"brand": "visa",
						"checks": {
							"address_line1_check": "pass",
							"address_postal_code_check": "pass",
							"cvc_check": "pass"
						}
					},
					"type": "card"
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
	 * @dataProvider provide_filtering_test_data
	*/
	public function test_response_filtering( $response_as_string, $response_allowed_part ) {
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

		$response               = $this->send_request();
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

		$response = $this->send_request( [], self::SINGLE_INTENT_ENDPOINT_URL . '/../../other' );

		$this->assertSame( 404, $response->get_status() );
	}
}
