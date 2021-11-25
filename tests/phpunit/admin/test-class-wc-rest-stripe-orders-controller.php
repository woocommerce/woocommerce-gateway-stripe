<?php
/**
 * Class WC_REST_Stripe_Orders_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_REST_Stripe_Orders_Controller
 */

/**
 * WC_REST_Stripe_Orders_Controller unit tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_REST_Stripe_Orders_Controller_Test extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const ORDERS_REST_BASE = '/wc/v3/wc_stripe/orders';

	public function test_create_customer_requires_auth() {
		wp_set_current_user( 0 );

		// Unauthenticated user should not be able to access route.
		$request = new WP_REST_Request( 'POST', self::ORDERS_REST_BASE . '/1/create_customer' );
		$response = rest_do_request( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_create_customer_invalid_order_fails() {
		wp_set_current_user( 1 );

		$request = new WP_REST_Request( 'POST', self::ORDERS_REST_BASE . '/1/create_customer' );
		$response = rest_do_request( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	public function test_create_customer_guest_order() {
		wp_set_current_user( 1 );

		$order    = WC_Helper_Order::create_order();
		$endpoint = '/' . strval( $order->get_id() ) . '/create_customer';

		// Mock response from Stripe API using request arguments.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			// If request is for updating customer, return existing ID; otherwise return 'cus_new'.
			$matches = [];
			preg_match( '/customers\/(\d+)/', $url, $matches );
			$customer_id = $matches ? $matches[1] : 'cus_new';
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id' => $customer_id,
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$request  = new WP_REST_Request( 'POST', self::ORDERS_REST_BASE . $endpoint );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'cus_new', $response->get_data()['id'] );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}

	public function test_create_customer_with_existing_id() {
		wp_set_current_user( 1 );

		$order    = WC_Helper_Order::create_order();
		$endpoint = '/' . strval( $order->get_id() ) . '/create_customer';
		$order->add_meta_data( '_stripe_customer_id', 'cus_12345', true );
		$order->save();
		$this->assertEquals( 'cus_12345', $order->get_meta( '_stripe_customer_id', true ) );

		// Mock response from Stripe API using request arguments.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			// If request is for updating customer, return existing ID; otherwise return 'cus_new'.
			$matches = [];
			preg_match( '/customers\/(\d+)/', $url, $matches );
			$customer_id = $matches ? $matches[1] : 'cus_new';
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id' => $customer_id,
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$request  = new WP_REST_Request( 'POST', self::ORDERS_REST_BASE . $endpoint );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'cus_12345', $response->get_data()['id'] );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}
}
