<?php
/**
 * These tests assert various things about processing a renewal payment for a WooCommerce Subscription.
 *
 * The responses from HTTP requests are mocked using the WP filter `pre_http_request`.
 *
 * There are a few methods that need to be mocked in the class WC_Gateway_Stripe, which is
 * why that class is mocked even though the method under test is part of that class.
 *
 * @package     WooCommerce_Stripe/Classes/WC_Stripe_Subscription_Renewal_Test
 */

/**
 * WC_Stripe_Subscription_Renewal_Test
 */
class WC_Stripe_Subscription_Renewal_Test extends WP_UnitTestCase {
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
			->setMethods( [ 'prepare_order_source', 'has_subscription' ] )
			->getMock();

		// Mocked in order to get metadata[payment_type] = recurring in the HTTP request.
		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'has_subscription' )
			->will(
				$this->returnValue( true )
			);

		$this->statement_descriptor = 'This is a statement descriptor.';

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		// Disable UPE.
		$stripe_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'no';
		// Set statement descriptor.
		$stripe_settings['statement_descriptor'] = $this->statement_descriptor;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Tears down the stuff we set up.
	 */
	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	/**
	 * Overall this test works like this:
	 *
	 * 1. Several things are set up or mocked.
	 * 2. A function that will mock an HTTP response for the payment_intents is created.
	 * 3. That same function has some assertions about the things we send to the
	 * payments_intents endpoint.
	 * 4. The function under test - `process_subscription_payment` - is called.
	 * 5. More assertions are made.
	 */
	public function test_renewal_successful() {
		// Arrange: Some variables we'll use later.
		$renewal_order                 = WC_Helper_Order::create_order();
		$amount                        = 20; // WC Subs sends an amount to be used, instead of using the order amount.
		$stripe_amount                 = WC_Stripe_Helper::get_stripe_amount( $amount );
		$currency                      = strtolower( $renewal_order->get_currency() );
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$should_retry                  = false;
		$previous_error                = false;
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$urls_used                     = [];

		$renewal_order->set_payment_method( 'stripe' );

		// Arrange: Mock prepare_order_source() so that we have a customer and source.
		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
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

		// Arrange: Add filter that will return a mocked HTTP response for the payment_intent call.
		// Note: There are assertions in the callback function.
		$pre_http_request_response_callback = function( $preempt, $request_args, $url ) use (
			$renewal_order,
			$stripe_amount,
			$currency,
			$customer,
			$source,
			$payments_intents_api_endpoint,
			&$urls_used
		) {
			// Add all urls to array so we can later make assertions about which endpoints were used.
			array_push( $urls_used, $url );

			// Continue without mocking the request if it's not the endpoint we care about.
			if ( $payments_intents_api_endpoint !== $url ) {
				return false;
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
				'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
				'customer'             => $customer,
				'off_session'          => 'true',
				'confirm'              => 'true',
				'confirmation_method'  => 'automatic',
				'capture_method'       => 'automatic',
			];
			foreach ( $expected_request_body_values as $key => $value ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
				$this->assertSame( $value, $request_args['body'][ $key ] );
			}

			// Assert: the request body contains these keys, without checking for their value.
			$expected_request_body_keys = [
				'description',
				'metadata',
			];
			foreach ( $expected_request_body_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
			}

			// Assert: the body metadata has these values.
			$order_id                 = (string) $renewal_order->get_id();
			$expected_metadata_values = [
				'order_id'     => $order_id,
				'payment_type' => 'recurring',
			];
			foreach ( $expected_metadata_values as $key => $value ) {
				$this->assertArrayHasKey( $key, $request_args['body']['metadata'] );
				$this->assertSame( $value, $request_args['body']['metadata'][ $key ] );
			}

			// Assert: the body metadata has these keys, without checking for their value.
			$expected_metadata_keys = [
				'customer_name',
				'customer_email',
				'site_url',
			];
			foreach ( $expected_metadata_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body']['metadata'] );
			}

			// Assert: the request body does not contains these keys.
			$expected_missing_request_body_keys = [
				'capture', // No need to capture with a payment intent.
				'expand[]',
			];
			foreach ( $expected_missing_request_body_keys as $key ) {
				$this->assertArrayNotHasKey( $key, $request_args['body'] );
			}

			// Arrange: return dummy content as the response.
			return [
				'headers'  => [],
				// Too bad we aren't dynamically setting things 'cus_123abc' when using this file.
				'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_success.json' ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Arrange: Make sure to check that an action we care about was called
		// by hooking into it.
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment',
			[ &$mock_action_process_payment, 'action' ]
		);

		// Act: call process_subscription_payment().
		// We need to use `wc_gateway_stripe` here because we mocked this class earlier.
		$result = $this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, $should_retry, $previous_error );

		// Assert: nothing was returned.
		$this->assertEquals( $result, null );

		// Assert that we saved the payment intent to the order.
		$order_id   = $renewal_order->get_id();
		$order      = wc_get_order( $order_id );
		$order_data = $order->get_meta( '_stripe_intent_id' );

		$this->assertEquals( $order_data, 'pi_123abc' );

		// Transaction ID was saved to order.
		$order_transaction_id = $order->get_transaction_id();
		$this->assertEquals( $order_transaction_id, 'ch_123abc' );

		// Assert: the order was marked as processing (this is done in process_response()).
		$this->assertEquals( $order->get_status(), 'processing' );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $payments_intents_api_endpoint, $urls_used ) );

		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );

		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment' ], $mock_action_process_payment->get_tags() );

		// Clean up.
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request_response_success' ] );
	}

	/**
	 * Overall this test works like this:
	 *
	 * 1. Several things are set up or mocked.
	 * 2. A function that will mock an HTTP response for the payment_intents is created.
	 * 3. That same function has some assertions about the things we send to the
	 * payments_intents endpoint.
	 * 4. The function under test - `process_subscription_payment` - is called.
	 * 5. More assertions are made.
	 */
	public function test_renewal_authorization_required() {
		// Arrange: Some variables we'll use later.
		$renewal_order                 = WC_Helper_Order::create_order();
		$amount                        = 20;
		$stripe_amount                 = WC_Stripe_Helper::get_stripe_amount( $amount );
		$currency                      = strtolower( $renewal_order->get_currency() );
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$should_retry                  = false;
		$previous_error                = false;
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$urls_used                     = [];

		// Arrange: Mock prepare_order_source() so that we have a customer and source.
		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
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

		// Arrange: Add filter that will return a mocked HTTP response for the payment_intent call.
		$pre_http_request_response_callback = function( $preempt, $request_args, $url ) use (
			$renewal_order,
			$stripe_amount,
			$currency,
			$customer,
			$source,
			$payments_intents_api_endpoint,
			&$urls_used
		) {
			// Add all urls to array so we can later make assertions about which endpoints were used.
			array_push( $urls_used, $url );

			// Continue without mocking the request if it's not the endpoint we care about.
			if ( $payments_intents_api_endpoint !== $url ) {
				return false;
			}

			// Arrange: return dummy content as the response.
			return [
				'headers'  => [],
				// Too bad we aren't dynamically setting things 'cus_123abc' when using this file.
				'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_authentication_required.json' ),
				'response' => [
					'code'    => 402,
					'message' => 'Payment Required',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Arrange: Make sure to check that an action we care about was called
		// by hooking into it.
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_authentication_required',
			[ &$mock_action_process_payment, 'action' ]
		);

		// Act: call process_subscription_payment().
		// We need to use `wc_gateway_stripe` here because we mocked this class earlier.
		$result = $this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, $should_retry, $previous_error );

		// Assert: nothing was returned.
		$this->assertEquals( $result, null );

		// Assert that we saved the payment intent to the order.
		$order_id             = $renewal_order->get_id();
		$order                = wc_get_order( $order_id );
		$order_data           = $order->get_meta( '_stripe_intent_id' );
		$order_transaction_id = $order->get_transaction_id();

		// Intent was saved to order even though there was an error in the response body.
		$this->assertEquals( $order_data, 'pi_123abc' );

		// Transaction ID was saved to order.
		$this->assertEquals( $order_transaction_id, 'ch_123abc' );

		// Assert: the order was marked as failed.
		$this->assertEquals( $order->get_status(), 'failed' );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $payments_intents_api_endpoint, $urls_used ) );

		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );

		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_authentication_required' ], $mock_action_process_payment->get_tags() );

		// Clean up.
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request_response_success' ] );
	}
}
