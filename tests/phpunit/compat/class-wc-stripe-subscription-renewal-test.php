<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * These tests assert various things about processing a renewal payment for a WooCommerce Subscription.
 *
 * The responses from HTTP requests are mocked using the WP filter `pre_http_request`.
 *
 * There are a few methods that need to be mocked in the class WC_Stripe_UPE_Payment_Gateway, which is
 * why that class is mocked even though the method under test is part of that class.
 *
 * @package     WooCommerce_Stripe/WC_Stripe_Subscription_Renewal
 *
 * WC_Stripe_Subscription_Renewal_Test
 */
class WC_Stripe_Subscription_Renewal_Test extends WP_UnitTestCase {
	/**
	 * System under test, and a mock object with some methods mocked for testing
	 *
	 * @var \WC_Stripe_UPE_Payment_Gateway
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

		// The order-helper singleton is process-global. Renewal lock tests require the real
		// helper so lock metadata is persisted regardless of prior test state.
		WC_Stripe_Order_Helper::set_instance( null );

		// WC_Stripe_API::retrieve() returns null once the consecutive-401 counter reaches its
		// threshold, and earlier test class trip it with their mocked API keys.
		// TODO: The proper fix is to remove any non stubbed call to Stripe; which is also the
		// correct implementation for a phpunit test.
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY, 'test' );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY, 'live' );

		$this->wc_gateway_stripe = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'prepare_order_source', 'has_subscription' ] )
			->getMock();

		// Mocked in order to get metadata[payment_type] = recurring in the HTTP request.
		$this->wc_gateway_stripe
			->method( 'has_subscription' )
			->willReturn( true );

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
		// The tests in this file do not mock ALL the calls to the Stripe API, and as we use mocked API keys they trigger the 401 rate-limiter,
		// this is not a problem for these tests as they don't depend on the reponses.
		//
		// The delete must happen while the Stripe settings still exist: the cache key is
		// prefixed with the current mode (test/live), which is derived from the settings, so
		// deleting the settings first makes this target a different key and the counter then
		// leaks across tests until the rate limiter blocks all API reads.
		//
		// TODO: Remove this once we've mocked all calls to the Stripe API (either using the pre_http_request filter, or by using a mocked WC_Stripe_API class).
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		WC_Stripe_Helper::delete_main_stripe_settings();
		WC_Stripe_Order_Helper::set_instance( null );

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
		$pre_http_request_response_callback = function (
			$preempt,
			$request_args,
			$url
		) use (
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
			'wc_gateway_stripe_process_payment_charge',
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
		$order_data = WC_Stripe_Order_Helper::get_instance()->get_stripe_intent_id( $order );

		$this->assertEquals( $order_data, 'pi_123abc' );

		// Transaction ID was saved to order.
		$order_transaction_id = $order->get_transaction_id();
		$this->assertEquals( $order_transaction_id, 'ch_123abc' );

		// Assert: the order was marked as processing (this is done in process_response()).
		$this->assertEquals( $order->get_status(), OrderStatus::PROCESSING );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $payments_intents_api_endpoint, $urls_used ) );

		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );

		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_charge' ], $mock_action_process_payment->get_tags() );

		// Clean up.
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request_response_success' ] );
	}

	/**
	 * @dataProvider provide_locked_subscription_renewal_payment_methods
	 */
	public function test_renewal_returns_without_charging_when_payment_lock_is_held( $gateway_id, $expected_api_endpoint ) {
		$renewal_order         = WC_Helper_Order::create_order();
		$api_requests          = [];
		$processing_started_at = time();

		$renewal_order->set_payment_method( $gateway_id );
		$renewal_order->save();

		$this->wc_gateway_stripe->id = $gateway_id;

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->lock_order_payment( $renewal_order );
		$existing_lock = $order_helper->get_order_existing_payment_lock( $renewal_order );

		// Spy on the logger to assert the payment-lock acquisition/verification error is emitted. Logging settings
		// are deliberately left at their defaults: the skip is logged at error level exactly so
		// that it leaves a trace even when debug logging is disabled.
		$previous_logger = WC_Stripe_Logger::$logger;
		$logged_errors   = [];
		$logger          = $this->getMockBuilder( WC_Logger::class )->disableOriginalConstructor()->getMock();
		$logger->method( 'error' )->willReturnCallback(
			function ( $message, $context = [] ) use ( &$logged_errors ) {
				$logged_errors[] = $message;
			}
		);
		WC_Stripe_Logger::$logger = $logger;

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $expected_api_endpoint, &$api_requests ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				return $preempt;
			}

			$api_requests[] = $url;

			return new WP_Error( 'unexpected_stripe_request', "No Stripe request was expected for the locked {$expected_api_endpoint} renewal." );
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			WC_Stripe_Logger::$logger = $previous_logger;
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( [], $api_requests );
		$this->assertSame( (string) $existing_lock, (string) $order_helper->get_order_existing_payment_lock( $renewal_order ) );
		$this->assertGreaterThan( $processing_started_at, (int) $existing_lock );
		$this->assertLessThanOrEqual( time() + 5 * MINUTE_IN_SECONDS, (int) $existing_lock );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
		$this->assertSame( '', $renewal_order->get_transaction_id() );

		$order_id        = (string) $renewal_order->get_id();
		$matching_errors = array_filter(
			$logged_errors,
			function ( $message ) use ( $order_id ) {
				return str_contains( $message, "skipping renewal attempt for order {$order_id}" )
					&& str_contains( $message, 'could not be acquired or verified' );
			}
		);
		$this->assertNotEmpty( $matching_errors, 'Expected a payment-lock acquisition error mentioning the order id.' );

		// The skip must also leave a merchant-visible trail on the order itself.
		$notes          = wc_get_order_notes( [ 'order_id' => $renewal_order->get_id() ] );
		$matching_notes = array_filter(
			wp_list_pluck( $notes, 'content' ),
			function ( $content ) {
				return str_contains( $content, 'could not be acquired or verified' )
					&& str_contains( $content, 'may already be in progress' );
			}
		);
		$this->assertNotEmpty( $matching_notes, 'Expected an order note explaining the skipped renewal attempt.' );

		$order_helper->unlock_order_payment( $renewal_order );
	}

	public function provide_locked_subscription_renewal_payment_methods() {
		return [
			'payment intents renewal' => [
				'stripe',
				'https://api.stripe.com/v1/payment_intents',
			],
			'legacy SEPA renewal'     => [
				'stripe_sepa',
				'https://api.stripe.com/v1/charges',
			],
		];
	}

	/**
	 * @dataProvider provide_invalid_subscription_payment_locks
	 */
	public function test_renewal_stops_when_acquired_payment_lock_is_invalid( $invalid_lock ) {
		$renewal_order = WC_Helper_Order::create_order();
		$api_requests  = [];
		$logged_errors = [];

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->expects( $this->once() )->method( 'acquire_order_payment_lock' )->willReturn( $invalid_lock );
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->expects( $this->never() )->method( 'unlock_order_payment_if_owned' );

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		$previous_logger = WC_Stripe_Logger::$logger;
		$logger          = $this->getMockBuilder( WC_Logger::class )->disableOriginalConstructor()->getMock();
		$logger->method( 'error' )->willReturnCallback(
			function ( $message, $context = [] ) use ( &$logged_errors ) {
				$logged_errors[] = $message;
			}
		);
		WC_Stripe_Logger::$logger = $logger;

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$api_requests ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				return $preempt;
			}

			$api_requests[] = $url;

			return new WP_Error( 'unexpected_stripe_request', 'No Stripe request was expected with an invalid acquired lock.' );
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			WC_Stripe_Logger::$logger = $previous_logger;
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$this->assertSame( [], $api_requests );
		$this->assertNotEmpty(
			array_filter(
				$logged_errors,
				function ( $message ) {
					return str_contains( $message, 'acquired payment lock is invalid' );
				}
			),
			'Expected an error explaining that renewal processing stopped on an invalid payment lock.'
		);

		$notes = wc_get_order_notes( [ 'order_id' => $renewal_order->get_id() ] );
		$this->assertNotEmpty(
			array_filter(
				wp_list_pluck( $notes, 'content' ),
				function ( $content ) {
					return str_contains( $content, 'payment lock could not be verified' );
				}
			),
			'Expected an order note explaining why the renewal was not processed.'
		);
	}

	public function provide_invalid_subscription_payment_locks() {
		return [
			'missing lock'      => [ '' ],
			'null lock'         => [ null ],
			'nonnumeric lock'   => [ 'not-a-timestamp' ],
			'negative integer'  => [ -1 ],
			'negative string'   => [ '-1' ],
			'floating point'    => [ 1.5 ],
			'array lock'        => [ [] ],
			'zero integer lock' => [ 0 ],
			'zero string lock'  => [ '0' ],
		];
	}

	/**
	 * @dataProvider provide_malformed_preexisting_subscription_payment_locks
	 */
	public function test_renewal_stops_safely_on_malformed_preexisting_payment_lock( $malformed_lock ) {
		$renewal_order = WC_Helper_Order::create_order();
		$request_count = 0;

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->update_meta_data( '_stripe_lock_payment', $malformed_lock );
		$renewal_order->save();

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count ) {
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				++$request_count;
				return new WP_Error( 'unexpected_stripe_request', 'No Stripe request was expected.' );
			}

			return $preempt;
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 0, $request_count );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
		$this->assertEquals( $malformed_lock, WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	public function provide_malformed_preexisting_subscription_payment_locks() {
		return [
			'empty array'     => [ [] ],
			'non-empty array' => [ [ time() + 5 * MINUTE_IN_SECONDS ] ],
			'object'          => [ (object) [ 'expires_at' => time() + 5 * MINUTE_IN_SECONDS ] ],
		];
	}

	public function test_renewal_does_not_hide_unrelated_type_error_during_lock_acquisition() {
		$renewal_order = WC_Helper_Order::create_order();
		$caught        = null;

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->method( 'acquire_order_payment_lock' )->willThrowException( new TypeError( 'Unexpected storage failure.' ) );
		$order_helper_mock->expects( $this->never() )->method( 'unlock_order_payment_if_owned' );

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} catch ( TypeError $error ) {
			$caught = $error;
		} finally {
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$this->assertInstanceOf( TypeError::class, $caught );
		$this->assertSame( 'Unexpected storage failure.', $caught->getMessage() );
	}

	public function test_renewal_honors_active_legacy_payment_lock_format() {
		$renewal_order = WC_Helper_Order::create_order();
		$legacy_lock   = ( time() + 5 * MINUTE_IN_SECONDS ) . '|pi_existing';

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->update_meta_data( '_stripe_lock_payment', $legacy_lock );
		$renewal_order->save();

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( $legacy_lock, WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
	}

	/**
	 * @dataProvider provide_recoverable_stale_subscription_payment_locks
	 */
	public function test_renewal_replaces_recoverable_stale_payment_lock( $stale_lock ) {
		$renewal_order = WC_Helper_Order::create_order();
		$caught        = null;

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->update_meta_data( '_stripe_lock_payment', $stale_lock );
		$renewal_order->save();

		$this->wc_gateway_stripe
			->expects( $this->once() )
			->method( 'prepare_order_source' )
			->willThrowException( new RuntimeException( 'Reached renewal processing.' ) );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} catch ( RuntimeException $error ) {
			$caught = $error;
		}

		$this->assertInstanceOf( RuntimeException::class, $caught );
		$this->assertSame( 'Reached renewal processing.', $caught->getMessage() );
		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( wc_get_order( $renewal_order->get_id() ) ) );
	}

	public function provide_recoverable_stale_subscription_payment_locks() {
		return [
			'expired legacy lock' => [ ( time() - MINUTE_IN_SECONDS ) . '|pi_old' ],
			'stale scalar lock'   => [ 'not-a-timestamp' ],
		];
	}

	public function test_renewal_retry_acquires_the_lock_only_once() {
		$renewal_order                 = WC_Helper_Order::create_order();
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$request_count                 = 0;
		$locks_during_requests         = [];

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->set_gateway_retry_interval( 0 );

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		// A single lock must cover the retry chain without a release/re-acquire gap. Only
		// lock/unlock are mocked; the rest of the helper runs real so processing still completes.
		$real_order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper_spy  = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'unlock_order_payment_if_owned' ] )
			->getMock();
		$order_helper_spy->expects( $this->once() )
			->method( 'acquire_order_payment_lock' )
			->willReturnCallback( [ $real_order_helper, 'acquire_order_payment_lock' ] );
		$order_helper_spy->expects( $this->once() )
			->method( 'unlock_order_payment_if_owned' )
			->willReturnCallback( [ $real_order_helper, 'unlock_order_payment_if_owned' ] );

		$original_instance = $real_order_helper;
		WC_Stripe_Order_Helper::set_instance( $order_helper_spy );

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $payments_intents_api_endpoint, &$request_count, &$locks_during_requests, $real_order_helper, $renewal_order ) {
			if ( $payments_intents_api_endpoint !== $url ) {
				if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
					return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe request during renewal retry.' );
				}

				return $preempt;
			}

			++$request_count;
			$locks_during_requests[] = $real_order_helper->get_order_existing_payment_lock( wc_get_order( $renewal_order->get_id() ) );

			if ( 1 === $request_count ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'error' => [
								'type'    => 'api_error',
								'message' => 'Temporary Stripe API error.',
							],
						]
					),
					'response' => [
						'code'    => 400,
						'message' => 'Bad Request',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return [
				'headers'  => [],
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

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, true, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 2, $request_count );
		$this->assertCount( 2, $locks_during_requests );
		$this->assertNotEmpty( $locks_during_requests[0] );
		$this->assertSame( (string) $locks_during_requests[0], (string) $locks_during_requests[1] );
		$this->assertSame( OrderStatus::PROCESSING, $renewal_order->get_status() );
		$this->assertEmpty( $real_order_helper->get_order_existing_payment_lock( $renewal_order ) );
	}

	public function test_renewal_cleans_up_idempotency_filter_and_retry_interval_after_attempt() {
		$renewal_order = WC_Helper_Order::create_order();

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->set_gateway_retry_interval( 1 );

		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type'   => WC_Stripe_Payment_Methods::CARD,
						'status' => 'chargeable',
					],
					'payment_method' => null,
				]
			);

		$retry_error      = [
			'error' => [
				'type'    => 'idempotency_error',
				'message' => 'Keys for idempotent requests can only be used with the same parameters they were first used with.',
			],
		];
		$request_count    = 0;
		$idempotency_keys = [];

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count, &$idempotency_keys, $retry_error ) {
			if ( 'https://api.stripe.com/v1/payment_intents' !== $url ) {
				return $preempt;
			}

			++$request_count;
			$idempotency_keys[] = $request_args['headers']['Idempotency-Key'] ?? null;

			if ( 1 === $request_count ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $retry_error ),
					'response' => [
						'code'    => 400,
						'message' => 'Bad Request',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return [
				'headers'  => [],
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

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, true, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		}

		$this->assertSame( 2, $request_count );
		$this->assertSame( $renewal_order->get_id() . '-2-src_123abc', $idempotency_keys[1] );
		$this->assertFalse( has_filter( 'wc_stripe_idempotency_key' ) );
		$this->assertSame( 1, $this->get_gateway_retry_interval() );
	}

	public function test_renewal_holds_payment_lock_until_response_is_processed() {
		$renewal_order         = WC_Helper_Order::create_order();
		$customer              = 'cus_123abc';
		$source                = 'src_123abc';
		$processing_started_at = time();

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) {
			if ( 'https://api.stripe.com/v1/payment_intents' !== $url ) {
				if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
					return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe request while testing response processing.' );
				}

				return $preempt;
			}

			return [
				'headers'  => [],
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

		// process_response() fires wc_gateway_stripe_process_payment_charge while the renewal
		// response is being recorded. Capture the lock state at that moment to prove it is
		// still held until processing completes (guards the duplicate-charge window).
		$order_helper           = WC_Stripe_Order_Helper::get_instance();
		$lock_during_processing = null;
		$capture_lock           = function () use ( $order_helper, $renewal_order, &$lock_during_processing ) {
			$lock_during_processing = $order_helper->get_order_existing_payment_lock( wc_get_order( $renewal_order->get_id() ) );
		};
		add_action( 'wc_gateway_stripe_process_payment_charge', $capture_lock );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_charge', $capture_lock );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		// The lock must still be held while the successful charge is processed...
		$this->assertGreaterThan( $processing_started_at, (int) $lock_during_processing );
		$this->assertLessThanOrEqual( time() + 5 * MINUTE_IN_SECONDS, (int) $lock_during_processing );
		// ...and released once processing has completed.
		$this->assertEmpty( $order_helper->get_order_existing_payment_lock( $renewal_order ) );
		// Sanity: the renewal actually succeeded.
		$this->assertSame( OrderStatus::PROCESSING, $renewal_order->get_status() );
	}

	public function test_renewal_releases_lock_on_unexpected_error() {
		$renewal_order = WC_Helper_Order::create_order();

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		// Throw a non-WC_Stripe_Exception from within the charge, after the lock is acquired.
		$thrower = function ( $preempt, $request_args, $url ) {
			if ( 'https://api.stripe.com/v1/payment_intents' === $url ) {
				throw new RuntimeException( 'Unexpected failure during the renewal charge.' );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $thrower, 10, 3 );

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$caught       = null;
		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} catch ( \Throwable $e ) {
			$caught = $e;
		} finally {
			remove_filter( 'pre_http_request', $thrower, 10 );
		}

		// The unexpected error is re-thrown so existing failure handling is preserved...
		$this->assertInstanceOf( RuntimeException::class, $caught );
		// ...and the payment lock is released, so a later retry is not blocked.
		$this->assertEmpty( $order_helper->get_order_existing_payment_lock( wc_get_order( $renewal_order->get_id() ) ) );
	}

	public function test_renewal_does_not_start_payment_when_acquired_lock_is_expired() {
		$renewal_order                 = WC_Helper_Order::create_order();
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$request_count                 = 0;

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		// Even a structurally valid acquired lock cannot protect a Stripe request after expiry.
		$expired_lock = ( time() - 10 ) . '|' . wp_generate_uuid4();

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'is_order_payment_lock_owned', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->method( 'acquire_order_payment_lock' )->willReturn( $expired_lock );
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->method( 'is_order_payment_lock_owned' )->willReturn( true );
		$order_helper_mock->method( 'unlock_order_payment_if_owned' );

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $payments_intents_api_endpoint, &$request_count ) {
			if ( $payments_intents_api_endpoint !== $url ) {
				if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
					++$request_count;
					return new WP_Error( 'unexpected_stripe_request', 'No Stripe request was expected with an expired acquired lock.' );
				}

				return $preempt;
			}

			++$request_count;

			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'error' => [
							'type'    => 'api_error',
							'message' => 'Temporary Stripe API error.',
						],
					]
				),
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, true, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 0, $request_count );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
		$this->assertSame( '', $renewal_order->get_transaction_id() );
	}

	public function test_renewal_does_not_start_payment_without_enough_lock_time_for_the_complete_stripe_request_chain() {
		$renewal_order    = WC_Helper_Order::create_order();
		$request_count    = 0;
		$error_hook_count = 0;
		$short_lock       = ( time() + WC_Stripe_API::REQUEST_TIMEOUT + 10 ) . '|' . wp_generate_uuid4();

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe
			->expects( $this->once() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'is_order_payment_lock_owned', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->method( 'acquire_order_payment_lock' )->willReturn( $short_lock );
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->method( 'is_order_payment_lock_owned' )->willReturn( true );
		$order_helper_mock->expects( $this->once() )->method( 'unlock_order_payment_if_owned' );

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count ) {
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				++$request_count;
				return new WP_Error( 'unexpected_stripe_request', 'No Stripe request was expected with insufficient lock time.' );
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		$error_hook = function () use ( &$error_hook_count ) {
			++$error_hook_count;
		};
		add_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10, 2 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10 );
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );
		$note_contents = wp_list_pluck( wc_get_order_notes( [ 'order_id' => $renewal_order->get_id() ] ), 'content' );

		$this->assertSame( 0, $request_count );
		$this->assertSame( 1, $error_hook_count );
		$this->assertSame( OrderStatus::FAILED, $renewal_order->get_status() );
		$this->assertSame( '', $renewal_order->get_transaction_id() );
		$this->assertStringContainsString( 'too little time remained on its payment lock', implode( '\n', $note_contents ) );
	}

	/**
	 * @dataProvider provide_payment_lock_states_after_retry_backoff
	 */
	public function test_renewal_stops_retrying_when_payment_lock_loses_request_coverage_during_backoff_sleep( $replace_lock_during_sleep, $expected_status ) {
		$renewal_order                 = WC_Helper_Order::create_order();
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$request_count                 = 0;
		$error_hook_count              = 0;
		$error_hook_messages           = [];

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		// A 3-second backoff leaves less than the full four-request timeout budget on this lock.
		// The first request is covered, while the retry must stop after the sleep.
		$this->set_gateway_retry_interval( 3 );
		$lock_losing_coverage_during_sleep = null;
		$replace_lock_at                   = null;
		$replacement_lock                  = ( time() + 10 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'is_order_payment_lock_owned', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->method( 'acquire_order_payment_lock' )
			->willReturnCallback(
				function () use ( &$lock_losing_coverage_during_sleep, &$replace_lock_at ) {
					$lock_losing_coverage_during_sleep = ( time() + 4 * WC_Stripe_API::REQUEST_TIMEOUT + 2 ) . '|' . wp_generate_uuid4();
					$replace_lock_at                   = time() + 2;
					return $lock_losing_coverage_during_sleep;
				}
			);
		$order_helper_mock->method( 'is_order_payment_lock_owned' )
			->willReturnCallback(
				function ( $order, $expected_lock ) use ( &$lock_losing_coverage_during_sleep, &$replace_lock_at, $replace_lock_during_sleep, $replacement_lock ) {
					$current_lock = $replace_lock_during_sleep && time() >= $replace_lock_at
						? $replacement_lock
						: $lock_losing_coverage_during_sleep;

					return $expected_lock === $current_lock;
				}
			);
		if ( $replace_lock_during_sleep ) {
			$order_helper_mock->expects( $this->never() )->method( 'unlock_order_payment_if_owned' );
		} else {
			$order_helper_mock->expects( $this->once() )->method( 'unlock_order_payment_if_owned' );
		}

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $payments_intents_api_endpoint, &$request_count ) {
			if ( $payments_intents_api_endpoint !== $url ) {
				if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
					return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe request during renewal backoff.' );
				}

				return $preempt;
			}

			++$request_count;

			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'error' => [
							'type'    => 'api_error',
							'message' => 'Temporary Stripe API error.',
						],
					]
				),
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		$error_hook = function ( $exception, $order ) use ( &$error_hook_count, &$error_hook_messages, $renewal_order ) {
			++$error_hook_count;
			$error_hook_messages[] = $exception->getLocalizedMessage();
			$this->assertInstanceOf( WC_Stripe_Exception::class, $exception );
			$this->assertSame( $renewal_order->get_id(), $order->get_id() );
		};
		add_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10, 2 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, true, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10 );
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		// The lock no longer covered a full Stripe request after backoff, so no second attempt was
		// started. If another worker replaced it, this worker must also avoid overwriting that
		// worker's order state.
		$this->assertSame( 1, $request_count );
		$this->assertSame( 1, $error_hook_count );
		$this->assertCount( 1, $error_hook_messages );
		$this->assertStringContainsString( 'Temporary Stripe API error.', $error_hook_messages[0] );
		$this->assertStringNotContainsString( 'too little time remained', $error_hook_messages[0] );
		$this->assertSame( $expected_status, $renewal_order->get_status() );
	}

	public function provide_payment_lock_states_after_retry_backoff() {
		return [
			'unchanged lock with insufficient request coverage' => [ false, OrderStatus::FAILED ],
			'replaced lock'                                     => [ true, OrderStatus::PENDING ],
		];
	}

	/**
	 * @dataProvider provide_replacement_subscription_payment_locks
	 */
	public function test_renewal_does_not_start_or_release_payment_after_lock_is_replaced( $foreign_lock ) {
		$renewal_order = WC_Helper_Order::create_order();
		$request_count = 0;

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$our_lock     = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$current_lock = '';

		$this->wc_gateway_stripe
			->expects( $this->once() )
			->method( 'prepare_order_source' )
			->willReturnCallback(
				function () use ( &$current_lock, $foreign_lock ) {
					$current_lock = $foreign_lock;

					return (object) [
						'token_id'       => false,
						// Deliberately invalid: losing the lock during source preparation must
						// be observed before local source validation can fail the order.
						'customer'       => '',
						'source'         => 'src_123abc',
						'source_object'  => (object) [
							'type' => WC_Stripe_Payment_Methods::CARD,
						],
						'payment_method' => null,
					];
				}
			);

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'is_order_payment_lock_owned', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->method( 'acquire_order_payment_lock' )
			->willReturnCallback(
				function () use ( &$current_lock, $our_lock ) {
					$current_lock = $our_lock;
					return $our_lock;
				}
			);
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->method( 'is_order_payment_lock_owned' )
			->willReturnCallback(
				function ( $order, $expected_lock ) use ( &$current_lock ) {
					return is_scalar( $current_lock ) && (string) $current_lock === $expected_lock;
				}
			);
		$order_helper_mock->expects( $this->never() )->method( 'unlock_order_payment_if_owned' );

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count ) {
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				++$request_count;
				return new WP_Error( 'unexpected_stripe_request', 'No Stripe request was expected.' );
			}

			return $preempt;
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 0, $request_count );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
	}

	public function provide_replacement_subscription_payment_locks() {
		return [
			'newer timestamp'  => [ (string) ( time() + 10 * MINUTE_IN_SECONDS ) ],
			'malformed array'  => [ [] ],
			'malformed object' => [ new stdClass() ],
		];
	}

	public function test_renewal_does_not_adopt_a_lock_replaced_immediately_after_acquisition() {
		$renewal_order    = WC_Helper_Order::create_order();
		$our_lock         = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$replacement_lock = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		$current_lock     = '';

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe->expects( $this->never() )->method( 'prepare_order_source' );

		$order_helper_mock = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'acquire_order_payment_lock', 'is_order_payment_lock_owned', 'unlock_order_payment_if_owned', 'get_order_existing_payment_lock' ] )
			->getMock();
		$order_helper_mock->method( 'get_order_existing_payment_lock' )->willReturn( '' );
		$order_helper_mock->method( 'acquire_order_payment_lock' )
			->willReturnCallback(
				function () use ( &$current_lock, $our_lock, $replacement_lock ) {
					$current_lock = $replacement_lock;
					return $our_lock;
				}
			);
		$order_helper_mock->method( 'is_order_payment_lock_owned' )
			->willReturnCallback(
				function ( $order, $expected_lock ) use ( &$current_lock ) {
					return $expected_lock === $current_lock;
				}
			);
		$order_helper_mock->expects( $this->never() )->method( 'unlock_order_payment_if_owned' );

		$original_instance = WC_Stripe_Order_Helper::get_instance();
		WC_Stripe_Order_Helper::set_instance( $order_helper_mock );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			WC_Stripe_Order_Helper::set_instance( $original_instance );
		}

		$this->assertSame( $replacement_lock, $current_lock );
	}

	public function test_renewal_does_not_start_payment_when_fallback_filter_replaces_lock() {
		$renewal_order    = WC_Helper_Order::create_order();
		$replacement_lock = (string) ( time() + 10 * MINUTE_IN_SECONDS );
		$request_count    = 0;
		$filter_count     = 0;

		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$use_default_source_filter          = function ( $use_default_source ) use ( &$filter_count, $renewal_order, $replacement_lock ) {
			++$filter_count;
			// Write through a separately loaded order to model another worker. The renewal's
			// original order object must force-refresh metadata to observe the replacement.
			$replacement_writer = wc_get_order( $renewal_order->get_id() );
			$replacement_writer->update_meta_data( '_stripe_lock_payment', $replacement_lock );
			$replacement_writer->save_meta_data();

			return $use_default_source;
		};
		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count ) {
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				++$request_count;
				return new WP_Error( 'unexpected_stripe_request', 'No Stripe request was expected after the lock changed.' );
			}

			return $preempt;
		};
		$previous_error                     = (object) [
			'type'    => 'invalid_request_error',
			'message' => 'No such source: src_old',
		];

		add_filter( 'wc_stripe_use_default_customer_source', $use_default_source_filter );
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, $previous_error );
		} finally {
			remove_filter( 'wc_stripe_use_default_customer_source', $use_default_source_filter );
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 1, $filter_count );
		$this->assertSame( 0, $request_count );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
		$this->assertSame( $replacement_lock, (string) WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	public function test_renewal_sepa_success_processes_charge_response() {
		$renewal_order         = WC_Helper_Order::create_order();
		$customer              = 'cus_123abc';
		$source                = 'src_123abc';
		$charges_api           = 'https://api.stripe.com/v1/charges';
		$requested             = [];
		$lock_during_request   = null;
		$processing_started_at = time();

		$renewal_order->set_payment_method( 'stripe_sepa' );
		$renewal_order->save();

		$this->wc_gateway_stripe->id = 'stripe_sepa';

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::SEPA,
					],
					'payment_method' => null,
				]
			);

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $charges_api, &$requested, $renewal_order, $order_helper, &$lock_during_request ) {
			if ( ! str_starts_with( $url, $charges_api ) ) {
				if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
					return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe request during SEPA renewal.' );
				}

				return $preempt;
			}

			$requested[]         = $url;
			$lock_during_request = $order_helper->get_order_existing_payment_lock( wc_get_order( $renewal_order->get_id() ) );

			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'id'                     => 'ch_123abc',
						'object'                 => 'charge',
						'captured'               => true,
						'paid'                   => true,
						'status'                 => 'succeeded',
						'currency'               => strtolower( $renewal_order->get_currency() ),
						'payment_method_details' => [
							'type' => WC_Stripe_Payment_Methods::SEPA,
						],
					]
				),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		// The legacy SEPA charges endpoint was used under the order lock, the charge response was
		// carried through process_response() to completion, and the lock was released afterward.
		$this->assertCount( 1, $requested );
		$this->assertGreaterThan( $processing_started_at, (int) $lock_during_request );
		$this->assertLessThanOrEqual( time() + 5 * MINUTE_IN_SECONDS, (int) $lock_during_request );
		$this->assertSame( OrderStatus::PROCESSING, $renewal_order->get_status() );
		$this->assertEmpty( $order_helper->get_order_existing_payment_lock( $renewal_order ) );
	}

	public function test_renewal_rejects_list_shaped_sepa_response() {
		$renewal_order          = WC_Helper_Order::create_order();
		$processed_charge_count = 0;
		$caught                 = null;

		$renewal_order->set_payment_method( 'stripe_sepa' );
		$renewal_order->save();

		$this->wc_gateway_stripe->id = 'stripe_sepa';
		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::SEPA,
					],
					'payment_method' => null,
				]
			);

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/charges' ) ) {
				return $preempt;
			}

			return [
				'headers'  => [],
				'body'     => wp_json_encode( [] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};
		$capture_processed_charge           = function () use ( &$processed_charge_count ) {
			++$processed_charge_count;
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		add_action( 'wc_gateway_stripe_process_payment_charge', $capture_processed_charge );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} catch ( \Throwable $e ) {
			$caught = $e;
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_charge', $capture_processed_charge );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertNull( $caught, 'A malformed Stripe response should be rejected without leaking an error from process_response().' );
		$this->assertSame( 0, $processed_charge_count );
		$this->assertSame( OrderStatus::FAILED, $renewal_order->get_status() );
		$this->assertSame( '', $renewal_order->get_transaction_id() );
		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	public function test_renewal_resolves_string_latest_charge_before_processing_response() {
		$renewal_order = WC_Helper_Order::create_order();
		$urls_used     = [];

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		// Build an intent response whose charges list holds a bare charge id instead of a
		// charge object — the string case get_latest_charge_from_intent() passes through.
		// process_response() reads the charge via object property access, so the renewal flow
		// must resolve the id into the full charge object before handing it over.
		$intent_response = json_decode( file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_success.json' ), true );
		$charge_object   = $intent_response['charges']['data'][0];

		$intent_response['charges']['data'] = [ 'ch_123abc' ];

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $intent_response, $charge_object, &$urls_used ) {
			$urls_used[] = $url;

			if ( 'https://api.stripe.com/v1/payment_intents' === $url ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $intent_response ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( 'https://api.stripe.com/v1/charges/ch_123abc' === $url ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $charge_object ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return $preempt;
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		// The bare id was resolved via the charges endpoint and the renewal completed with the
		// resolved charge recorded on the order.
		$this->assertContains( 'https://api.stripe.com/v1/charges/ch_123abc', $urls_used );
		$this->assertSame( OrderStatus::PROCESSING, $renewal_order->get_status() );
		$this->assertSame( 'ch_123abc', $renewal_order->get_transaction_id() );
	}

	/**
	 * A charge-enrichment failure retains the established renewal failure behavior rather
	 * than handing extension hooks an incomplete synthetic Charge object.
	 */
	public function test_renewal_charge_enrichment_failure_uses_established_failure_path() {
		$renewal_order       = WC_Helper_Order::create_order();
		$payment_requests    = 0;
		$charge_get_requests = 0;
		$fee_get_requests    = 0;
		$error_hook_count    = 0;
		$error_hook_messages = [];

		$renewal_order->set_payment_method( 'stripe' );
		$renewal_order->save();

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$intent_response                    = json_decode( file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_success.json' ), true );
		$intent_response['charges']['data'] = [];
		$intent_response['latest_charge']   = 'ch_123abc';

		$successful_charge = json_decode( file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_success.json' ), true )['charges']['data'][0];

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $intent_response, $successful_charge, &$payment_requests, &$charge_get_requests, &$fee_get_requests ) {
			if ( 'https://api.stripe.com/v1/payment_intents' === $url ) {
				++$payment_requests;
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $intent_response ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( 'https://api.stripe.com/v1/charges/ch_123abc' === $url ) {
				++$charge_get_requests;
				if ( 1 < $charge_get_requests ) {
					return [
						'headers'  => [],
						'body'     => wp_json_encode( $successful_charge ),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'cookies'  => [],
						'filename' => null,
					];
				}

				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'error' => [
								'type'    => 'api_error',
								'message' => 'Charge details are temporarily unavailable.',
							],
						]
					),
					'response' => [
						'code'    => 500,
						'message' => 'Server Error',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( 'https://api.stripe.com/v1/balance/history/txn_123abc' === $url ) {
				++$fee_get_requests;
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'       => 'txn_123abc',
							'fee'      => 100,
							'net'      => 1900,
							'currency' => 'gbp',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return $preempt;
		};
		$error_hook                         = function ( $exception ) use ( &$error_hook_count, &$error_hook_messages ) {
			++$error_hook_count;
			$error_hook_messages[] = [ $exception->getMessage(), $exception->getLocalizedMessage() ];
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		add_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10, 2 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );
		$note_contents = wp_list_pluck( wc_get_order_notes( [ 'order_id' => $renewal_order->get_id() ] ), 'content' );

		$this->assertSame( 1, $payment_requests );
		$this->assertSame( 1, $charge_get_requests );
		$this->assertSame( 0, $fee_get_requests );
		$this->assertSame( 1, $error_hook_count );
		$this->assertCount( 1, $error_hook_messages );
		$this->assertStringContainsString( 'temporarily unavailable', $error_hook_messages[0][1] );
		$this->assertSame( OrderStatus::FAILED, $renewal_order->get_status() );
		$this->assertSame( '', $renewal_order->get_transaction_id() );
		$this->assertStringNotContainsString( 'recorded using Charge ID', implode( '\n', $note_contents ) );
		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	/**
	 * UPE payment-method objects proxy intent creation to a separate main gateway. Retry
	 * counts and resolved charges must cross that boundary without creating dynamic state
	 * on the proxy or issuing the same charge request twice.
	 */
	public function test_upe_payment_method_proxy_shares_retry_and_charge_enrichment_state() {
		$renewal_order       = WC_Helper_Order::create_order();
		$payment_requests    = 0;
		$charge_get_requests = 0;
		$fee_get_requests    = 0;
		$idempotency_keys    = [];
		$stripe              = WC_Stripe::get_instance();
		$gateway_property    = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$gateway_property->setAccessible( true );
		$previous_gateway = $gateway_property->getValue( $stripe );
		$gateway_property->setValue( $stripe, $this->wc_gateway_stripe );

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type'   => WC_Stripe_Payment_Methods::CARD,
						'status' => 'chargeable',
					],
					'payment_method' => null,
				]
			);

		$intent_response                    = json_decode( file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_success.json' ), true );
		$successful_charge                  = $intent_response['charges']['data'][0];
		$intent_response['charges']['data'] = [];
		$intent_response['latest_charge']   = 'ch_123abc';

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $intent_response, $successful_charge, &$payment_requests, &$charge_get_requests, &$fee_get_requests, &$idempotency_keys ) {
			if ( 'https://api.stripe.com/v1/payment_intents' === $url ) {
				++$payment_requests;
				$idempotency_keys[] = $request_args['headers']['Idempotency-Key'] ?? null;

				if ( 1 === $payment_requests ) {
					return [
						'headers'  => [],
						'body'     => wp_json_encode(
							[
								'error' => [
									'type'    => 'idempotency_error',
									'message' => 'Keys for idempotent requests can only be used with the same parameters they were first used with.',
								],
							]
						),
						'response' => [
							'code'    => 400,
							'message' => 'Bad Request',
						],
						'cookies'  => [],
						'filename' => null,
					];
				}

				return [
					'headers'  => [],
					'body'     => wp_json_encode( $intent_response ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( 'https://api.stripe.com/v1/charges/ch_123abc' === $url ) {
				++$charge_get_requests;
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $successful_charge ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( 'https://api.stripe.com/v1/balance/history/txn_123abc' === $url ) {
				++$fee_get_requests;
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'       => 'txn_123abc',
							'fee'      => 100,
							'net'      => 1900,
							'currency' => 'gbp',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		try {
			$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
			$renewal_order->set_payment_method( $payment_method->id );
			$renewal_order->save();
			$payment_method->process_subscription_payment( 20, $renewal_order, true, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			$gateway_property->setValue( $stripe, $previous_gateway );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );
		$this->assertSame( 2, $payment_requests );
		$this->assertSame( $renewal_order->get_id() . '-2-src_123abc', $idempotency_keys[1] );
		$this->assertSame( 1, $charge_get_requests );
		$this->assertSame( 1, $fee_get_requests );
		$this->assertFalse( property_exists( $payment_method, 'retry_interval' ) );
		$this->assertSame( 1, $this->get_gateway_retry_interval() );
		$this->assertSame( OrderStatus::PROCESSING, $renewal_order->get_status() );
		$this->assertSame( 'ch_123abc', $renewal_order->get_transaction_id() );
		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	private function set_gateway_retry_interval( $retry_interval ) {
		$reflection = new ReflectionProperty( WC_Stripe_Payment_Gateway::class, 'retry_interval' );
		$reflection->setAccessible( true );
		$reflection->setValue( $this->wc_gateway_stripe, $retry_interval );
	}

	private function get_gateway_retry_interval() {
		$reflection = new ReflectionProperty( WC_Stripe_Payment_Gateway::class, 'retry_interval' );
		$reflection->setAccessible( true );

		return $reflection->getValue( $this->wc_gateway_stripe );
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
		$auth_response                 = json_decode( file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_authentication_required.json' ), true );
		$auth_response['error']['payment_intent']['charges']['data'][0]['payment_method_details']['card']['mandate'] = 'mandate_authentication_required';

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
		$pre_http_request_response_callback = function (
			$preempt,
			$request_args,
			$url
		) use (
			$renewal_order,
			$stripe_amount,
			$currency,
			$customer,
			$source,
			$payments_intents_api_endpoint,
			$auth_response,
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
				'body'     => wp_json_encode( $auth_response ),
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
		$order_data           = WC_Stripe_Order_Helper::get_instance()->get_stripe_intent_id( $order );
		$order_transaction_id = $order->get_transaction_id();

		// Intent was saved to order even though there was an error in the response body.
		$this->assertEquals( $order_data, 'pi_123abc' );
		$this->assertSame( 'mandate_authentication_required', WC_Stripe_Order_Helper::get_instance()->get_stripe_mandate_id( $order ) );

		// Transaction ID was saved to order.
		$this->assertEquals( $order_transaction_id, 'ch_123abc' );

		// Assert: the order was marked as failed.
		$this->assertEquals( $order->get_status(), OrderStatus::FAILED );

		// Assert: called payment intents.
		$this->assertTrue( in_array( $payments_intents_api_endpoint, $urls_used ) );

		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );

		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_authentication_required' ], $mock_action_process_payment->get_tags() );

		// Clean up.
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request_response_success' ] );
	}

	/**
	 * On a Radar-blocked renewal we cancel the pending retry and clear the
	 * subscription's payment_retry date so WC Subscriptions doesn't fire another
	 * charge for Radar to block. The subscription's own on-hold transition is
	 * left to core's payment_failed() — the trait used to do it here too, but it
	 * was a same-status no-op and on-hold is the one transition that does NOT
	 * cancel the scheduled retry (see STRIPE-1110).
	 */
	public function test_renewal_radar_blocked_cancels_pending_retry() {
		// Arrange.
		$renewal_order                 = WC_Helper_Order::create_order();
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$charges_api_base              = 'https://api.stripe.com/v1/charges/';

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		// Mock subscription with a payment_retry already scheduled (mimics what
		// core's payment_failed() call leaves behind by the time the trait runs).
		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_status( OrderStatus::ON_HOLD );
		$mock_subscription->set_mock_date( 'payment_retry', time() + HOUR_IN_SECONDS );
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = [ $mock_subscription ];

		// Mock retry store: a pending retry is registered against the renewal order.
		$pending_retry                      = new WCS_Retry( 'pending' );
		WCS_Retry_Manager::$mock_last_retry = $pending_retry;

		// Mock HTTP: PI confirm returns a Radar-blocked card_declined error; the
		// subsequent charge fetch returns a charge with outcome.type === 'blocked'.
		$pre_http_request_callback = function (
			$preempt,
			$request_args,
			$url
		) use (
			$payments_intents_api_endpoint,
			$charges_api_base
		) {
			if ( $payments_intents_api_endpoint === $url ) {
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_radar_blocked.json' ),
					'response' => [
						'code'    => 402,
						'message' => 'Payment Required',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( strpos( $url, $charges_api_base ) === 0 ) {
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_charge_radar_blocked.json' ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return false;
		};

		add_filter( 'pre_http_request', $pre_http_request_callback, 10, 3 );

		try {
			// Act.
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );

			// Assert: the renewal order itself is marked as failed.
			$order = wc_get_order( $renewal_order->get_id() );
			$this->assertSame( OrderStatus::FAILED, $order->get_status() );

			// Assert: the pending retry was transitioned to cancelled.
			$this->assertSame( 'cancelled', $pending_retry->get_status() );

			// Assert: the subscription's payment_retry date was cleared.
			$this->assertSame( 0, $mock_subscription->get_date( 'payment_retry' ) );

			// Assert: a Radar note was attached to the subscription.
			$subscription_notes = $mock_subscription->get_captured_notes();
			$this->assertNotEmpty( $subscription_notes );
			$this->assertStringContainsString( 'Stripe Radar blocked payment for the saved payment method', $subscription_notes[0] );
		} finally {
			// Clean up.
			remove_filter( 'pre_http_request', $pre_http_request_callback );
			WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = null;
			WCS_Retry_Manager::mock_reset();
		}
	}

	/**
	 * A Radar-blocked renewal fires the hook with the order, error response, and reason.
	 */
	public function test_renewal_radar_blocked_fires_action_hook() {
		// Arrange.
		$renewal_order                 = WC_Helper_Order::create_order();
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$charges_api_base              = 'https://api.stripe.com/v1/charges/';

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_status( OrderStatus::ON_HOLD );
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = [ $mock_subscription ];

		$pre_http_request_callback = function (
			$preempt,
			$request_args,
			$url
		) use (
			$payments_intents_api_endpoint,
			$charges_api_base
		) {
			if ( $payments_intents_api_endpoint === $url ) {
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_radar_blocked.json' ),
					'response' => [
						'code'    => 402,
						'message' => 'Payment Required',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( strpos( $url, $charges_api_base ) === 0 ) {
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_charge_radar_blocked.json' ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return false;
		};

		add_filter( 'pre_http_request', $pre_http_request_callback, 10, 3 );

		$hook_args = [];
		$listener  = function ( $order, $response, $radar_reason ) use ( &$hook_args ) {
			$hook_args[] = [ $order, $response, $radar_reason ];
		};
		add_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $listener, 10, 3 );

		try {
			// Act.
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );

			// Assert: the hook fired exactly once with the expected arguments.
			$this->assertCount( 1, $hook_args );
			$this->assertSame( $renewal_order->get_id(), $hook_args[0][0]->get_id() );
			$this->assertNotEmpty( $hook_args[0][1]->error );
			$this->assertSame( 'highest_risk_level', $hook_args[0][2] );
		} finally {
			// Clean up.
			remove_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $listener, 10 );
			remove_filter( 'pre_http_request', $pre_http_request_callback );
			WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = null;
			WCS_Retry_Manager::mock_reset();
		}
	}

	/**
	 * A listener that throws must not leave the renewal order perpetually locked: the payment
	 * lock is released before the hook fires, so a misbehaving listener cannot strand the order.
	 */
	public function test_renewal_radar_blocked_unlocks_order_when_listener_throws() {
		// Arrange.
		$renewal_order                 = WC_Helper_Order::create_order();
		$customer                      = 'cus_123abc';
		$source                        = 'src_123abc';
		$payments_intents_api_endpoint = 'https://api.stripe.com/v1/payment_intents';
		$charges_api_base              = 'https://api.stripe.com/v1/charges/';

		$this->wc_gateway_stripe
			->expects( $this->any() )
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_status( OrderStatus::ON_HOLD );
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = [ $mock_subscription ];

		$pre_http_request_callback = function (
			$preempt,
			$request_args,
			$url
		) use (
			$payments_intents_api_endpoint,
			$charges_api_base
		) {
			if ( $payments_intents_api_endpoint === $url ) {
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_radar_blocked.json' ),
					'response' => [
						'code'    => 402,
						'message' => 'Payment Required',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( strpos( $url, $charges_api_base ) === 0 ) {
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_charge_radar_blocked.json' ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return false;
		};

		add_filter( 'pre_http_request', $pre_http_request_callback, 10, 3 );

		$listener = function () {
			throw new Exception( 'Listener failure.' );
		};
		add_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $listener, 10, 3 );

		try {
			// Act: the throwing listener is caught internally, so the call returns without error.
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );

			// Assert: the payment lock was released despite the listener throwing.
			$order_helper = WC_Stripe_Order_Helper::get_instance();
			$this->assertEmpty( $order_helper->get_order_existing_payment_lock( $renewal_order ) );
		} finally {
			// Clean up.
			remove_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $listener, 10 );
			remove_filter( 'pre_http_request', $pre_http_request_callback );
			WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = null;
			WCS_Retry_Manager::mock_reset();
		}
	}

	public function test_missing_customer() {
		$renewal_order = WC_Helper_Order::create_order();
		$source        = 'src_123abc';

		// Mock prepare_order_source() to return a missing customer.
		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => null,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$thrown_exception = null;
		$error_helper     = function ( $exception, $order ) use ( &$thrown_exception, $renewal_order ) {
			if ( $order && $order->get_id() === $renewal_order->get_id() ) {
				$thrown_exception = $exception;
			}
		};

		\add_action( 'wc_gateway_stripe_process_payment_error', $error_helper, 10, 2 );

		// Process via the mocked gateway.
		$this->wc_gateway_stripe->process_subscription_payment( $renewal_order->get_total(), $renewal_order, false, false );

		\remove_action( 'wc_gateway_stripe_process_payment_error', $error_helper, 10 );

		$this->assertEquals( \Automattic\WooCommerce\Enums\OrderStatus::FAILED, $renewal_order->get_status() );
		$this->assertInstanceOf( \WC_Stripe_Exception::class, $thrown_exception );

		$expected_raw_error       = 'Failed to process renewal for order ' . $renewal_order->get_id() . '. Stripe customer id is missing in the order';
		$expected_localized_error = __( 'Customer not found', 'woocommerce-gateway-stripe' );

		$this->assertEquals( $expected_raw_error, $thrown_exception->getMessage() );
		$this->assertEquals( $expected_localized_error, $thrown_exception->getLocalizedMessage() );
	}

	public function test_payment_intent_returns_non_retryable_error() {
		$renewal_order = WC_Helper_Order::create_order();
		$source        = 'src_123abc';
		$customer      = 'cus_123abc';

		// Mock prepare_order_source() to return a valid customer.
		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => $customer,
					'source'         => $source,
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$mock_error = (object) [
			'error' => (object) [
				'type'    => 'card_error',
				'code'    => 'card_declined',
				'message' => 'Mock card declined error',
			],
		];

		// Arrange: Add filter that will return a mocked HTTP response for the payment_intent call.
		// Note: There are assertions in the callback function.
		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $mock_error ) {
			if ( 'https://api.stripe.com/v1/payment_intents' !== $url ) {
				return $preempt;
			}

			return [
				'headers'  => [],
				'body'     => json_encode( $mock_error ),
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
			];
		};
		\add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		$thrown_exception = null;
		$error_helper     = function ( $exception, $order ) use ( &$thrown_exception, $renewal_order ) {
			if ( $order && $order->get_id() === $renewal_order->get_id() ) {
				$thrown_exception = $exception;
			}
		};
		\add_action( 'wc_gateway_stripe_process_payment_error', $error_helper, 10, 2 );

		$this->wc_gateway_stripe->process_subscription_payment( $renewal_order->get_total(), $renewal_order, false, false );

		\remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		\remove_action( 'wc_gateway_stripe_process_payment_error', $error_helper, 10 );

		$this->assertEquals( \Automattic\WooCommerce\Enums\OrderStatus::FAILED, $renewal_order->get_status() );
		$this->assertInstanceOf( \WC_Stripe_Exception::class, $thrown_exception );

		$expected_raw_error       = print_r( $mock_error, true );
		$expected_localized_error = __( 'The card was declined.', 'woocommerce-gateway-stripe' );

		$this->assertEquals( $expected_raw_error, $thrown_exception->getMessage() );
		$this->assertEquals( $expected_localized_error, $thrown_exception->getLocalizedMessage() );
	}

	public function test_payment_error_hook_unsaved_metadata_is_preserved_across_lock_recheck() {
		$renewal_order = WC_Helper_Order::create_order();

		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) {
			if ( 'https://api.stripe.com/v1/payment_intents' !== $url ) {
				return $preempt;
			}

			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'error' => [
							'type'    => 'card_error',
							'code'    => 'card_declined',
							'message' => 'Mock card declined error',
						],
					]
				),
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
			];
		};
		$error_hook                         = function ( $exception, $order ) use ( $renewal_order ) {
			if ( $order->get_id() === $renewal_order->get_id() ) {
				$order->update_meta_data( '_listener_unsaved_meta', 'preserved' );
			}
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		add_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10, 2 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( OrderStatus::FAILED, $renewal_order->get_status() );
		$this->assertSame( 'preserved', $renewal_order->get_meta( '_listener_unsaved_meta', true ) );
	}

	public function test_successful_payment_is_recorded_when_lock_is_replaced_during_stripe_request() {
		$renewal_order    = WC_Helper_Order::create_order();
		$replacement_lock = (string) ( time() + 10 * MINUTE_IN_SECONDS );
		$request_count    = 0;

		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count, $renewal_order, $replacement_lock ) {
			if ( 'https://api.stripe.com/v1/payment_intents' !== $url ) {
				return $preempt;
			}

			++$request_count;
			$replacement_writer = wc_get_order( $renewal_order->get_id() );
			$replacement_writer->update_meta_data( '_stripe_lock_payment', $replacement_lock );
			$replacement_writer->save_meta_data();

			return [
				'headers'  => [],
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

		try {
			$this->wc_gateway_stripe->process_subscription_payment( 20, $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 1, $request_count );
		$this->assertSame( OrderStatus::PROCESSING, $renewal_order->get_status() );
		$this->assertSame( 'ch_123abc', $renewal_order->get_transaction_id() );
		$this->assertSame( $replacement_lock, (string) WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	/**
	 * @dataProvider provide_payment_errors_after_payment_lock_replacement
	 */
	public function test_payment_error_does_not_fail_order_after_payment_lock_is_replaced( $error_mode, $replace_during_request, $expected_error_hook_count ) {
		$renewal_order    = WC_Helper_Order::create_order();
		$replacement_lock = (string) ( time() + 10 * MINUTE_IN_SECONDS );
		$request_count    = 0;
		$error_hook_count = 0;

		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count, $renewal_order, $replacement_lock, $error_mode, $replace_during_request ) {
			if ( 'https://api.stripe.com/v1/payment_intents' === $url ) {
				++$request_count;
				if ( $replace_during_request ) {
					$renewal_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
					$renewal_order->save_meta_data();
				}

				if ( 'transport exception' === $error_mode ) {
					return new WP_Error( 'stripe_transport_error', 'Stripe transport failed.' );
				}

				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'error' => [
								'type'    => 'card_error',
								'code'    => 'card_declined',
								'message' => 'Mock card declined error',
							],
						]
					),
					'response' => [
						'code'    => 400,
						'message' => 'Bad Request',
					],
				];
			}

			if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe request.' );
			}

			return $preempt;
		};
		$error_hook                         = function () use ( &$error_hook_count, $replace_during_request, $renewal_order, $replacement_lock ) {
			++$error_hook_count;
			if ( ! $replace_during_request ) {
				$renewal_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
				$renewal_order->save_meta_data();
			}
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		add_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10, 2 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( $renewal_order->get_total(), $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 1, $request_count );
		$this->assertSame( $expected_error_hook_count, $error_hook_count );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
		$this->assertSame( $replacement_lock, (string) WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	public function provide_payment_errors_after_payment_lock_replacement() {
		return [
			'non-retryable response'   => [ 'non-retryable response', true, 1 ],
			'transport exception'      => [ 'transport exception', true, 1 ],
			'error hook replaces lock' => [ 'transport exception', false, 1 ],
		];
	}

	public function test_radar_lookup_does_not_fail_order_after_payment_lock_is_replaced() {
		$renewal_order    = WC_Helper_Order::create_order();
		$replacement_lock = (string) ( time() + 10 * MINUTE_IN_SECONDS );
		$request_count    = 0;
		$error_hook_count = 0;
		$radar_hook_count = 0;

		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( &$request_count, $renewal_order, $replacement_lock ) {
			if ( 'https://api.stripe.com/v1/payment_intents' === $url ) {
				++$request_count;
				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_response_radar_blocked.json' ),
					'response' => [
						'code'    => 402,
						'message' => 'Payment Required',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( str_starts_with( $url, 'https://api.stripe.com/v1/charges/' ) ) {
				++$request_count;
				$renewal_order->update_meta_data( '_stripe_lock_payment', $replacement_lock );
				$renewal_order->save_meta_data();

				return [
					'headers'  => [],
					'body'     => file_get_contents( __DIR__ . '/dummy-data/subscription_renewal_charge_radar_blocked.json' ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
				return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe request.' );
			}

			return $preempt;
		};
		$error_hook                         = function () use ( &$error_hook_count ) {
			++$error_hook_count;
		};
		$radar_hook                         = function () use ( &$radar_hook_count ) {
			++$radar_hook_count;
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );
		add_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10, 2 );
		add_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $radar_hook, 10, 3 );

		try {
			$this->wc_gateway_stripe->process_subscription_payment( $renewal_order->get_total(), $renewal_order, false, false );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
			remove_action( 'wc_gateway_stripe_process_payment_error', $error_hook, 10 );
			remove_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $radar_hook, 10 );
		}

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( 2, $request_count );
		$this->assertSame( 1, $error_hook_count );
		$this->assertSame( 0, $radar_hook_count );
		$this->assertSame( OrderStatus::PENDING, $renewal_order->get_status() );
		$this->assertSame( $replacement_lock, (string) WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( $renewal_order ) );
	}

	/**
	 * The failure order note links the Stripe request log URL with target="_blank" so the
	 * merchant does not navigate away from the order screen when opening it.
	 *
	 * @dataProvider provide_test_failed_renewal_note_links_request_log_url_in_new_tab
	 */
	public function test_failed_renewal_note_links_request_log_url_in_new_tab( $mock_error_data, $expected_note_template ) {
		$renewal_order   = WC_Helper_Order::create_order();
		$request_log_url = 'https://dashboard.stripe.com/acct_123abc/test/logs/req_123abc?t=123&span=456';

		// Mock prepare_order_source() to return a valid customer.
		$this->wc_gateway_stripe
			->method( 'prepare_order_source' )
			->willReturn(
				(object) [
					'token_id'       => false,
					'customer'       => 'cus_123abc',
					'source'         => 'src_123abc',
					'source_object'  => (object) [
						'type' => WC_Stripe_Payment_Methods::CARD,
					],
					'payment_method' => null,
				]
			);

		$mock_error = (object) [
			'error' => (object) array_merge( $mock_error_data, [ 'request_log_url' => $request_log_url ] ),
		];

		// Arrange: Add filter that will return a mocked HTTP response for the payment_intent call.
		$pre_http_request_response_callback = function ( $preempt, $request_args, $url ) use ( $mock_error ) {
			if ( 'https://api.stripe.com/v1/payment_intents' !== $url ) {
				return $preempt;
			}

			return [
				'headers'  => [],
				'body'     => json_encode( $mock_error ),
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
			];
		};
		\add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		$this->wc_gateway_stripe->process_subscription_payment( $renewal_order->get_total(), $renewal_order, false, false );

		\remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );

		// The fixture URL's "&" locks in the escaping: esc_url() encodes it as "&#038;"
		// in the href, esc_html() as "&amp;" in the link text.
		$expected_href = 'https://dashboard.stripe.com/acct_123abc/test/logs/req_123abc?t=123&#038;span=456';
		$expected_text = 'https://dashboard.stripe.com/acct_123abc/test/logs/req_123abc?t=123&amp;span=456';

		$expected_note = sprintf(
			$expected_note_template,
			'<a href="' . $expected_href . '" target="_blank" rel="noopener noreferrer">' . $expected_text . '</a>'
		);
		$note_contents = wp_list_pluck( wc_get_order_notes( [ 'order_id' => $renewal_order->get_id() ] ), 'content' );

		$this->assertContains( $expected_note, $note_contents );
	}

	/**
	 * Data provider for `test_failed_renewal_note_links_request_log_url_in_new_tab`.
	 *
	 * @return array
	 */
	public function provide_test_failed_renewal_note_links_request_log_url_in_new_tab() {
		return [
			'non-retryable card decline'             => [
				[
					'type'    => 'card_error',
					'code'    => 'card_declined',
					'message' => 'Mock card declined error',
				],
				'The card was declined. %s',
			],
			'retryable error with retries exhausted' => [
				[
					'type'    => 'api_error',
					'message' => 'Mock API error',
				],
				'Sorry, we are unable to process the payment at this time. Reason: Mock API error %s',
			],
		];
	}
}
