<?php

namespace WooCommerce\Stripe\Tests;

/**
 * These tests make assertions against the class WC_Stripe_Customer
 *
 * Class WC_Stripe_Customer_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Customer
 */
class WC_Stripe_Customer_Test extends \WP_UnitTestCase {

	public function provide_test_validate_create_customer_request_cases(): array {
		return [
			'all fields present and required, no overrides' => [
				'billing_fields'             => [],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
			],
			'all required fields present with Woo-level overrides to not require state and postcode' => [
				'billing_fields'             => [
					'state'    => '',
					'postcode' => '',
				],
				'woo_billing_fields'         => [
					'billing_state'    => false,
					'billing_postcode' => false,
				],
				'stripe_billing_fields'      => null,
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
			],
			'all required fields present with Stripe-level overrides to not require state and postcode' => [
				'billing_fields'             => [
					'state'    => '',
					'postcode' => '',
				],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => [
					'state'       => false,
					'postal_code' => false,
				],
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
			],
			'email is empty string'             => [
				'billing_fields'             => [ 'email' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
			],
			'email is whitespace string'        => [
				'billing_fields'             => [ 'email' => '   	  ' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
			],
			'name is null'                      => [
				'billing_fields'             => [
					'first_name' => null,
					'last_name'  => '',
				],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: name',
				'expected_exception_string'  => 'Missing required customer field: name',
			],
			'address line1 is empty string'     => [
				'billing_fields'             => [ 'address_1' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: address->line1',
				'expected_exception_string'  => 'Missing required customer field: address->line1',
			],
			'address city is empty string'      => [
				'billing_fields'             => [ 'city' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: address->city',
				'expected_exception_string'  => 'Missing required customer field: address->city',
			],
			'address city is whitespace string' => [
				'billing_fields'             => [ 'city' => '    ' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: address->city',
				'expected_exception_string'  => 'Missing required customer field: address->city',
			],
			'address country is empty string'   => [
				'billing_fields'             => [ 'country' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: address->country',
				'expected_exception_string'  => 'Missing required customer field: address->country',
			],
			'add payment method page with boolean, all fields present and required, no overrides' => [
				'billing_fields'             => [], // only email is required
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
				'current_context'            => true,
			],
			'add payment method page with context, all fields present and required, no overrides' => [
				'billing_fields'             => [], // only email is required
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
				'current_context'            => \WC_Stripe_Customer::CUSTOMER_CONTEXT_ADD_PAYMENT_METHOD,
			],
			'add payment method page with boolean, email is empty string' => [
				'billing_fields'             => [ 'email' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
				'current_context'            => true,
			],
			'add payment method page with context, email is empty string' => [
				'billing_fields'             => [ 'email' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
				'current_context'            => \WC_Stripe_Customer::CUSTOMER_CONTEXT_ADD_PAYMENT_METHOD,
			],
			'pay for order page, only email present and required, no overrides' => [
				'billing_fields'             => [], // only email is required
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
				'current_context'            => \WC_Stripe_Customer::CUSTOMER_CONTEXT_PAY_FOR_ORDER,
			],
			'pay for order page, only email is empty string' => [
				'billing_fields'             => [ 'email' => '' ],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
				'current_context'            => \WC_Stripe_Customer::CUSTOMER_CONTEXT_PAY_FOR_ORDER,
			],
			'all fields present and required, no overrides, context is false' => [
				'billing_fields'             => [],
				'woo_billing_fields'         => null,
				'stripe_billing_fields'      => null,
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
				'current_context'            => false,
			],
		];
	}

	/**
	 * @dataProvider provide_test_validate_create_customer_request_cases
	 */
	public function test_validate_create_customer_request(
		array $billing_fields = [],
		?array $woo_billing_fields = null,
		?array $stripe_billing_fields = null,
		?string $expected_exception_message = null,
		?string $expected_exception_string = null,
		$current_context = null
	) {
		if ( true === $current_context || in_array( $current_context, \WC_Stripe_Customer::MINIMAL_BILLING_DETAILS_CONTEXTS, true ) ) {
			$default_billing_data = [
				'email'      => 'test@example.com',
				'first_name' => '',
				'last_name'  => '',
				'address_1'  => '',
				'city'       => '',
				'state'      => '',
				'postcode'   => '',
				'country'    => '',
			];
		} else {
			$default_billing_data = [
				'email'      => 'test@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'address_1'  => '123 Main St',
				'city'       => 'Anytown',
				'state'      => 'CA',
				'postcode'   => '12345',
				'country'    => 'US',
			];
		}

		$billing_data = wp_parse_args( $billing_fields, $default_billing_data );

		$woo_billing_fields_required = wp_parse_args(
			$woo_billing_fields ?? [],
			[
				'billing_email'      => true,
				'billing_first_name' => true,
				'billing_last_name'  => true,
				'billing_address_1'  => true,
				'billing_city'       => true,
				'billing_country'    => true,
				'billing_state'      => true,
				'billing_postcode'   => true,
			]
		);

		// Provide a default set of fields so these tests are not dependent on default WooCommerce configuration.
		$woo_checkout_fields_filter = function ( $checkout_fields ) use ( $woo_billing_fields_required ) {
			if ( ! isset( $checkout_fields['billing'] ) ) {
				return $checkout_fields;
			}

			foreach ( $woo_billing_fields_required as $field => $required ) {
				if ( ! isset( $checkout_fields['billing'][ $field ] ) ) {
					$checkout_fields['billing'][ $field ] = [
						'required' => $required,
					];
				} else {
					$checkout_fields['billing'][ $field ]['required'] = $required;
				}
			}

			return $checkout_fields;
		};
		add_filter( 'woocommerce_checkout_fields', $woo_checkout_fields_filter, 10, 1 );

		$stripe_billing_fields_filter = null;
		if ( null !== $stripe_billing_fields ) {
			$stripe_billing_fields_filter = function ( $required_fields ) use ( $stripe_billing_fields ) {
				foreach ( $stripe_billing_fields as $field => $required ) {
					if ( isset( $required_fields[ $field ] ) ) {
						$required_fields[ $field ] = $required;
					} elseif ( isset( $required_fields['address'][ $field ] ) ) {
						$required_fields['address'][ $field ] = $required;
					}
				}

				return $required_fields;
			};
			add_filter( 'wc_stripe_create_customer_required_fields', $stripe_billing_fields_filter, 10, 1 );
		}

		$mock_order = $this->getMockBuilder( \WC_Order::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'get_billing_address_1',
					'get_billing_city',
					'get_billing_country',
					'get_billing_email',
					'get_billing_first_name',
					'get_billing_last_name',
					'get_billing_postcode',
					'get_billing_state',
				]
			)
			->getMock();

		$mock_order->method( 'get_billing_address_1' )->willReturn( $billing_data['address_1'] );
		$mock_order->method( 'get_billing_city' )->willReturn( $billing_data['city'] );
		$mock_order->method( 'get_billing_country' )->willReturn( $billing_data['country'] );
		$mock_order->method( 'get_billing_email' )->willReturn( $billing_data['email'] );
		$mock_order->method( 'get_billing_first_name' )->willReturn( $billing_data['first_name'] );
		$mock_order->method( 'get_billing_last_name' )->willReturn( $billing_data['last_name'] );
		$mock_order->method( 'get_billing_postcode' )->willReturn( $billing_data['postcode'] );
		$mock_order->method( 'get_billing_state' )->willReturn( $billing_data['state'] );

		$args = [];
		$customer = new \WC_Stripe_Customer();

		$was_exception_thrown = false;

		$mock_customer_search_call = null;

		if ( ! empty( $billing_data['email'] ) && ( ! empty( $billing_data['first_name'] ) || ! empty( $billing_data['last_name'] ) ) ) {
			$mock_customer_search_call = function ( $return_value, $parsed_args, $url ) {
				if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/customers/search' ) ) {
					return $return_value;
				}

				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode(
						[
							'data' => [],
						]
					),
				];
			};
			add_filter( 'pre_http_request', $mock_customer_search_call, 10, 3 );
		}

		$mock_create_customer_call = null;
		if ( null === $expected_exception_message ) {
			$mock_create_customer_call = function ( $return_value, $parsed_args, $url ) {
				if ( 'https://api.stripe.com/v1/customers' !== $url ) {
					return $return_value;
				}

				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode(
						[
							'id' => 'cus_123',
						]
					),
				];
			};
			add_filter( 'pre_http_request', $mock_create_customer_call, 10, 3 );
		}

		try {
			$customer->create_customer( $args, $current_context, $mock_order );
		} catch ( \WC_Stripe_Exception $stripe_exception ) {
			$was_exception_thrown = true;

			if ( null === $expected_exception_message ) {
				throw $stripe_exception;
			}
			$this->assertEquals( $expected_exception_message, $stripe_exception->getMessage() );
			$this->assertEquals( $expected_exception_string, $stripe_exception->getLocalizedMessage() );
		} finally {
			// Clean up the filter configuration.
			if ( null !== $mock_customer_search_call ) {
				remove_filter( 'pre_http_request', $mock_customer_search_call, 10 );
			}

			if ( null !== $mock_create_customer_call ) {
				remove_filter( 'pre_http_request', $mock_create_customer_call, 10 );
			}

			remove_filter( 'woocommerce_checkout_fields', $woo_checkout_fields_filter, 10 );

			if ( null !== $stripe_billing_fields_filter ) {
				remove_filter( 'wc_stripe_create_customer_required_fields', $stripe_billing_fields_filter, 10 );
			}

			// Reset checkout fields, otherwise they persist across test cases.
			\WC_Checkout::instance()->checkout_fields = null;
		}

		if ( null !== $expected_exception_message && ! $was_exception_thrown ) {
			$this->fail( 'Expected exception not thrown' );
		}

		if ( null === $expected_exception_message ) {
			$this->assertFalse( $was_exception_thrown, 'No exception was thrown when no exception was expected' );
		}
	}

	/**
	 * Test that update_or_create_customer retrieves billing details from the order object
	 * when called in the context of get_customer_id_for_order().
	 */
	public function test_update_or_create_customer_retrieves_billing_details_from_order() {
		$billing_data = [
			'email'      => 'order-test@example.com',
			'first_name' => 'Order',
			'last_name'  => 'Tester',
			'address_1'  => '456 Order St',
			'city'       => 'Order City',
			'state'      => 'NY',
			'postcode'   => '54321',
			'country'    => 'US',
		];

		// Create mock order with specific billing data.
		$mock_order = $this->getMockBuilder( \WC_Order::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'get_billing_address_1',
					'get_billing_address_2',
					'get_billing_city',
					'get_billing_country',
					'get_billing_email',
					'get_billing_first_name',
					'get_billing_last_name',
					'get_billing_postcode',
					'get_billing_state',
				]
			)
			->getMock();

		$mock_order->method( 'get_billing_address_1' )->willReturn( $billing_data['address_1'] );
		$mock_order->method( 'get_billing_address_2' )->willReturn( '' );
		$mock_order->method( 'get_billing_city' )->willReturn( $billing_data['city'] );
		$mock_order->method( 'get_billing_country' )->willReturn( $billing_data['country'] );
		$mock_order->method( 'get_billing_email' )->willReturn( $billing_data['email'] );
		$mock_order->method( 'get_billing_first_name' )->willReturn( $billing_data['first_name'] );
		$mock_order->method( 'get_billing_last_name' )->willReturn( $billing_data['last_name'] );
		$mock_order->method( 'get_billing_postcode' )->willReturn( $billing_data['postcode'] );
		$mock_order->method( 'get_billing_state' )->willReturn( $billing_data['state'] );

		// Capture the API request arguments.
		$captured_request_body = null;

		$mock_customer_search_call = function ( $return_value, $parsed_args, $url ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/customers/search' ) ) {
				return $return_value;
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'data' => [],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_customer_search_call, 10, 3 );

		$mock_create_customer_call = function ( $return_value, $parsed_args, $url ) use ( &$captured_request_body ) {
			if ( 'https://api.stripe.com/v1/customers' !== $url ) {
				return $return_value;
			}

			// Capture the request body to verify billing details.
			$captured_request_body = $parsed_args['body'];

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id' => 'cus_test_123',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_create_customer_call, 10, 3 );

		$customer = new \WC_Stripe_Customer();

		try {
			// Call update_or_create_customer with order object (simulating get_customer_id_for_order context).
			$customer_id = $customer->update_or_create_customer( [], null, $mock_order );

			// Verify customer was created successfully.
			$this->assertEquals( 'cus_test_123', $customer_id );

			// Verify billing details were correctly retrieved from the order.
			$this->assertNotNull( $captured_request_body, 'Customer create request was not captured' );
			$this->assertStringContainsString( 'email=' . urlencode( $billing_data['email'] ), $captured_request_body );
			$this->assertStringContainsString( 'name=' . urlencode( $billing_data['first_name'] . ' ' . $billing_data['last_name'] ), $captured_request_body );
			$this->assertStringContainsString( urlencode( $billing_data['address_1'] ), $captured_request_body );
			$this->assertStringContainsString( urlencode( $billing_data['city'] ), $captured_request_body );
			$this->assertStringContainsString( urlencode( $billing_data['state'] ), $captured_request_body );
			$this->assertStringContainsString( urlencode( $billing_data['postcode'] ), $captured_request_body );
			$this->assertStringContainsString( urlencode( $billing_data['country'] ), $captured_request_body );
		} finally {
			remove_filter( 'pre_http_request', $mock_customer_search_call, 10 );
			remove_filter( 'pre_http_request', $mock_create_customer_call, 10 );
		}
	}

	/**
	 * Test that update_or_create_customer with pay_for_order context only requires email.
	 */
	public function test_update_or_create_customer_with_pay_for_order_context() {
		$billing_data = [
			'email' => 'payfororder@example.com',
		];

		// Create mock order with minimal billing data (only email).
		$mock_order = $this->getMockBuilder( \WC_Order::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'get_billing_address_1',
					'get_billing_address_2',
					'get_billing_city',
					'get_billing_country',
					'get_billing_email',
					'get_billing_first_name',
					'get_billing_last_name',
					'get_billing_postcode',
					'get_billing_state',
				]
			)
			->getMock();

		$mock_order->method( 'get_billing_address_1' )->willReturn( '' );
		$mock_order->method( 'get_billing_address_2' )->willReturn( '' );
		$mock_order->method( 'get_billing_city' )->willReturn( '' );
		$mock_order->method( 'get_billing_country' )->willReturn( '' );
		$mock_order->method( 'get_billing_email' )->willReturn( $billing_data['email'] );
		$mock_order->method( 'get_billing_first_name' )->willReturn( '' );
		$mock_order->method( 'get_billing_last_name' )->willReturn( '' );
		$mock_order->method( 'get_billing_postcode' )->willReturn( '' );
		$mock_order->method( 'get_billing_state' )->willReturn( '' );

		$captured_request_body = null;

		$mock_create_customer_call = function ( $return_value, $parsed_args, $url ) use ( &$captured_request_body ) {
			if ( 'https://api.stripe.com/v1/customers' !== $url ) {
				return $return_value;
			}

			$captured_request_body = $parsed_args['body'];

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id' => 'cus_pay_for_order_123',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_create_customer_call, 10, 3 );

		$customer = new \WC_Stripe_Customer();

		try {
			// Call update_or_create_customer with pay_for_order context (simulating get_customer_id_for_order).
			$customer_id = $customer->update_or_create_customer(
				[],
				\WC_Stripe_Customer::CUSTOMER_CONTEXT_PAY_FOR_ORDER,
				$mock_order
			);

			// Verify customer was created successfully with minimal details.
			$this->assertEquals( 'cus_pay_for_order_123', $customer_id );

			// Verify email was included in the request.
			$this->assertNotNull( $captured_request_body, 'Customer create request was not captured' );
			$this->assertStringContainsString( 'email=' . urlencode( $billing_data['email'] ), $captured_request_body );
		} finally {
			remove_filter( 'pre_http_request', $mock_create_customer_call, 10 );
		}
	}
}
