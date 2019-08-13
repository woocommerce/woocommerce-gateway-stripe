<?php
/**
 * These tests assert various things about processing a renewal payment for a WooCommerce Subscription.
 *
 * The responses from HTTP requests are mocked using the WP filter `pre_http_request`.
 *
 * There are a few methods that need to be mocked in the class WC_Stripe_Subs_Compat, which is
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
	private $wc_stripe_subs_compat;

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();

		$this->wc_stripe_subs_compat = $this->getMockBuilder( 'WC_Stripe_Subs_Compat' )
			->disableOriginalConstructor()
			->setMethods( array( 'prepare_order_source', 'has_subscription' ) )
			->getMock();

		// Mocked in order to get metadata[payment_type] = recurring in the HTTP request.
		$this->wc_stripe_subs_compat
			->expects( $this->any() )
			->method( 'has_subscription' )
			->will(
				$this->returnValue( true )
			);
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
		$amount                        = 20;
		$stripe_amount                 = WC_Stripe_Helper::get_stripe_amount( $amount );
		$currency                      = strtolower( $renewal_order->get_currency() );
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$should_retry                  = false;
		$previous_error                = false;
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$urls_used                     = array();

		// Arrange: Mock prepare_order_source() so that we have a customer and source.
		$this->wc_stripe_subs_compat
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->will(
				$this->returnValue(
					(object) array(
						'token_id'      => false,
						'customer'      => $customer,
						'source'        => $source,
						'source_object' => (object) array(),
					)
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
			$expected_request_body_values = array(
				'source'               => $source,
				'amount'               => $stripe_amount,
				'currency'             => $currency,
				'payment_method_types' => array( 'card' ),
				'customer'             => $customer,
				'off_session'          => 'true',
				'confirm'              => 'true',
				'confirmation_method'  => 'automatic',
				// Not mocking 'statement_descriptor' since it's not available during this test.
			);
			foreach ( $expected_request_body_values as $key => $value ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
				$this->assertSame( $value, $request_args['body'][ $key ] );
			}

			// Assert: the request body contains these keys, without checking for their value.
			$expected_request_body_keys = array(
				'description',
				'metadata',
			);
			foreach ( $expected_request_body_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
			}

			// Assert: the body metadata has these values.
			$expected_metadata_values = array(
				'order_id'     => (string) $renewal_order->get_id(),
				'payment_type' => 'recurring',
			);
			foreach ( $expected_metadata_values as $key => $value ) {
				$this->assertArrayHasKey( $key, $request_args['body']['metadata'] );
				$this->assertSame( $value, $request_args['body']['metadata'][ $key ] );
			}

			// Assert: the body metadata has these keys, without checking for their value.
			$expected_metadata_keys = array(
				'customer_name',
				'customer_email',
				'site_url',
			);
			foreach ( $expected_metadata_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body']['metadata'] );
			}

			// Assert: the request body does not contains these keys.
			$expected_missing_request_body_keys = array(
				'capture', // No need to capture with a payment intent.
				'capture_method', // The default ('automatic') is what we want in this case, so we leave it off.
				'expand[]',
			);
			foreach ( $expected_missing_request_body_keys as $key ) {
				$this->assertArrayNotHasKey( $key, $request_args['body'] );
			}

			// Arrange: return dummy content as the response.
			return array(
				'headers'  => array(),
				// Too bad we aren't dynamically setting things 'cus_123abc' when using this file.
				'body'     => file_get_contents( 'tests/phpunit/dummy-data/subscription_renewal_response_success.json' ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Act: call process_subscription_payment().
		// We need to use `wc_stripe_subs_compat` here because we mocked this class earlier.
		$result = $this->wc_stripe_subs_compat->process_subscription_payment( 20, $renewal_order, $should_retry, $previous_error );

		// Assert: nothing was returned.
		$this->assertEquals( $result, null );

		// Assert that we saved the payment intent to the order.
		$order      = wc_get_order( $renewal_order->get_id() );
		$order_data = $order->get_meta( '_stripe_intent_id' );
		$this->assertEquals( $order_data, 'pi_123abc' );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $payments_intents_api_endpoint, $urls_used ) );

		// Clean up.
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request_response_success' ) );
	}
}
