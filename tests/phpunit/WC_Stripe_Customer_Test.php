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
			'all fields present' => [
				'billing_fields'             => [],
				'expected_exception_message' => null,
				'expected_exception_string'  => null,
			],
			'email is empty string' => [
				'billing_fields'             => [ 'email' => '' ],
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
			],
			'email is whitespace string' => [
				'billing_fields'             => [ 'email' => '   	  ' ],
				'expected_exception_message' => 'missing_required_customer_field: email',
				'expected_exception_string'  => 'Missing required customer field: email',
			],
			'name is null' => [
				'billing_fields'             => [
					'first_name' => null,
					'last_name' => '',
				],
				'expected_exception_message' => 'missing_required_customer_field: name',
				'expected_exception_string'  => 'Missing required customer field: name',
			],
			'address line1 is empty string' => [
				'billing_fields'             => [ 'address_1' => '' ],
				'expected_exception_message' => 'missing_required_customer_field: address->line1',
				'expected_exception_string'  => 'Missing required customer field: address->line1',
			],
			'address city is empty string' => [
				'billing_fields'             => [ 'city' => '' ],
				'expected_exception_message' => 'missing_required_customer_field: address->city',
				'expected_exception_string'  => 'Missing required customer field: address->city',
			],
			'address city is whitespace string' => [
				'billing_fields'             => [ 'city' => '    ' ],
				'expected_exception_message' => 'missing_required_customer_field: address->city',
				'expected_exception_string'  => 'Missing required customer field: address->city',
			],
			'address country is empty string' => [
				'billing_fields'             => [ 'country' => '' ],
				'expected_exception_message' => 'missing_required_customer_field: address->country',
				'expected_exception_string'  => 'Missing required customer field: address->country',
			],
		];
	}

	/**
	 * @dataProvider provide_test_validate_create_customer_request_cases
	 */
	public function test_validate_create_customer_request( array $billing_fields = [], ?string $expected_exception_message = null, ?string $expected_exception_string = null ) {
		$default_billing_data = [
			'email'      => 'test@example.com',
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'address_1'  => '123 Main St',
			'city'       => 'Anytown',
			'country'    => 'US',
		];

		$billing_data = wp_parse_args( $billing_fields, $default_billing_data );

		$mock_order = $this->getMockBuilder( \WC_Order::class )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_billing_email',
					'get_billing_first_name',
					'get_billing_last_name',
					'get_billing_address_1',
					'get_billing_city',
					'get_billing_country',
				]
			)
			->getMock();

		$mock_order->method( 'get_billing_email' )->willReturn( $billing_data['email'] );
		$mock_order->method( 'get_billing_first_name' )->willReturn( $billing_data['first_name'] );
		$mock_order->method( 'get_billing_last_name' )->willReturn( $billing_data['last_name'] );
		$mock_order->method( 'get_billing_address_1' )->willReturn( $billing_data['address_1'] );
		$mock_order->method( 'get_billing_city' )->willReturn( $billing_data['city'] );
		$mock_order->method( 'get_billing_country' )->willReturn( $billing_data['country'] );

		$args = [
			'order' => $mock_order,
		];
		$customer = new \WC_Stripe_Customer();

		$was_exception_thrown = false;

		$mock_customer_search_call = null;

		if ( ! empty( $billing_data['email'] ) && ! empty( $billing_data['first_name'] ) && ! empty( $billing_data['last_name'] ) ) {
			$mock_customer_search_call = function ( $return_value, $parsed_args, $url ) {
				if ( ! str_starts_with( 'https://api.stripe.com/v1/customers/search', $url ) ) {
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
			$customer->create_customer( $args );
		} catch ( \WC_Stripe_Exception $stripe_exception ) {
			$was_exception_thrown = true;

			if ( null === $expected_exception_message ) {
				throw $stripe_exception;
			}
			$this->assertEquals( $expected_exception_message, $stripe_exception->getMessage() );
			$this->assertEquals( $expected_exception_string, $stripe_exception->getLocalizedMessage() );
		}

		if ( null !== $mock_customer_search_call ) {
			remove_filter( 'pre_http_request', $mock_customer_search_call, 10 );
		}

		if ( null !== $mock_create_customer_call ) {
			remove_filter( 'pre_http_request', $mock_create_customer_call, 10 );
		}

		if ( null !== $expected_exception_message && ! $was_exception_thrown ) {
			$this->fail( 'Expected exception not thrown' );
		}

		if ( null === $expected_exception_message ) {
			$this->assertFalse( $was_exception_thrown, 'No exception was thrown when no exception was expected' );
		}
	}
}
