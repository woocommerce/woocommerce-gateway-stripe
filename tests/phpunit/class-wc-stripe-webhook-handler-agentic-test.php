<?php

/**
 * Tests for agentic commerce checkout.session.completed webhook handling.
 *
 * @covers WC_Stripe_Webhook_Handler::process_checkout_session_success
 */
class WC_Stripe_Webhook_Handler_Agentic_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $handler;

	/**
	 * @var callable|null HTTP mock filter callback to remove in tear_down.
	 */
	private $http_filter;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->handler     = new WC_Stripe_Webhook_Handler();
		$this->http_filter = null;

		// Clear any invalid API key cache left by other tests so that
		// WC_Stripe_API::retrieve() actually fires HTTP requests.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Tear down the test — always remove filters to prevent leaks.
	 */
	public function tear_down() {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
		}
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		parent::tear_down();
	}

	/**
	 * Tests that the webhook is ignored when the feature flag is disabled.
	 */
	public function test_process_checkout_session_completed_skips_when_disabled() {
		// Override the setUp-enabled flag for this specific test.
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_false' );

		$session_id   = 'cs_test_disabled';
		$notification = $this->build_notification( $session_id );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: feature flag off → agentic path skips → no order created.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_disabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );

		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_false' );
	}

	/**
	 * Tests that non-agentic checkout sessions are ignored.
	 */
	public function test_process_checkout_session_completed_skips_non_agentic() {
		$session_id   = 'cs_test_non_agentic';
		$notification = $this->build_notification( $session_id );
		$mock_session = $this->build_checkout_session_response( $session_id, false );
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: non-agentic session → skips without creating an order.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		$resolved = $this->get_resolved_order( $this->handler );
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that a session with empty network_business_profile is skipped.
	 */
	public function test_skips_session_with_empty_network_business_profile() {
		$session_id = 'cs_test_empty_nbp';
		$session    = (object) [
			'id'             => $session_id,
			'payment_intent' => (object) [
				'id'            => 'pi_test_' . $session_id,
				'agent_details' => (object) [
					'network_business_profile' => '',
				],
			],
		];
		$this->mock_stripe_checkout_sessions_response( $session );

		$notification = $this->build_notification( $session_id );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: empty network_business_profile → skips.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		$resolved = $this->get_resolved_order( $this->handler );
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that concurrent duplicate webhooks are blocked by the lock.
	 */
	public function test_concurrent_duplicate_blocked_by_lock() {
		$session_id = 'cs_test_locked';
		$lock_key   = 'checkout_session_lock_' . $session_id;

		// Simulate an in-progress lock.
		WC_Stripe_Database_Cache::set( $lock_key, time(), 5 * MINUTE_IN_SECONDS );

		$notification = $this->build_notification( $session_id );
		$this->handler->process_checkout_session_success( $notification );

		$resolved = $this->get_resolved_order( $this->handler );

		// Clean up.
		WC_Stripe_Database_Cache::delete( $lock_key );

		$this->assertNull( $resolved );
	}

	/**
	 * Tests that the lock is released after processing, even on failure.
	 */
	public function test_lock_released_after_processing() {
		$this->mock_stripe_api_error();

		$notification = $this->build_notification( 'cs_test_lock_release' );
		$this->handler->process_checkout_session_success( $notification );

		$lock_key = 'checkout_session_lock_cs_test_lock_release';
		$this->assertNull( WC_Stripe_Database_Cache::get( $lock_key ) );
	}

	/**
	 * Tests that the mapper is called, errors fire the failure hook, and the
	 * exception is re-thrown so Action Scheduler marks the job as failed.
	 *
	 * The order mapper will fail because the mock session references
	 * a non-existent product.
	 */
	public function test_process_checkout_session_completed_handles_mapper_failure() {
		$failure_action_fired = false;
		$captured_exception   = null;
		add_action(
			'wc_stripe_agentic_order_creation_failed',
			function ( $e ) use ( &$failure_action_fired, &$captured_exception ) {
				$failure_action_fired = true;
				$captured_exception   = $e;
			}
		);

		$session_id   = 'cs_test_mapper_fail';
		$notification = $this->build_notification( $session_id );
		$mock_session = $this->build_checkout_session_response( $session_id, true );
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: mapper fails → fires failure action, then rethrows so
		// Action Scheduler marks the job failed.
		$rethrown = null;
		try {
			$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );
		} catch ( \Throwable $t ) {
			$rethrown = $t;
		}

		$this->assertTrue( $failure_action_fired );
		$this->assertInstanceOf( Exception::class, $captured_exception );
		$this->assertSame( $captured_exception, $rethrown );
	}

	/**
	 * Tests that a PHP Throwable (not just Exception) thrown during order
	 * mapping is caught, logged, fires the failure hook, and is re-thrown so
	 * Action Scheduler marks the job as failed rather than silently complete.
	 */
	public function test_process_checkout_session_completed_handles_mapper_throwable() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '20.00',
				'price'         => '20.00',
			]
		);

		$failure_action_fired = false;
		$captured_throwable   = null;
		add_action(
			'wc_stripe_agentic_order_creation_failed',
			function ( $e ) use ( &$failure_action_fired, &$captured_throwable ) {
				$failure_action_fired = true;
				$captured_throwable   = $e;
			}
		);

		// Force a non-Exception Throwable from inside the mapper's try block.
		// woocommerce_new_order_item fires during $order->add_product() in
		// map_line_items(), which runs inside the try/catch(Throwable) that the
		// mapper's rollback depends on.
		$throw_action = function () {
			throw new \TypeError( 'Simulated fatal inside order mapping' );
		};
		add_action( 'woocommerce_new_order_item', $throw_action );

		$rethrown = null;
		try {
			$session_id   = 'cs_test_throwable';
			$notification = $this->build_notification( $session_id );
			$mock_session = $this->build_checkout_session_response( $session_id, true, (string) $product->get_id() );
			$this->mock_stripe_checkout_sessions_response( $mock_session );

			// Immediate phase: defers the webhook.
			$this->handler->process_checkout_session_success( $notification );
			// Deferred phase: TypeError is thrown from woocommerce_new_order_item
			// via the action. The handler must catch it (Throwable, not just
			// Exception), log it, fire the failure action, and then rethrow so
			// Action Scheduler sees the job as failed.
			try {
				$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );
			} catch ( \Throwable $t ) {
				$rethrown = $t;
			}
		} finally {
			remove_action( 'woocommerce_new_order_item', $throw_action );
		}

		$this->assertTrue( $failure_action_fired );
		$this->assertInstanceOf( \Throwable::class, $captured_throwable );
		$this->assertInstanceOf( \TypeError::class, $captured_throwable );
		$this->assertSame( $captured_throwable, $rethrown );

		// The order created by the mapper must be rolled back: the mapper's
		// inner Throwable catch deletes the order before rethrowing.
		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_throwable', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that a valid agentic session creates an order and fires the success action.
	 */
	public function test_process_checkout_session_completed_creates_order() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '20.00',
				'price'         => '20.00',
			]
		);

		$success_action_fired = false;
		$created_order        = null;
		add_action(
			'wc_stripe_agentic_order_created',
			function ( $order ) use ( &$success_action_fired, &$created_order ) {
				$success_action_fired = true;
				$created_order        = $order;
			}
		);

		$session_id   = 'cs_test_happy';
		$notification = $this->build_notification( $session_id );
		$mock_session = $this->build_checkout_session_response( $session_id, true, (string) $product->get_id() );
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: agentic session → creates order.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		try {
			$this->assertTrue( $success_action_fired );
			$this->assertInstanceOf( WC_Order::class, $created_order );
			$this->assertEquals( 'processing', $created_order->get_status() );
			$this->assertEquals( '20.00', $created_order->get_total() );
			$this->assertEquals( 'stripe', $created_order->get_payment_method() );
			$this->assertEquals( 'pi_test_cs_test_happy', $created_order->get_meta( '_stripe_intent_id', true ) );
		} finally {
			if ( $created_order instanceof WC_Order ) {
				$created_order->delete( true );
			}
			$product->delete( true );
		}
	}

	/**
	 * Tests that a failed API fetch is handled gracefully without creating an order.
	 */
	public function test_process_checkout_session_completed_handles_api_fetch_failure() {
		$session_id   = 'cs_test_fetch_fail';
		$notification = $this->build_notification( $session_id );
		$this->mock_stripe_api_error();

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: Stripe API error → no order created.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_fetch_fail', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that a session with a missing payment intent ID is skipped without creating an order.
	 */
	public function test_process_checkout_session_completed_skips_when_payment_intent_missing() {
		$session_id                   = 'cs_test_no_intent';
		$notification                 = $this->build_notification( $session_id );
		$mock_session                 = $this->build_checkout_session_response( $session_id, true );
		$mock_session->payment_intent = (object) [
			'id'            => null,
			'agent_details' => (object) [
				'network_business_profile' => 'nbp_test_123',
			],
		];
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: missing payment intent ID → no order created.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_no_intent', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that the wc_stripe_request_headers filter is always removed after processing,
	 * even when an error occurs before order creation.
	 */
	public function test_request_headers_filter_is_removed_after_processing_failure() {
		$this->assertFalse( has_filter( 'wc_stripe_request_headers' ) );

		$session_id   = 'cs_test_filter_cleanup';
		$notification = $this->build_notification( $session_id );
		$this->mock_stripe_api_error();

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: API error → filter must still be cleaned up.
		$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );

		$this->assertFalse( has_filter( 'wc_stripe_request_headers' ) );
	}

	/**
	 * Tests that the Stripe API version override header is applied during the retrieve call.
	 */
	public function test_api_version_override_applied() {
		$captured_headers  = null;
		$this->http_filter = function ( $preempt, $parsed_args ) use ( &$captured_headers ) {
			$captured_headers = $parsed_args['headers'] ?? [];
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode( $this->build_checkout_session_response( 'cs_test_version', true ) ),
			];
		};

		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$session_id   = 'cs_test_version';
		$notification = $this->build_notification( $session_id );

		// Immediate phase: defers the webhook.
		$this->handler->process_checkout_session_success( $notification );
		// Deferred phase: API retrieve call must include the Stripe-Version override
		// header. The stub session references a non-existent product, so the mapper
		// fails and re-throws — that's fine for this test; we only care that the
		// override filter was applied before the HTTP call.
		$mapper_threw = null;
		try {
			$this->handler->process_deferred_webhook( 'checkout.session.completed', [ 'session_id' => $session_id ], $notification );
		} catch ( \Throwable $t ) {
			$mapper_threw = $t;
		}
		$this->assertInstanceOf( \Throwable::class, $mapper_threw );

		$this->assertNotNull( $captured_headers );
		$this->assertArrayHasKey( 'Stripe-Version', $captured_headers );
		$this->assertEquals( WC_Stripe_API::AGENTIC_COMMERCE_API_VERSION, $captured_headers['Stripe-Version'] );
	}

	/**
	 * Tests that build_checkout_session_retrieve_url produces correct URLs.
	 *
	 * @dataProvider provide_build_url_cases
	 */
	public function test_build_checkout_session_retrieve_url( $session_id, $additional_expand, $expected_url ) {
		$method = new \ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'build_checkout_session_retrieve_url' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $session_id, $additional_expand );
		$this->assertEquals( $expected_url, $result );
	}

	public function provide_build_url_cases() {
		return [
			'default expand only'           => [
				'cs_123',
				[],
				'checkout/sessions/cs_123?expand[]=payment_intent.agent_details',
			],
			'with additional expand'        => [
				'cs_456',
				[ 'line_items' ],
				'checkout/sessions/cs_456?expand[]=payment_intent.agent_details&expand[]=line_items',
			],
			'session id with special chars' => [
				'cs_test/special&chars',
				[],
				'checkout/sessions/cs_test%2Fspecial%26chars?expand[]=payment_intent.agent_details',
			],
		];
	}

	// ---- Helpers ----

	/**
	 * Intercepts HTTP requests to the Stripe checkout sessions API and returns a mock response.
	 *
	 * @param object $response_body The mock response body object.
	 */
	private function mock_stripe_checkout_sessions_response( $response_body ) {
		$this->http_filter = function ( $preempt, $args, $url ) use ( $response_body ) {
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions/' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode( $response_body ),
				];
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	/**
	 * Intercepts HTTP requests to the Stripe API and returns an error response.
	 */
	private function mock_stripe_api_error() {
		$this->http_filter = function ( $preempt, $args, $url ) {
			if ( false !== strpos( $url, 'api.stripe.com' ) ) {
				return new \WP_Error( 'http_request_failed', 'Simulated Stripe API failure' );
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	/**
	 * Builds a checkout.session.completed notification object (webhook payload).
	 *
	 * @param string $session_id The checkout session ID.
	 * @return object
	 */
	private function build_notification( $session_id ) {
		$session = [
			'id'             => $session_id,
			'payment_intent' => 'pi_test_' . $session_id,
			'payment_status' => 'paid',
			'currency'       => 'usd',
			'amount_total'   => 2000,
			'metadata'       => (object) [],
		];

		return (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) $session,
			],
		];
	}

	/**
	 * Builds a mock Stripe API response for a checkout session retrieval.
	 *
	 * @param string      $session_id The checkout session ID.
	 * @param bool        $agentic    Whether to include agentic line items.
	 * @param string|null $product_id Optional real WC product ID for the external_reference.
	 * @return object
	 */
	private function build_checkout_session_response( $session_id, $agentic, $product_id = null ) {
		$line_items_data = [];

		if ( $agentic ) {
			$line_items_data[] = (object) [
				'id'              => 'li_test_1',
				'description'     => 'Test Product',
				'quantity'        => 1,
				'amount_total'    => 2000,
				'amount_subtotal' => 2000,
				'amount_tax'      => 0,
				'price'           => (object) [
					'unit_amount'        => 2000,
					'external_reference' => $product_id ?? '99999999',
					'currency'           => 'usd',
				],
			];
		} else {
			$line_items_data[] = (object) [
				'id'              => 'li_test_1',
				'description'     => 'Test Product',
				'quantity'        => 1,
				'amount_total'    => 2000,
				'amount_subtotal' => 2000,
				'amount_tax'      => 0,
				'price'           => (object) [
					'unit_amount' => 2000,
					'currency'    => 'usd',
				],
			];
		}

		$address = (object) [
			'city'        => 'San Francisco',
			'country'     => 'US',
			'line1'       => '123 Main St',
			'line2'       => '',
			'postal_code' => '94105',
			'state'       => 'CA',
		];

		return (object) [
			'id'               => $session_id,
			'payment_intent'   => (object) [
				'id'            => 'pi_test_' . $session_id,
				'agent_details' => $agentic
					? (object) [ 'network_business_profile' => 'nbp_test_123' ]
					: null,
			],
			'customer'         => 'cus_test_789',
			'customer_email'   => 'test@example.com',
			'currency'         => 'usd',
			'amount_total'     => 2000,
			'amount_subtotal'  => 2000,
			'customer_details' => (object) [
				'email'   => 'test@example.com',
				'name'    => 'John Smith',
				'phone'   => '+1234567890',
				'address' => $address,
			],
			'shipping_details' => (object) [
				'name'    => 'John Smith',
				'phone'   => '+1234567890',
				'address' => $address,
			],
			'total_details'    => (object) [
				'amount_shipping' => 0,
				'amount_tax'      => 0,
				'amount_discount' => 0,
			],
			'line_items'       => (object) [
				'data' => $line_items_data,
			],
		];
	}

	/**
	 * Gets the resolved_order from the handler via reflection.
	 *
	 * @param WC_Stripe_Webhook_Handler $webhook_handler Webhook handler instance.
	 * @return WC_Order|null
	 */
	private function get_resolved_order( WC_Stripe_Webhook_Handler $webhook_handler ) {
		$prop = new \ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'resolved_order' );
		$prop->setAccessible( true );
		return $prop->getValue( $webhook_handler );
	}
}
