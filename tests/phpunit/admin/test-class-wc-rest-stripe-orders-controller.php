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
		$request  = new WP_REST_Request( 'POST', self::ORDERS_REST_BASE . '/1/create_customer' );
		$response = rest_do_request( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_create_customer_invalid_order_fails() {
		wp_set_current_user( 1 );

		$request  = new WP_REST_Request( 'POST', self::ORDERS_REST_BASE . '/1/create_customer' );
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
				'body'     => wp_json_encode(
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
				'body'     => wp_json_encode(
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

	public function test_capture_payment_success() {
		wp_set_current_user( 1 );
		$order = WC_Helper_Order::create_order();

		// Mock response from Stripe API.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'id'      => 'pi_12345',
						'object'  => 'payment_intent',
						'status'  => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
						'charges' => [
							'data' => [
								[
									'id'                  => 'ch_12345',
									'balance_transaction' => [
										'id' => 'txn_12345',
									],
									'status'              => 'succeeded',
								],
							],
						],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$endpoint = self::ORDERS_REST_BASE . '/' . strval( $order->get_id() ) . '/capture_terminal_payment';
		$request  = new WP_REST_Request( 'POST', $endpoint );
		$request->set_param( 'payment_intent_id', 'pi_12345' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'succeeded', $response->get_data()['status'] );
		$this->assertEquals( 'ch_12345', $response->get_data()['id'] );
		$this->assertEquals( 'pi_12345', $order->get_meta( '_stripe_intent_id', true ) );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}

	public function test_capture_payment_missing_order() {
		wp_set_current_user( 1 );

		$endpoint = self::ORDERS_REST_BASE . '/1/capture_terminal_payment';
		$request  = new WP_REST_Request( 'POST', $endpoint );
		$request->set_param( 'payment_intent_id', 'pi_12345' );
		$response = rest_do_request( $request );

		$this->assertEquals( 'wc_stripe_missing_order', $response->get_data()['code'] );
		$this->assertEquals( 404, $response->get_status() );
	}

	public function test_capture_payment_invalid_status() {
		wp_set_current_user( 1 );
		$order = WC_Helper_Order::create_order();

		// Mock response from Stripe API.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'id'     => 'pi_12345',
						'object' => 'payment_intent',
						'status' => WC_Stripe_Intent_Status::SUCCEEDED,
					]
				),
			];
		};
		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$endpoint = self::ORDERS_REST_BASE . '/' . strval( $order->get_id() ) . '/capture_terminal_payment';
		$request  = new WP_REST_Request( 'POST', $endpoint );
		$request->set_param( 'payment_intent_id', 'pi_12345' );
		$response = rest_do_request( $request );

		$this->assertEquals( 'wc_stripe_payment_uncapturable', $response->get_data()['code'] );
		$this->assertEquals( 409, $response->get_status() );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}

	public function test_capture_payment_refunded_order() {
		wp_set_current_user( 1 );
		$order = WC_Helper_Order::create_order();
		wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 1,
			]
		);

		$endpoint = self::ORDERS_REST_BASE . '/' . strval( $order->get_id() ) . '/capture_terminal_payment';
		$request  = new WP_REST_Request( 'POST', $endpoint );
		$request->set_param( 'payment_intent_id', 'pi_12345' );
		$response = rest_do_request( $request );

		$this->assertEquals( 'wc_stripe_refunded_order_uncapturable', $response->get_data()['code'] );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_capture_payment_stripe_error() {
		wp_set_current_user( 1 );
		$order = WC_Helper_Order::create_order();

		// Mock response from Stripe API.
		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'id'      => 'pi_12345',
						'object'  => 'payment_intent',
						'status'  => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
						'charges' => [
							'data' => [
								[
									'id'                  => 'ch_12345',
									'balance_transaction' => [
										'id' => 'txn_12345',
									],
									'status'              => 'failed',
								],
							],
						],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$endpoint = self::ORDERS_REST_BASE . '/' . strval( $order->get_id() ) . '/capture_terminal_payment';
		$request  = new WP_REST_Request( 'POST', $endpoint );
		$request->set_param( 'payment_intent_id', 'pi_12345' );
		$response = rest_do_request( $request );

		$this->assertEquals( 'wc_stripe_capture_error', $response->get_data()['code'] );
		$this->assertEquals( 502, $response->get_status() );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );
	}
}
