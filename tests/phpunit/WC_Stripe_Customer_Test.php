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

	/**
	 * Helper method to create a mock WC_Order with billing data.
	 *
	 * @param array $billing_data Billing data array with keys: email, first_name, last_name, address_1, address_2, city, state, postcode, country.
	 * @return \PHPUnit\Framework\MockObject\MockObject Mock WC_Order instance.
	 */
	private function create_mock_order( array $billing_data = [] ) {
		$default_billing_data = [
			'email'      => 'test@example.com',
			'first_name' => 'Test',
			'last_name'  => 'User',
			'address_1' => '123 Test St',
			'address_2' => '',
			'city'       => 'Test City',
			'state'      => 'CA',
			'postcode'   => '90210',
			'country'    => 'US',
		];

		$billing_data = wp_parse_args( $billing_data, $default_billing_data );

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

		$mock_order->method( 'get_billing_email' )->willReturn( $billing_data['email'] );
		$mock_order->method( 'get_billing_first_name' )->willReturn( $billing_data['first_name'] );
		$mock_order->method( 'get_billing_last_name' )->willReturn( $billing_data['last_name'] );
		$mock_order->method( 'get_billing_address_1' )->willReturn( $billing_data['address_1'] );
		$mock_order->method( 'get_billing_address_2' )->willReturn( $billing_data['address_2'] );
		$mock_order->method( 'get_billing_city' )->willReturn( $billing_data['city'] );
		$mock_order->method( 'get_billing_state' )->willReturn( $billing_data['state'] );
		$mock_order->method( 'get_billing_postcode' )->willReturn( $billing_data['postcode'] );
		$mock_order->method( 'get_billing_country' )->willReturn( $billing_data['country'] );

		return $mock_order;
	}

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

		$mock_order = $this->create_mock_order( $billing_data );

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
			// Reset WC Customer, otherwise it also persists across test cases.
			\WC()->customer = null;
		}

		if ( null !== $expected_exception_message && ! $was_exception_thrown ) {
			$this->fail( 'Expected exception not thrown' );
		}

		if ( null === $expected_exception_message ) {
			$this->assertFalse( $was_exception_thrown, 'No exception was thrown when no exception was expected' );
		}
	}

	/**
	 * Test that order data is excluded from Stripe API requests when passed via $args['order'].
	 *
	 * This test verifies the fix for backwards compatibility: when order is passed via $args['order'],
	 * it should be extracted and used for billing data, but the 'order' key itself should not be
	 * sent to the Stripe API.
	 */
	public function test_order_excluded_from_stripe_api_when_passed_via_args() {
		$customer = new \WC_Stripe_Customer();

		$mock_order = $this->create_mock_order( [ 'address_2' => 'Apt 1' ] );

		$captured_create_request = null;
		$captured_update_request  = null;

		// Mock for create_customer
		$mock_create_call = function ( $return_value, $parsed_args, $url ) use ( &$captured_create_request ) {
			if ( 'https://api.stripe.com/v1/customers' === $url ) {
				if ( isset( $parsed_args['body'] ) ) {
					$captured_create_request = is_array( $parsed_args['body'] ) ? $parsed_args['body'] : [];
					if ( ! is_array( $captured_create_request ) ) {
						parse_str( $captured_create_request, $captured_create_request );
					}
				}
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode( [ 'id' => 'cus_123' ] ),
				];
			}
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/customers/search' ) ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode( [ 'data' => [] ] ),
				];
			}
			return $return_value;
		};

		// Mock for update_customer
		$mock_update_call = function ( $return_value, $parsed_args, $url ) use ( &$captured_update_request ) {
			if ( 'https://api.stripe.com/v1/customers/cus_existing' === $url ) {
				if ( isset( $parsed_args['body'] ) ) {
					$captured_update_request = is_array( $parsed_args['body'] ) ? $parsed_args['body'] : [];
					if ( ! is_array( $captured_update_request ) ) {
						parse_str( $captured_update_request, $captured_update_request );
					}
				}
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => json_encode( [ 'id' => 'cus_existing' ] ),
				];
			}
			return $return_value;
		};

		add_filter( 'pre_http_request', $mock_create_call, 10, 3 );

		try {
			// Test create_customer with $args['order']
			$args = [ 'order' => $mock_order ];
			$customer->create_customer( $args );

			$this->assertNotNull( $captured_create_request, 'Create request should have been captured' );
			$this->assertArrayNotHasKey( 'order', $captured_create_request, 'Order should not be sent to Stripe API in create_customer' );
		} finally {
			remove_filter( 'pre_http_request', $mock_create_call, 10 );
		}

		// Test update_customer with $args['order']
		$customer->set_id( 'cus_existing' );
		add_filter( 'pre_http_request', $mock_update_call, 10, 3 );

		try {
			$args = [ 'order' => $mock_order ];
			$customer->update_customer( $args );

			$this->assertNotNull( $captured_update_request, 'Update request should have been captured' );
			$this->assertArrayNotHasKey( 'order', $captured_update_request, 'Order should not be sent to Stripe API in update_customer' );
		} finally {
			remove_filter( 'pre_http_request', $mock_update_call, 10 );
		}
	}

	/**
	 * Data provider for {@see test_generate_customer_request_via_create_customer()}.
	 */
	public function provide_test_generate_customer_request_via_create_customer_cases(): array {
		$jane_doe_user_data = [
			'ID'         => 12345,
			'user_email' => 'test-user@example.com',
			'user_login' => 'test_user',
		];
		$jane_doe_user_meta_data = [
			'billing_first_name' => 'Jane',
			'billing_last_name'  => 'Doe',
			'billing_address_1'  => '321 Main St',
			'billing_address_2'  => 'Apt 4B',
			'billing_city'       => 'Anytown',
			'billing_state'      => 'CA',
			'billing_postcode'   => '98765',
			'billing_country'    => 'US',
		];
		$jane_doe_expected_fields = [
			'email'             => 'test-user@example.com',
			'description'       => 'Name: Jane Doe, Username: test_user',
			'name'              => 'Jane Doe',
			'metadata'          => [],
			'preferred_locales' => [ 'en-US' ],
			'address' => [
				'line1'       => '321 Main St',
				'line2'       => 'Apt 4B',
				'postal_code' => '98765',
				'city'        => 'Anytown',
				'state'       => 'CA',
				'country'     => 'US',
			],
		];

		$john_smith_wc_customer_data = [
			'get_first_name'        => 'John',
			'get_last_name'         => 'Smith',
			'get_email'             => 'test-wc-customer@example.com',
			'get_username'          => 'test_wc_customer',
			'get_billing_address_1' => '123 Customer St',
			'get_billing_address_2' => 'Apt 3C',
			'get_billing_city'      => 'Testville',
			'get_billing_state'     => 'ON',
			'get_billing_postcode'  => 'ABC 123',
			'get_billing_country'   => 'CA',
		];
		$john_smith_expected_fields = [
			'email'             => 'test-wc-customer@example.com',
			'description'       => 'Name: John Smith, Username: test_wc_customer',
			'name'              => 'John Smith',
			'metadata'          => [],
			'preferred_locales' => [ 'en-US' ],
			'address' => [
				'line1'       => '123 Customer St',
				'line2'       => 'Apt 3C',
				'postal_code' => 'ABC 123',
				'city'        => 'Testville',
				'state'       => 'ON',
				'country'     => 'CA',
			],
		];

		$jack_cash_billing_data = [
			'billing_email'      => 'test-billing@example.com',
			'billing_first_name' => 'Jack',
			'billing_last_name'  => 'Cash',
			'billing_address_1'  => '456 Test St',
			'billing_address_2'  => 'Apt 2D',
			'billing_city'       => 'Tester',
			'billing_state'      => 'NY',
			'billing_postcode'   => '12345',
			'billing_country'    => 'US',
		];
		$jack_cash_expected_fields = [
			'email'             => 'test-billing@example.com',
			'description'       => 'Name: Jack Cash, Guest',
			'name'              => 'Jack Cash',
			'metadata'          => [],
			'preferred_locales' => [ 'en-US' ],
			'address' => [
				'line1'       => '456 Test St',
				'line2'       => 'Apt 2D',
				'postal_code' => '12345',
				'city'        => 'Tester',
				'state'       => 'NY',
				'country'     => 'US',
			],
		];

		return [
			'user_meta preferred, no WC_Customer - fields from user and user meta' => [
				'preferred_source' => 'user_meta',
				'user_data'        => $jane_doe_user_data,
				'user_meta_data'   => $jane_doe_user_meta_data,
				'wc_customer_data' => null,
				'billing_data'     => null,
				'expected'         => $jane_doe_expected_fields,
			],
			'user_meta preferred, no WC_Customer - fields from user and user meta using fallback names from meta and email from billing data' => [
				'preferred_source' => 'user_meta',
				'user_data'        => array_merge( $jane_doe_user_data, [ 'user_email' => '' ] ),
				'user_meta_data'   => array_merge(
					$jane_doe_user_meta_data,
					[
						'first_name'         => 'Jennifer',
						'last_name'          => 'Fallback',
						'billing_first_name' => '',
						'billing_last_name'  => '',
					]
				),
				'wc_customer_data' => null,
				'billing_data'     => array_merge( $jack_cash_billing_data, [ 'billing_email' => 'test-fallback-billing-email@example.com' ] ),
				'expected'         => array_merge(
					$jane_doe_expected_fields,
					[
						'email'             => 'test-fallback-billing-email@example.com',
						'name'              => 'Jennifer Fallback',
						'description'       => 'Name: Jennifer Fallback, Username: test_user',
					]
				),
			],
			'wc_customer preferred, no user - fields from WC_Customer' => [
				'preferred_source' => 'wc_customer',
				'user_data'        => null,
				'user_meta_data'   => null,
				'wc_customer_data' => $john_smith_wc_customer_data,
				'billing_data'     => null,
				'expected'         => $john_smith_expected_fields,
			],
			'wc_customer preferred, no user - fields from WC_Customer; fallback names and email from billing data' => [
				'preferred_source' => 'wc_customer',
				'user_data'        => null,
				'user_meta_data'   => null,
				'wc_customer_data' => array_merge(
					$john_smith_wc_customer_data,
					[
						'get_email'      => '',
						'get_first_name' => '',
						'get_last_name'  => '',
					]
				),
				'billing_data'     => [
					'billing_email'      => 'test-fallback-billing-email@example.com',
					'billing_first_name' => 'Joseph',
					'billing_last_name'  => 'Fallback',
				],
				'expected'         => array_merge(
					$john_smith_expected_fields,
					[
						'email'             => 'test-fallback-billing-email@example.com',
						'name'              => 'Joseph Fallback',
						'description'       => 'Name: Joseph Fallback, Username: test_wc_customer',
					]
				),
			],
			'user_meta preferred, no user or WC_Customer - fields from billing data' => [
				'preferred_source' => 'user_meta',
				'user_data'        => null,
				'user_meta_data'   => null,
				'wc_customer_data' => null,
				'billing_data'     => $jack_cash_billing_data,
				'expected'         => $jack_cash_expected_fields,
			],
			'user_meta preferred, data present across all sources, user fields take precedence' => [
				'preferred_source' => 'user_meta',
				'user_data'        => $jane_doe_user_data,
				'user_meta_data'   => $jane_doe_user_meta_data,
				'wc_customer_data' => $john_smith_wc_customer_data,
				'billing_data'     => $jack_cash_billing_data,
				'expected'         => $jane_doe_expected_fields,
			],
			'user_meta preferred, no user data present, wc_customer_data present, billing fields take precedence' => [
				'preferred_source' => 'user_meta',
				'user_data'        => null,
				'user_meta_data'   => null,
				'wc_customer_data' => $john_smith_wc_customer_data,
				'billing_data'     => $jack_cash_billing_data,
				'expected'         => $jack_cash_expected_fields,
			],
			'wc_customer preferred, data present across all sources, wc_customer fields take precedence' => [
				'preferred_source' => 'wc_customer',
				'user_data'        => $jane_doe_user_data,
				'user_meta_data'   => $jane_doe_user_meta_data,
				'wc_customer_data' => $john_smith_wc_customer_data,
				'billing_data'     => $jack_cash_billing_data,
				'expected'         => $john_smith_expected_fields,
			],
			'wc_customer preferred, no wc_customer data present, user data present, billing fields take precedence' => [
				'preferred_source' => 'wc_customer',
				'user_data'        => $jane_doe_user_data,
				'user_meta_data'   => $jane_doe_user_meta_data,
				'wc_customer_data' => null,
				'billing_data'     => $jack_cash_billing_data,
				'expected'         => $jack_cash_expected_fields,
			],
		];
	}

	/**
	 * Test the generate_customer_request() method via create_customer().
	 *
	 * @dataProvider provide_test_generate_customer_request_via_create_customer_cases
	 *
	 * @param string           $preferred_customer_source The preferred source of customer data.
	 * @param array|null       $user_data                 The user data.
	 * @param array|null       $user_meta_data            The user meta data.
	 * @param array|null       $wc_customer_data          The WC customer data.
	 * @param array|null       $billing_data              The billing data.
	 */
	public function test_generate_customer_request_via_create_customer( string $preferred_customer_source, ?array $user_data, ?array $user_meta_data, ?array $wc_customer_data, ?array $billing_data, array $expected ) {
		/** @var \WC_Stripe_Customer & \PHPUnit\Framework\MockObject\MockObject $stripe_customer */
		$stripe_customer = $this->getMockBuilder( \WC_Stripe_Customer::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'get_user',
					'get_wc_customer',
					'get_billing_data_field',
					'get_existing_customer',
				]
			)
			->getMock();
		/*
		 * We really want to focus on testing generate_customer_request(), which is called early on in create_customer().
		 * We thus want to stub out get_existing_customer(), as it is not always called, and then throw an exception from
		 * within the 'wc_stripe_create_customer_args' filter, as that allows us to interrogate the data generated by generate_customer_request().
		 */
		$stripe_customer->method( 'get_existing_customer' )->willReturn( [] );

		$create_customer_args_filter = function ( $create_customer_args ) use ( $expected ) {
			$this->assertCount( count( $expected ), $create_customer_args );
			foreach ( $expected as $key => $value ) {
				$this->assertArrayHasKey( $key, $create_customer_args );
				$this->assertEquals( $value, $create_customer_args[ $key ] );
			}
			throw new \Exception( 'test_exception', 321 );
		};
		add_filter( 'wc_stripe_create_customer_args', $create_customer_args_filter, 10, 1 );

		$preferred_customer_source_filter = function ( $preferred_customer_source_arg ) use ( $preferred_customer_source ) {
			return $preferred_customer_source;
		};
		add_filter( 'wc_stripe_customer_data_preferred_source', $preferred_customer_source_filter, 10, 1 );

		$user_return_value = false;
		if ( is_array( $user_data ) ) {
			if ( ! isset( $user_data['ID'] ) ) {
				$user_data['ID'] = 12345;
			}
			$user_return_value = new \WP_User( (object) $user_data );
		}
		$stripe_customer->method( 'get_user' )->willReturn( $user_return_value );

		$user_meta_filter = null;
		if ( is_array( $user_meta_data ) ) {
			$user_meta_filter = function ( $user_meta_value, $user_id, $meta_key, $single ) use ( $user_meta_data ) {
				if ( $single && isset( $user_meta_data[ $meta_key ] ) ) {
					return $user_meta_data[ $meta_key ];
				}
				return $user_meta_value;
			};
			add_filter( 'get_user_metadata', $user_meta_filter, 10, 4 );
		}

		$wc_customer_return_value = null;
		if ( is_array( $wc_customer_data ) ) {
			$mock_wc_customer = $this->getMockBuilder( \WC_Customer::class )
				->disableOriginalConstructor()
				->onlyMethods( array_keys( $wc_customer_data ) )
				->getMock();

			foreach ( $wc_customer_data as $key => $value ) {
				$mock_wc_customer->method( $key )
					->willReturn( $value );
			}

			$wc_customer_return_value = $mock_wc_customer;
		}

		$stripe_customer->method( 'get_wc_customer' )
			->willReturn( $wc_customer_return_value );

		$billing_data_callback = function ( $field, $args ) use ( $billing_data ) {
			if ( is_array( $billing_data ) && isset( $billing_data[ $field ] ) ) {
				return $billing_data[ $field ];
			}
			return '';
		};

		$stripe_customer->method( 'get_billing_data_field' )
			->will( $this->returnCallback( $billing_data_callback ) );

		$caught_exception = null;
		try {
			$stripe_customer->create_customer( [] );
		} catch ( \Throwable $e ) {
			$caught_exception = $e;
		}

		// Perform all cleanup before assertions.

		if ( null !== $user_meta_filter ) {
			remove_filter( 'get_user_metadata', $user_meta_filter, 10 );
		}

		remove_filter( 'wc_stripe_customer_data_preferred_source', $preferred_customer_source_filter, 10 );

		remove_filter( 'wc_stripe_create_customer_args', $create_customer_args_filter, 10 );

		// Make sure we caught the expected exception.
		$this->assertInstanceOf( \Exception::class, $caught_exception );
		$this->assertEquals( 'test_exception', $caught_exception->getMessage() );
		$this->assertEquals( 321, $caught_exception->getCode() );
	}
}
