<?php
/**
 * These tests assert various things about processing an initial payment for a WooCommerce Subscriptions.
 *
 * The responses from HTTP requests are mocked using the WP filter `pre_http_request`.
 *
 * There are a few methods that need to be mocked in the class WC_Gateway_Stripe, which is
 * why that class is mocked even though the method under test is part of that class.
 *
 * @package WooCommerce_Stripe/Classes/WC_Stripe_Subscription_Initial_Test
 */

/**
 * WC_Stripe_Subscription_Initial_Test
 */
class WC_Stripe_Subscription_Initial_Test extends WP_UnitTestCase {
	/**
	 * System under test, and a mock object with some methods mocked for testing
	 *
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	private $wc_gateway_stripe;

	/**
	 * The statement descriptor we'll use in a test.
	 *
	 * @var string
	 */
	private $statement_descriptor;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->wc_gateway_stripe = $this->getMockBuilder( 'WC_Gateway_Stripe' )
			->disableOriginalConstructor()
			->setMethods( [ 'prepare_source', 'has_subscription' ] )
			->getMock();

		// Mocked in order to get metadata[payment_type] = recurring in the HTTP request.
		$this->statement_descriptor = 'This is a statement descriptor.';
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'statement_descriptor' => $this->statement_descriptor,
			]
		);
	}

	/**
	 * Tears down the stuff we set up.
	 */
	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	/**
	 * Tests whether the initial payment succeeds and includes the `setup_future_usage` parameter.
	 *
	 * 1. Several things are set up or mocked.
	 * 2. A function that will mock an HTTP response for the payment_intents is created.
	 * 3. That same function has some assertions about the things we send to the
	 * payments_intents endpoint.
	 * 4. The function under test - `process_payment` - is called.
	 * 5. More assertions are made.
	 */
	public function test_initial_intent_parameters() {
		$initial_order        = WC_Helper_Order::create_order();
		$order_id             = $initial_order->get_id();
		$stripe_amount        = WC_Stripe_Helper::get_stripe_amount( $initial_order->get_total() );
		$currency             = strtolower( $initial_order->get_currency() );
		$customer             = 'cus_123abc';
		$source               = 'src_123abc';
		$intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$urls_used            = [];

		$initial_order->set_payment_method( 'stripe' );
		$initial_order->save();

		// Arrange: Mock prepare_source() so that we have a customer and source.
		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_source' )
			->will(
				$this->returnValue(
					(object) [
						'token_id'       => false,
						'customer'       => $customer,
						'source'         => $source,
						'source_object'  => (object) [
							'type' => WC_Stripe_Payment_Methods::CARD,
						],
						'payment_method' => null,
					]
				)
			);

		// Emulate a subscription.
		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'has_subscription' )
			->will( $this->returnValue( true ) );

		$pre_http_request_response_callback = function( $preempt, $request_args, $url ) use (
			$stripe_amount,
			$currency,
			$customer,
			$source,
			$intents_api_endpoint,
			$order_id,
			&$urls_used
		) {
			// Add all urls to array so we can later make assertions about which endpoints were used.
			array_push( $urls_used, $url );
			// Continue without mocking the request if it's not the endpoint we care about.
			if ( 0 !== strpos( $url, $intents_api_endpoint ) ) {
				return false;
			}

			// Prepare the response early because it is used for confirmations too.
			$response = [
				'headers'  => [],
				// Too bad we aren't dynamically setting things 'cus_123abc' when using this file.
				'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_signup_response_success.json' ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];

			// Respond with a successfull intent for confirmations.
			if ( $url !== $intents_api_endpoint ) {
				$response['body'] = str_replace( WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION, WC_Stripe_Intent_Status::SUCCEEDED, $response['body'] );
				return $response;
			}

			// Assert: the request method is POST.
			$this->assertArrayHasKey( 'method', $request_args );
			$this->assertSame( 'POST', $request_args['method'] );

			// Assert: the request has a body.
			$this->assertArrayHasKey( 'body', $request_args );

			// Assert: the request body contains these values.
			$expected_request_body_values = [
				'source'               => $source,
				'amount'               => $stripe_amount,
				'currency'             => $currency,
				'customer'             => $customer,
				'setup_future_usage'   => 'off_session',
				'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
			];
			foreach ( $expected_request_body_values as $key => $value ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
				$this->assertSame( $value, $request_args['body'][ $key ] );
			}

			// Assert: the request body contains these keys, without checking for their value.
			$expected_request_body_keys = [
				'description',
				'capture_method',
			];
			foreach ( $expected_request_body_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
			}

			// Assert: the body metadata contains the order ID.
			$this->assertSame( $order_id, absint( $request_args['body']['metadata']['order_id'] ) );

			// // Assert: the body metadata has these keys, without checking for their value.
			$expected_metadata_keys = [
				'customer_name',
				'customer_email',
			];
			foreach ( $expected_metadata_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body']['metadata'] );
			}

			// Return dummy content as the response.
			return $response;
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Act: call process_subscription_payment().
		// We need to use `wc_gateway_stripe` here because we mocked this class earlier.
		$result = $this->wc_gateway_stripe->process_payment( $order_id );

		// Assert: nothing was returned.
		$this->assertEquals( $result['result'], 'success' );
		$this->assertArrayHasKey( 'redirect', $result );

		$order      = wc_get_order( $order_id );
		$order_data = $order->get_meta( '_stripe_intent_id' );

		$this->assertEquals( $order_data, 'pi_123abc' );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $intents_api_endpoint, $urls_used, true ) );

		// Clean up.
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request_response_success' ] );
	}
}
