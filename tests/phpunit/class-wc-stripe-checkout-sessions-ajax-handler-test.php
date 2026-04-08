<?php

/**
 * These tests make assertions against the WC_Stripe_Checkout_Sessions_Ajax_Handler class.
 */
class WC_Stripe_Checkout_Sessions_Ajax_Handler_Test extends WP_UnitTestCase {
	/**
	 * Test that hooks are initialized correctly.
	 */
	public function test_init_hooks(): void {
		$ajax_handler = new WC_Stripe_Checkout_Sessions_Ajax_Handler();
		$ajax_handler->init_hooks();

		$this->assertTrue(
			(bool) has_action(
				'wc_ajax_wc_stripe_create_checkout_session',
				[ $ajax_handler, 'create_checkout_session' ]
			)
		);
		$this->assertTrue(
			(bool) has_action(
				'wc_ajax_wc_stripe_update_checkout_session',
				[ $ajax_handler, 'update_checkout_session' ]
			)
		);
	}

	/**
	 * Tests for the `create_checkout_session` method.
	 *
	 * @param bool        $user_is_logged_in          Whether the user is logged in or not.
	 * @param bool        $is_valid_nonce             Whether the AJAX nonce is valid.
	 * @param array       $customer_data              The customer billing data to set.
	 * @param bool        $is_cart_empty              Whether the cart is empty.
	 * @param object|null $checkout_session_response  The mocked response from the Stripe API.
	 * @param object      $expected_response          The expected AJAX response, if any.
	 * @return void
	 * @dataProvider provide_test_create_checkout_session
	 */
	public function test_create_checkout_session(
		bool $user_is_logged_in,
		bool $is_valid_nonce,
		array $customer_data,
		bool $is_cart_empty,
		?object $checkout_session_response,
		object $expected_response
	): void {
		Ajax_Test_Helper::init_hooks();

		if ( $user_is_logged_in ) {
			// Set up a logged-in user with billing details.
			wp_set_current_user( 1 );
			WC()->customer = new \WC_Customer( 1 );

			foreach ( $customer_data as $key => $value ) {
				update_user_meta( 1, $key, $value );
			}
		}

		// Set up the cart contents.
		WC()->session->init();
		WC()->cart->empty_cart();

		if ( ! $is_cart_empty ) {
			$product = WC_Helper_Product::create_simple_product();
			$product->save();

			WC()->cart->add_to_cart( $product->get_id(), 1 );
		}

		// Mock response from Stripe API.
		$test_request = function ( $return_value, $parsed_args, $url ) use ( $checkout_session_response ) {
			// Mock the customer retrieval response.
			if ( strpos( $url, '/v1/customers' ) !== false ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode( (object) [ 'id' => 'cus_123' ] ),
				];
			}

			if ( 'https://api.stripe.com/v1/checkout/sessions' === $url ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode( $checkout_session_response ),
				];
			}

			return $return_value;
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		// Set up the AJAX request nonce.
		$_REQUEST['_ajax_nonce'] = $is_valid_nonce ? wp_create_nonce( 'wc_stripe_create_checkout_session_nonce' ) : 'invalid_nonce_value';

		$ajax_handler = new WC_Stripe_Checkout_Sessions_Ajax_Handler();

		try {
			ob_start();
			$ajax_handler->create_checkout_session();
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			throw $e;
		} finally {
			// Clean up.
			remove_filter( 'pre_http_request', $test_request, 10, 3 );
			Ajax_Test_Helper::remove_hooks();
		}

		$response = json_decode( $output );
		$this->assertEquals( (object) $expected_response, $response );
	}

	/**
	 * Checkout Sessions should send one line item whose amount matches the full cart total (Stripe minor units).
	 */
	public function test_create_checkout_session_sends_single_line_item_matching_cart_total(): void {
		Ajax_Test_Helper::init_hooks();

		WC()->customer = new \WC_Customer( 1 );

		$customer_data = [
			'billing_first_name' => 'John',
			'billing_last_name'  => 'Doe',
			'billing_address_1'  => '123 Main St',
			'billing_city'       => 'New York',
			'billing_state'      => 'NY',
			'billing_postcode'   => '10001',
			'billing_country'    => 'US',
			'billing_email'      => 'john@example.com',
		];
		foreach ( $customer_data as $key => $value ) {
			update_user_meta( 1, $key, $value );
		}

		WC()->session->init();
		WC()->cart->empty_cart();

		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 12.34 ] );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 2 );

		$captured_request = null;
		$capture_body     = static function ( $request, $api ) use ( &$captured_request ) {
			if ( 'checkout/sessions' === $api ) {
				$captured_request = $request;
			}
			return $request;
		};
		add_filter( 'wc_stripe_request_body', $capture_body, 10, 2 );

		$test_request = static function ( $return_value, $parsed_args, $url ) {
			if ( strpos( $url, '/v1/customers' ) !== false ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode( (object) [ 'id' => 'cus_123' ] ),
				];
			}

			if ( 'https://api.stripe.com/v1/checkout/sessions' === $url ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode( (object) [ 'client_secret' => 'cs_test_secret' ] ),
				];
			}

			return $return_value;
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_create_checkout_session_nonce' );

		$ajax_handler = new WC_Stripe_Checkout_Sessions_Ajax_Handler();

		try {
			ob_start();
			$ajax_handler->create_checkout_session();
			ob_end_clean();
		} finally {
			remove_filter( 'pre_http_request', $test_request, 10, 3 );
			remove_filter( 'wc_stripe_request_body', $capture_body, 10, 2 );
			Ajax_Test_Helper::remove_hooks();
		}

		$this->assertIsArray( $captured_request );
		$this->assertArrayHasKey( 'line_items', $captured_request );
		$this->assertCount( 1, $captured_request['line_items'] );

		$expected_amount = WC_Stripe_Helper::get_stripe_amount( WC()->cart->get_total( 'edit' ), get_woocommerce_currency() );
		$line_item       = $captured_request['line_items'][0];

		$this->assertSame( __( 'Cart total', 'woocommerce-gateway-stripe' ), $line_item['price_data']['product_data']['name'] );
		$this->assertSame( $expected_amount, $line_item['price_data']['unit_amount'] );
		$this->assertSame( 1, $line_item['quantity'] );
	}

	/**
	 * Tests for the `update_checkout_session` method.
	 *
	 * @param bool        $is_valid_nonce             Whether the AJAX nonce is valid.
	 * @param bool        $set_checkout_session_id    Whether `checkout_session_id` is present in $_POST.
	 * @param string      $checkout_session_id        Value for `checkout_session_id` when set.
	 * @param object|null $checkout_session_response  Mocked Stripe API response body, or null when the API should not be called.
	 * @param object      $expected_response          Expected JSON-decoded AJAX response.
	 * @return void
	 * @dataProvider provide_test_update_checkout_session
	 */
	public function test_update_checkout_session(
		bool $is_valid_nonce,
		bool $set_checkout_session_id,
		string $checkout_session_id,
		?object $checkout_session_response,
		object $expected_response
	): void {
		Ajax_Test_Helper::init_hooks();

		$post_before = $_POST;

		WC()->session->init();
		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$test_request = null;
		if ( null !== $checkout_session_response ) {
			$test_request = function ( $return_value, $parsed_args, $url ) use ( $checkout_session_id, $checkout_session_response ) {
				$expected_path = 'checkout/sessions/' . $checkout_session_id;
				if ( is_string( $url ) && strpos( $url, $expected_path ) !== false ) {
					return [
						'response' => 200,
						'headers'  => [ 'Content-Type' => 'application/json' ],
						'body'     => wp_json_encode( $checkout_session_response ),
					];
				}

				return $return_value;
			};
			add_filter( 'pre_http_request', $test_request, 10, 3 );
		}

		$_REQUEST['_ajax_nonce'] = $is_valid_nonce ? wp_create_nonce( 'wc_stripe_update_checkout_session_nonce' ) : 'invalid_nonce_value';

		if ( $set_checkout_session_id ) {
			$_POST['checkout_session_id'] = $checkout_session_id;
		} else {
			unset( $_POST['checkout_session_id'] );
		}

		$ajax_handler = new WC_Stripe_Checkout_Sessions_Ajax_Handler();

		try {
			ob_start();
			$ajax_handler->update_checkout_session();
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			throw $e;
		} finally {
			$_POST = $post_before;
			if ( null !== $test_request ) {
				remove_filter( 'pre_http_request', $test_request, 10, 3 );
			}
			Ajax_Test_Helper::remove_hooks();
		}

		$response = json_decode( $output );
		$this->assertEquals( (object) $expected_response, $response );
	}

	/**
	 * Data provider for `test_update_checkout_session`.
	 *
	 * @return array
	 */
	public function provide_test_update_checkout_session(): array {
		$mocked_error_message = 'Simulated update error for testing.';

		$checkout_session_error = (object) [
			'error' => (object) [
				'message' => $mocked_error_message,
			],
		];

		$checkout_session_success = (object) [
			'id' => 'cs_test_updated',
		];

		return [
			'invalid nonce'                => [
				'is valid nonce'            => false,
				'set checkout session id'   => true,
				'checkout session id'       => 'cs_test_123',
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => "We're not able to process this request. Please refresh the page and try again.",
					],
				],
			],
			'checkout session ID not sent' => [
				'is valid nonce'            => true,
				'set checkout session id'   => false,
				'checkout session id'       => '',
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => 'Checkout session ID is required.',
					],
				],
			],
			'checkout session ID empty'    => [
				'is valid nonce'            => true,
				'set checkout session id'   => true,
				'checkout session id'       => '',
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => 'Checkout session ID is required.',
					],
				],
			],
			'error updating session'       => [
				'is valid nonce'            => true,
				'set checkout session id'   => true,
				'checkout session id'       => 'cs_test_123',
				'checkout session response' => $checkout_session_error,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => $mocked_error_message,
					],
				],
			],
			'successful update'            => [
				'is valid nonce'            => true,
				'set checkout session id'   => true,
				'checkout session id'       => 'cs_test_123',
				'checkout session response' => $checkout_session_success,
				'expected response'         => (object) [
					'success' => true,
					'data'    => (object) [
						'result' => 'success',
					],
				],
			],
		];
	}

	/**
	 * Data provider for `test_create_checkout_session`.
	 *
	 * @return array
	 */
	public function provide_test_create_checkout_session(): array {
		$customer_data = [
			'billing_first_name' => 'John',
			'billing_last_name'  => 'Doe',
			'billing_address_1'  => '123 Main St',
			'billing_city'       => 'New York',
			'billing_state'      => 'NY',
			'billing_postcode'   => '10001',
			'billing_country'    => 'US',
		];

		$mocked_error_message = 'Simulated error for testing.';

		$mocked_secret     = 'cs_test_1234567890abcdef';
		$mocked_session_id = 'cs_test_session_id';

		$checkout_session_error = (object) [
			'error' => (object) [
				'message' => $mocked_error_message,
			],
		];

		$checkout_session_missing_secret = (object) [];

		$checkout_session_success = (object) [
			'client_secret' => $mocked_secret,
			'id'            => $mocked_session_id,
		];

		return [
			'invalid nonce'               => [
				'user is logged-in'         => true,
				'is valid nonce'            => false,
				'customer data'             => [],
				'is cart empty'             => true,
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => "We're not able to process this request. Please refresh the page and try again.",
					],
				],
			],
			'missing customer data'       => [
				'user is logged-in'         => true,
				'is valid nonce'            => true,
				'customer data'             => [],
				'is cart empty'             => false,
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => 'Unable to create or retrieve Stripe customer.',
					],
				],
			],
			'cart is empty'               => [
				'user is logged-in'         => true,
				'is valid nonce'            => true,
				'customer data'             => $customer_data,
				'is cart empty'             => true,
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => 'Your cart is currently empty.',
					],
				],
			],
			'error creating session'      => [
				'user is logged-in'         => true,
				'is valid nonce'            => true,
				'customer data'             => $customer_data,
				'is cart empty'             => false,
				'checkout session response' => $checkout_session_error,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => $mocked_error_message,
					],
				],
			],
			'client secret is missing'    => [
				'user is logged-in'         => true,
				'is valid nonce'            => true,
				'customer data'             => $customer_data,
				'is cart empty'             => false,
				'checkout session response' => $checkout_session_missing_secret,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => 'Unable to create Stripe Checkout Session.',
					],
				],
			],
			'successful creation'         => [
				'user is logged-in'         => true,
				'is valid nonce'            => true,
				'customer data'             => $customer_data,
				'is cart empty'             => false,
				'checkout session response' => $checkout_session_success,
				'expected response'         => (object) [
					'success' => true,
					'data'    => (object) [
						'client_secret' => $mocked_secret,
						'session_id'    => $mocked_session_id,
					],
				],
			],
			'successful creation (guest)' => [
				'user is logged-in'         => false,
				'is valid nonce'            => true,
				'customer data'             => $customer_data,
				'is cart empty'             => false,
				'checkout session response' => $checkout_session_success,
				'expected response'         => (object) [
					'success' => true,
					'data'    => (object) [
						'client_secret' => $mocked_secret,
						'session_id'    => $mocked_session_id,
					],
				],
			],
		];
	}
}
