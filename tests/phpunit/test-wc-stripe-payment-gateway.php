<?php
/**
 * These tests make assertions against abstract class WC_Stripe_Payment_Gateway
 *
 */


class WC_Stripe_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * Gateway under test.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();

		$this->gateway = new WC_Gateway_Stripe();
	}

	/**
	 * Tests false is returned if payment intent is not set in the order.
	 */
	public function test_default_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );
	}

	/**
	 * Tests if payment intent is fetched from Stripe API.
	 */
	public function test_success_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data('_stripe_intent_id', 'pi_123');
		$expected_intent = (object) [ 'id' => 'pi_123' ];
		$callback = function( $preempt, $request_args, $url ) use ( $expected_intent ) {
			$response = [
				'headers' 	=> [],
				'body'		=> json_encode( $expected_intent ),
				'response'	=> [
					'code' 		=> 200,
					'message' 	=> 'OK',
				],
			];

			$this->assertEquals( 'GET', $request_args['method'] );
			$this->assertStringEndsWith( 'payment_intents/pi_123', $url );

			return $response;
		};

		add_filter( 'pre_http_request', $callback, 10, 3);

		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertEquals( $expected_intent, $intent );

		remove_filter( 'pre_http_request', $callback);
	}

	/**
	 * Tests if false is returned when error is returned from Stripe API.
	 */
	public function test_error_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data('_stripe_intent_id', 'pi_123');
		$response_error = (object) [
			'error' => [
				'code' 		=> 'resource_missing',
				'message' 	=> 'error_message'
			]
		];
		$callback = function( $preempt, $request_args, $url ) use ( $response_error ) {
			$response = [
				'headers' 	=> [],
				'body'		=> json_encode( $response_error ),
				'response'	=> [
					'code' 		=> 404,
					'message' 	=> 'ERR',
				],
			];

			$this->assertEquals( 'GET', $request_args['method'] );
			$this->assertStringEndsWith( 'payment_intents/pi_123', $url );

			return $response;
		};

		add_filter( 'pre_http_request', $callback, 10, 3);

		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );

		remove_filter( 'pre_http_request', $callback);
	}
}
