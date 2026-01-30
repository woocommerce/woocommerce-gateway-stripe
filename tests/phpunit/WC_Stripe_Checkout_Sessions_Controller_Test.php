<?php

namespace WooCommerce\Stripe\Tests;

use Automattic\WooCommerce\Enums\ProductTaxStatus;
use WooCommerce\Stripe\Tests\Helpers\Ajax_Test_Helper;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Product;
use WP_UnitTestCase;
use WC_Stripe_Checkout_Sessions_Controller;

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
	 * @param object|null $checkout_session_response  The mocked response from Stripe when creating the Checkout Session.
	 * @param string|null $expected_exception_message The expected exception message, if any.
	 * @param string|null $expected_secret            The expected client secret, if any.
	 * @return void
	 * @dataProvider provide_test_create_checkout_session
	 */
	public function test_create_checkout_session(
		bool $is_valid_nonce,
		array $customer_data = [],
		bool $is_cart_empty = true,
		?object $checkout_session_response = null,
		?string $expected_exception_message = null,
		?string $expected_secret = null
	): void {
		Ajax_Test_Helper::init_hooks();

		// Set up a logged-in user with billing details.
		wp_set_current_user( 1 );

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

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( $checkout_session_response ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		// Set up the AJAX request nonce.
		$_REQUEST['_ajax_nonce'] = $is_valid_nonce ? wp_create_nonce( 'wc_stripe_create_checkout_session_nonce' ) : 'invalid_nonce_value';

		$controller = new WC_Stripe_Checkout_Sessions_Controller();

		if ( $expected_exception_message ) {
			$this->expectException( \Exception::class );
			$this->expectExceptionMessage( $expected_exception_message );
		}

		try {
			ob_start();
			$controller->create_checkout_session();
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			ob_end_clean();
			throw $e;
		}

		// Clean up.
		remove_filter( 'pre_http_request', $test_request, 10, 3 );
		Ajax_Test_Helper::remove_hooks();

		if ( $expected_secret ) {
			$response = json_decode( $output );
			$this->assertEquals( $expected_secret, $response->client_secret );
		}
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
				'is valid nonce'             => false,
				'customer data'              => [],
				'is cart empty'              => true,
				'checkout session response'  => null,
				'expected exception message' => "We're not able to process this payment. Please refresh the page and try again.",
				'expected secret'            => null,
			],
			'missing customer data'    => [
				'is valid nonce'             => true,
				'customer data'              => [],
				'is cart empty'              => true,
				'checkout session response'  => null,
				'expected exception message' => 'Unable to create or retrieve Stripe customer.',
				'expected secret'            => null,
			],
			'cart is empty'            => [
				'is valid nonce'             => true,
				'customer data'              => $customer_data,
				'is cart empty'              => true,
				'checkout session response'  => null,
				'expected exception message' => 'Your cart is currently empty.',
				'expected secret'            => null,
			],
			'error creating session'   => [
				'is valid nonce'             => true,
				'customer data'              => $customer_data,
				'is cart empty'              => false,
				'checkout session response'  => $checkout_session_error,
				'expected exception message' => $mocked_error_message,
				'expected secret'            => null,
			],
			'client secret is missing' => [
				'is valid nonce'             => true,
				'customer data'              => $customer_data,
				'is cart empty'              => false,
				'checkout session response'  => $checkout_session_missing_secret,
				'expected exception message' => 'Unable to create Stripe Checkout Session.',
				'expected secret'            => null,
			],
			'successful creation'      => [
				'is valid nonce'             => true,
				'customer data'              => $customer_data,
				'is cart empty'              => false,
				'checkout session response'  => $checkout_session_success,
				'expected exception message' => null,
				'expected secret'            => $mocked_secret,
			],
		];
	}
}
