<?php
/**
 * These tests assert various things about processing an initial payment for a WooCommerce Subscriptions.
 *
 * The responses from HTTP requests are mocked using the WP filter `pre_http_request`.
 *
 * There are a few methods that need to be mocked in the class WC_Stripe_Subs_Compat, which is
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
	private $wc_stripe_subs_compat;

	/**
	 * The statement descriptor we'll use in a test.
	 *
	 * @var string
	 */
	private $statement_descriptor;

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();

		$this->wc_stripe_subs_compat = $this->getMockBuilder( 'WC_Stripe_Subs_Compat' )
			->disableOriginalConstructor()
			->setMethods( array( 'prepare_source', 'has_subscription' ) )
			->getMock();

		// Mocked in order to get metadata[payment_type] = recurring in the HTTP request.
		$this->statement_descriptor = 'This is a statement descriptor.';
		update_option(
			'woocommerce_stripe_settings',
			array(
				'statement_descriptor' => $this->statement_descriptor,
			)
		);
	}

	/**
	 * Tears down the stuff we set up.
	 */
	public function tearDown() {
		parent::tearDown();
		delete_option( 'woocommerce_stripe_settings' );
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
		$order_id             = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $initial_order->id : $initial_order->get_id();
		$stripe_amount        = WC_Stripe_Helper::get_stripe_amount( $initial_order->get_total() );
		$currency             = strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $initial_order->get_order_currency() : $initial_order->get_currency() );
		$customer             = 'cus_123abc';
		$source               = 'src_123abc';
		$statement_descriptor = WC_Stripe_Helper::clean_statement_descriptor( $this->statement_descriptor );
		$intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$urls_used            = array();

		if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
			$initial_order->payment_method = 'stripe';
			update_post_meta( $order_id, '_payment_method', 'stripe' ); // for `wc_get_order()`.
		} else {
			$initial_order->set_payment_method( 'stripe' );
			$initial_order->save();
		}

		// Arrange: Mock prepare_source() so that we have a customer and source.
		$this->wc_stripe_subs_compat
			->expects( $this->any() )
			->method( 'prepare_source' )
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

		// Emulate a subscription.
		$this->wc_stripe_subs_compat
			->expects( $this->any() )
			->method( 'has_subscription' )
			->will( $this->returnValue( true ) );

		$pre_http_request_response_callback = function( $preempt, $request_args, $url ) use (
			$stripe_amount,
			$currency,
			$customer,
			$source,
			$intents_api_endpoint,
			$statement_descriptor,
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
			$response = array(
				'headers'  => array(),
				// Too bad we aren't dynamically setting things 'cus_123abc' when using this file.
				'body'     => file_get_contents( 'tests/phpunit/dummy-data/subscription_signup_response_success.json' ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);

			// Respond with a successfull intent for confirmations.
			if ( $url !== $intents_api_endpoint ) {
				$response['body'] = str_replace( 'requires_confirmation', 'succeeded', $response['body'] );
				return $response;
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
				'statement_descriptor' => $statement_descriptor,
				'customer'             => $customer,
				'setup_future_usage'   => 'off_session',
				'payment_method_types' => array( 'card' ),
			);
			foreach ( $expected_request_body_values as $key => $value ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
				$this->assertSame( $value, $request_args['body'][ $key ] );
			}

			// Assert: the request body contains these keys, without checking for their value.
			$expected_request_body_keys = array(
				'description',
				'capture_method',
			);
			foreach ( $expected_request_body_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body'] );
			}

			// Assert: the body metadata contains the order ID.
			$this->assertSame( $order_id, absint( $request_args['body']['metadata']['order_id'] ) );

			// // Assert: the body metadata has these keys, without checking for their value.
			$expected_metadata_keys = array(
				'customer_name',
				'customer_email',
			);
			foreach ( $expected_metadata_keys as $key ) {
				$this->assertArrayHasKey( $key, $request_args['body']['metadata'] );
			}

			// Return dummy content as the response.
			return $response;
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Act: call process_subscription_payment().
		// We need to use `wc_stripe_subs_compat` here because we mocked this class earlier.
		$result = $this->wc_stripe_subs_compat->process_payment( $order_id );

		// Assert: nothing was returned.
		$this->assertEquals( $result['result'], 'success' );
		$this->assertArrayHasKey( 'redirect', $result );

		$order      = wc_get_order( $order_id );
		$order_data = (
			WC_Stripe_Helper::is_wc_lt( '3.0' )
				? get_post_meta( $order_id, '_stripe_intent_id', true )
				: $order->get_meta( '_stripe_intent_id' )
		);

		$this->assertEquals( $order_data, 'pi_123abc' );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $intents_api_endpoint, $urls_used, true ) );

		// Clean up.
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request_response_success' ) );
	}
}
