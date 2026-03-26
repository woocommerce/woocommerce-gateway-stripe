<?php

/**
 * These tests make assertions against the WC_Stripe_Checkout_Sessions_Controller class.
 */
class WC_Stripe_Checkout_Sessions_Controller_Test extends WP_UnitTestCase {
	/**
	 * Test that hooks are initialized correctly.
	 */
	public function test_init_hooks(): void {
		$controller = new WC_Stripe_Checkout_Sessions_Controller();
		$controller->init_hooks();

		$this->assertTrue(
			(bool) has_action(
				'wc_ajax_wc_stripe_create_checkout_session',
				[ $controller, 'create_checkout_session' ]
			)
		);
	}

	/**
	 * Tests for the `create_checkout_session` method.
	 *
	 * @param bool        $is_valid_nonce             Whether the AJAX nonce is valid.
	 * @param bool        $is_cart_empty              Whether the cart is empty.
	 * @param array       $customer_data              The customer billing data to set.
	 * @param object|null $checkout_session_response  The mocked response from the Stripe API.
	 * @param object      $expected_response          The expected AJAX response, if any.
	 * @return void
	 * @dataProvider provide_test_create_checkout_session
	 */
	public function test_create_checkout_session(
		bool $is_valid_nonce,
		array $customer_data,
		bool $is_cart_empty,
		?object $checkout_session_response,
		object $expected_response
	): void {
		Ajax_Test_Helper::init_hooks();

		// Set up a logged-in user with billing details.
		WC()->customer = new \WC_Customer( 1 );

		foreach ( $customer_data as $key => $value ) {
			update_user_meta( 1, $key, $value );
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

		$controller = new WC_Stripe_Checkout_Sessions_Controller();

		try {
			ob_start();
			$controller->create_checkout_session();
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

		$mocked_secret = 'cs_test_1234567890abcdef';

		$checkout_session_error = (object) [
			'error' => (object) [
				'message' => $mocked_error_message,
			],
		];

		$checkout_session_missing_secret = (object) [];

		$checkout_session_success = (object) [
			'client_secret' => $mocked_secret,
		];

		return [
			'invalid nonce'            => [
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
			'missing customer data'    => [
				'is valid nonce'            => true,
				'customer data'             => [],
				'is cart empty'             => true,
				'checkout session response' => null,
				'expected response'         => (object) [
					'success' => false,
					'data'    => (object) [
						'message' => 'Unable to create or retrieve Stripe customer.',
					],
				],
			],
			'cart is empty'            => [
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
			'error creating session'   => [
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
			'client secret is missing' => [
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
			'successful creation'      => [
				'is valid nonce'            => true,
				'customer data'             => $customer_data,
				'is cart empty'             => false,
				'checkout session response' => $checkout_session_success,
				'expected response'         => (object) [
					'success' => true,
					'data'    => (object) [
						'client_secret' => $mocked_secret,
					],
				],
			],
		];
	}
}
