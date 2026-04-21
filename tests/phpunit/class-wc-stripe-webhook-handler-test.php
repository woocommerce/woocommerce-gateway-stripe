<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * These tests make assertions against class WC_Stripe_Webhook_State.
 *
 * @package WooCommerce/Stripe/Webhook_State
 *
 * WC_Stripe_Webhook_State_Test class.
 */
class WC_Stripe_Webhook_Handler_Test extends WP_UnitTestCase {

	/**
	 * The webhook handler instance for testing.
	 *
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $mock_webhook_handler;

	/**
	 * Mock card payment intent template.
	 */
	const MOCK_PAYMENT_INTENT = [
		'id'      => 'pi_mock',
		'object'  => 'payment_intent',
		'status'  => WC_Stripe_Intent_Status::SUCCEEDED,
		'charges' => [
			'total_count' => 1,
			'data'        => [
				[
					'id'                     => 'ch_mock',
					'captured'               => true,
					'payment_method_details' => [],
					'status'                 => 'succeeded',
				],
			],
		],
	];

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_webhook_handler();

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);

		$order_helper->expects( $this->any() )
			->method( 'lock_order_payment' )
			->willReturn( false );

		$order_helper->expects( $this->any() )
			->method( 'unlock_order_payment' );

		WC_Stripe_Order_Helper::set_instance( $order_helper );
	}

	/**
	 * Mock the webhook handler.
	 */
	private function mock_webhook_handler( $exclude_methods = [] ) {
		$methods = [
			'handle_deferred_payment_intent_succeeded',
			'get_intent_from_order',
			'get_latest_charge_from_intent',
			'process_response',
			'update_fees',
			'send_failed_refund_emails',
		];

		$methods = array_diff( $methods, $exclude_methods );

		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( $methods )
			->getMock();

		// Set process_response mock to use the real method.
		// We need to mock this because several tests check that it's not called or called a specific number of times.
		$this->mock_webhook_handler->expects( $this->any() )
		->method( 'process_response' )
		->willReturnCallback(
			function ( $response, $order ) {
				// Call the real method
				$real_handler = new WC_Stripe_Webhook_Handler();
				return $real_handler->process_response( $response, $order );
			}
		);
	}

	/**
	 * Test process_deferred_webhook with unsupported webhook type.
	 */
	public function test_process_deferred_webhook_invalid_type() {
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'handle_deferred_payment_intent_succeeded' );

		$this->expectExceptionMessage( 'Unsupported webhook type: event-id' );
		$this->mock_webhook_handler->process_deferred_webhook( 'event-id', [], (object) [] );
	}

	/**
	 * Test process_deferred_webhook with invalid args.
	 */
	public function test_process_deferred_webhook_invalid_args() {
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'handle_deferred_payment_intent_succeeded' );

		$notification = (object) [
			'type' => 'payment_intent.succeeded',
			'data' => (object) [
				'object' => (object) [
					'id'                 => 'pi_mock_1234',
					'charges'            => (object) [
						'total_count' => 1,
						'data'        => [
							(object) self::MOCK_PAYMENT_INTENT['charges']['data'][0],
						],
					],
					'last_payment_error' => null,
				],
			],
		];

		// No data.
		$data = [];

		$this->expectExceptionMessage( "Missing required data. 'order_id' is invalid or not found for the deferred 'payment_intent.succeeded' event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification );

		// Invalid order_id.
		$data = [
			'order_id' => 9999,
		];

		$this->expectExceptionMessage( "Missing required data. 'order_id' is invalid or not found for the deferred 'payment_intent.succeeded' event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification );

		// No payment intent.
		$order            = WC_Helper_Order::create_order();
		$data['order_id'] = $order->get_id();

		$this->expectExceptionMessage( "Missing required data. 'intent_id' is missing for the deferred 'payment_intent.succeeded' event." );
		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification );
	}

	/**
	 * Test process_deferred_webhook with valid args.
	 */
	public function test_process_deferred_webhook() {
		$order        = WC_Helper_Order::create_order();
		$intent_id    = 'pi_mock_1234';
		$data         = [
			'order_id'  => $order->get_id(),
			'intent_id' => $intent_id,
		];
		$notification = (object) [
			'type' => 'payment_intent.succeeded',
			'data' => (object) [
				'object' => (object) [
					'id'                 => $intent_id,
					'charges'            => (object) [
						'total_count' => 1,
						'data'        => [
							(object) self::MOCK_PAYMENT_INTENT['charges']['data'][0],
						],
					],
					'last_payment_error' => null,
				],
			],
		];

		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'handle_deferred_payment_intent_succeeded' )
			->with(
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $passed_order instanceof WC_Order && $order->get_id() === $passed_order->get_id();
					}
				),
				$this->equalTo( $intent_id ),
			);

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification );
	}

	/**
	 * Deferred webhook jobs deserialize notification stdClass to nested arrays; ensure wc_stripe_webhook_received still gets an object.
	 *
	 * @return void
	 */
	public function test_process_deferred_webhook_normalizes_array_notification_for_wc_stripe_webhook_received() {
		$captured_notification = null;

		$listener = static function ( $webhook_type, $notification ) use ( &$captured_notification ) {
			unset( $webhook_type );
			$captured_notification = $notification;
		};

		add_action( 'wc_stripe_webhook_received', $listener, 10, 3 );

		try {

			$order        = WC_Helper_Order::create_order();
			$intent_id    = 'pi_mock_1234';
			$data         = [
				'order_id'  => $order->get_id(),
				'intent_id' => $intent_id,
			];
			$notification = (object) [
				'type' => 'payment_intent.succeeded',
				'data' => (object) [
					'object' => (object) [
						'id'                 => $intent_id,
						'charges'            => (object) [
							'total_count' => 1,
							'data'        => [
								(object) self::MOCK_PAYMENT_INTENT['charges']['data'][0],
							],
						],
						'last_payment_error' => null,
					],
				],
			];

			$notification_as_arrays = json_decode( wp_json_encode( $notification ), true );
			$this->assertIsArray( $notification_as_arrays );

			$this->mock_webhook_handler->expects( $this->once() )
				->method( 'handle_deferred_payment_intent_succeeded' );

			$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification_as_arrays );

			$this->assertIsObject( $captured_notification );
			$this->assertSame( 'payment_intent.succeeded', $captured_notification->type );
		} finally {
			remove_action( 'wc_stripe_webhook_received', $listener, 10 );
		}
	}

	/**
	 * Test deferred webhook where the intent is no longer stored on the order.
	 */
	public function test_mismatch_intent_id_process_deferred_webhook() {
		$order        = WC_Helper_Order::create_order();
		$data         = [
			'order_id'  => $order->get_id(),
			'intent_id' => 'pi_wrong_id',
		];
		$notification = (object) [
			'type' => 'payment_intent.succeeded',
			'data' => (object) [
				'object' => (object) [
					'id'                 => 'pi_mock_1234',
					'charges'            => (object) [
						'total_count' => 1,
						'data'        => [
							(object) self::MOCK_PAYMENT_INTENT['charges']['data'][0],
						],
					],
					'last_payment_error' => null,
				],
			],
		];

		$this->mock_webhook_handler( [ 'handle_deferred_payment_intent_succeeded' ] );

		// Mock the get intent from order to return the mock intent.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->with(
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $passed_order instanceof WC_Order && $order->get_id() === $passed_order->get_id();
					}
				)
			)->willReturn( (object) self::MOCK_PAYMENT_INTENT );

		// Expect the get latest charge from intent to be called.
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'get_latest_charge_from_intent' );

		// Expect the process response to be called with the charge and order.
		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'process_response' );

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification );
	}

	/**
	 * Test successful deferred webhook.
	 */
	public function test_process_of_successful_payment_intent_deferred_webhook() {
		$order        = WC_Helper_Order::create_order();
		$data         = [
			'order_id'  => $order->get_id(),
			'intent_id' => self::MOCK_PAYMENT_INTENT['id'],
		];
		$notification = (object) [
			'type' => 'payment_intent.succeeded',
			'data' => (object) [
				'object' => (object) self::MOCK_PAYMENT_INTENT,
			],
		];

		$this->mock_webhook_handler( [ 'handle_deferred_payment_intent_succeeded' ] );

		// Mock the get intent from order to return the mock intent.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT );

		// Expect the get latest charge from intent to be called.
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		// Expect the process response to be called with the charge and order.
		$charge_param = (object) array_merge(
			self::MOCK_PAYMENT_INTENT['charges']['data'][0],
			[ 'is_webhook_response' => true ]
		);
		$this->mock_webhook_handler->expects( $this->once() )
			->method( 'process_response' )
			->with(
				$charge_param,
				$this->callback(
					function ( $passed_order ) use ( $order ) {
						return $passed_order instanceof WC_Order && $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$this->mock_webhook_handler->process_deferred_webhook( 'payment_intent.succeeded', $data, $notification );
	}

	/**
	 * When no order is linked yet, checkout session success defers processing via Action Scheduler.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_success_defers_when_order_not_found(): void {
		$checkout_session_id = 'cs_test_deferred_no_order';

		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_deferred',
				],
			],
		];

		$start          = time();
		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->callback(
					function ( $timestamp ) use ( $start ) {
						$this->assertIsInt( $timestamp );
						$this->assertGreaterThanOrEqual( $start + 2 * MINUTE_IN_SECONDS, $timestamp );

						return true;
					}
				),
				'wc_stripe_deferred_webhook',
				$this->callback(
					function ( $args ) use ( $notification, $checkout_session_id ) {
						return isset( $args['type'], $args['data'], $args['notification'] )
							&& 'checkout.session.completed' === $args['type']
							&& isset( $args['data']['session_id'] )
							&& $checkout_session_id === $args['data']['session_id']
							&& $args['notification'] === $notification;
					}
				)
			);

		$handler = new WC_Stripe_Webhook_Handler();
		$prop    = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $handler, $mock_scheduler );

		$handler->process_checkout_session_success( $notification );
	}

	/**
	 * Deferred checkout session success events should run handle_checkout_session_success when the job executes.
	 *
	 * @param string $event_type Stripe event type.
	 * @return void
	 * @dataProvider provide_deferred_checkout_session_success_event_types
	 */
	public function test_process_deferred_webhook_invokes_handle_checkout_session_success( string $event_type ): void {
		$checkout_session_id = 'cs_test_deferred_job';

		$notification = (object) [
			'type' => $event_type,
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_job',
				],
			],
		];

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'handle_checkout_session_success' ] )
			->getMock();

		$handler->expects( $this->once() )
			->method( 'handle_checkout_session_success' )
			->with(
				$this->callback(
					function ( $passed ) use ( $notification ) {
						return is_object( $passed )
							&& isset( $passed->type )
							&& $notification->type === $passed->type
							&& isset( $passed->data->object->id )
							&& $notification->data->object->id === $passed->data->object->id;
					}
				)
			);

		$handler->process_deferred_webhook(
			$event_type,
			[ 'session_id' => $checkout_session_id ],
			$notification
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_deferred_checkout_session_success_event_types(): array {
		return [
			'checkout.session.completed'               => [ 'checkout.session.completed' ],
			'checkout.session.async_payment_succeeded' => [ 'checkout.session.async_payment_succeeded' ],
		];
	}

	/**
	 * Deferred checkout session failure events should run handle_checkout_session_failure when the job executes.
	 *
	 * @param string $event_type Stripe event type.
	 * @return void
	 * @dataProvider provide_deferred_checkout_session_failure_event_types
	 */
	public function test_process_deferred_webhook_invokes_handle_checkout_session_failure( string $event_type ): void {
		$checkout_session_id = 'cs_test_deferred_failure_job';

		$notification = (object) [
			'type' => $event_type,
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_failure_job',
				],
			],
		];

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'handle_checkout_session_failure' ] )
			->getMock();

		$handler->expects( $this->once() )
			->method( 'handle_checkout_session_failure' )
			->with(
				$this->callback(
					function ( $passed ) use ( $notification ) {
						return is_object( $passed )
							&& isset( $passed->type )
							&& $notification->type === $passed->type
							&& isset( $passed->data->object->id )
							&& $notification->data->object->id === $passed->data->object->id;
					}
				)
			);

		$handler->process_deferred_webhook(
			$event_type,
			[ 'session_id' => $checkout_session_id ],
			$notification
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_deferred_checkout_session_failure_event_types(): array {
		return [
			'checkout.session.expired'              => [ 'checkout.session.expired' ],
			'checkout.session.async_payment_failed' => [ 'checkout.session.async_payment_failed' ],
		];
	}

	/**
	 * Test for `process_webhook_charge_failed`.
	 *
	 * @param string $order_status       The order status.
	 * @param bool   $order_status_final Whether the order status is final.
	 * @param string $charge_id          The charge ID.
	 * @param array  $event              The event type.
	 * @param string $expected_status    The expected order status.
	 * @param string $expected_note      The expected order note.
	 * @return void
	 * @dataProvider provide_test_process_webhook_charge_failed
	 */
	public function test_process_webhook_charge_failed(
		$order_status,
		$order_status_final,
		$charge_id,
		$event,
		$expected_status,
		$expected_note
	) {
		$order = WC_Helper_Order::create_order();
		$order->set_status( $order_status );
		$order->set_transaction_id( $charge_id );
		if ( $order_status_final ) {
			$order->update_meta_data( '_stripe_status_final', true );
		}
		$order->save();

		$notification = (object) [
			'type' => $event,
			'data' => (object) [
				'object' => (object) [
					'id' => 'ch_fQpkNKxmUrZ8t4CT7EHGS3Rg',
				],
			],
		];

		$this->mock_webhook_handler->process_webhook_charge_failed( $notification );

		if ( $charge_id ) { // Order not found charge ID.
			$final_order = wc_get_order( $order->get_id() );
			$this->assertEquals( $expected_status, $final_order->get_status() );

			if ( $expected_note ) {
				$notes = wc_get_order_notes(
					[
						'order_id' => $final_order->get_id(),
						'limit'    => 1,
					]
				);
				$this->assertSame( $expected_note, $notes[0]->content );
			}
		}
	}

	/**
	 * Provider for `test_process_webhook_charge_failed`.
	 *
	 * @return array
	 */
	public function provide_test_process_webhook_charge_failed() {
		return [
			'order already failed'                                     => [
				'order status'       => OrderStatus::FAILED,
				'order status final' => false,
				'charge id'          => 'ch_fQpkNKxmUrZ8t4CT7EHGS3Rg',
				'event'              => 'charge.failed',
				'expected status'    => OrderStatus::FAILED,
				'expected note'      => '',
			],
			'charge failed event, order already with the final status' => [
				'order status'       => OrderStatus::ON_HOLD,
				'order status final' => true,
				'charge id'          => 'ch_fQpkNKxmUrZ8t4CT7EHGS3Rg',
				'event'              => 'charge.failed',
				'expected status'    => OrderStatus::ON_HOLD,
				'expected note'      => 'This payment failed to clear.',
			],
			'charge failed event'                                      => [
				'order status'       => OrderStatus::ON_HOLD,
				'order status final' => false,
				'charge id'          => 'ch_fQpkNKxmUrZ8t4CT7EHGS3Rg',
				'event'              => 'charge.failed',
				'expected status'    => OrderStatus::FAILED,
				'expected note'      => 'This payment failed to clear. Order status changed from On hold to Failed.',
			],
			'charge expired event'                                     => [
				'order status'       => OrderStatus::ON_HOLD,
				'order status final' => false,
				'charge id'          => 'ch_fQpkNKxmUrZ8t4CT7EHGS3Rg',
				'event'              => 'charge.expired',
				'expected status'    => OrderStatus::FAILED,
				'expected note'      => 'This payment has expired. Order status changed from On hold to Failed.',
			],
		];
	}

	/**
	 * Test for `process_webhook_dispute`.
	 *
	 * @param bool $order_status_final Whether the order status is final.
	 * @param string $dispute_status   The dispute status.
	 * @param string $expected_status  The expected order status.
	 * @param string $expected_note    The expected order note.
	 * @return void
	 * @dataProvider provide_test_process_webhook_dispute
	 */
	public function test_process_webhook_dispute( $order_status, $order_status_final, $dispute_status, $expected_status, $expected_note ) {
		$charge_id = 'ch_fQpkNKxmUrZ8t4CT7EHGS3Rg';

		$order = WC_Helper_Order::create_order();
		$order->set_status( $order_status );
		$order->set_transaction_id( $charge_id );
		if ( $order_status_final ) {
			$order->update_meta_data( '_stripe_status_final', true );
		}
		$order->save();

		$notification = (object) [
			'type' => 'charge.dispute.created',
			'data' => (object) [
				'object' => (object) [
					'charge' => $charge_id,
					'status' => $dispute_status,
				],
			],
		];

		$this->mock_webhook_handler->process_webhook_dispute( $notification );

		$final_order = wc_get_order( $order->get_id() );

		$notes = wc_get_order_notes(
			[
				'order_id' => $final_order->get_id(),
				'limit'    => 1,
			]
		);

		$this->assertSame( $expected_status, $final_order->get_status() );
		$this->assertMatchesRegularExpression( $expected_note, $notes[0]->content );
	}

	/**
	 * Provider for `test_process_webhook_dispute`.
	 *
	 * @return array
	 */
	public function provide_test_process_webhook_dispute() {
		return [
			'response needed, order status not final'                      => [
				'order status'       => OrderStatus::PROCESSING,
				'order status final' => false,
				'dispute status'     => 'needs_response',
				'expected status'    => OrderStatus::ON_HOLD,
				'expected note'      => '/A dispute was created for this order. Response is needed./',
			],
			'response needed, order status not final, status is cancelled' => [
				'order status'       => OrderStatus::CANCELLED,
				'order status final' => false,
				'dispute status'     => 'needs_response',
				'expected status'    => OrderStatus::CANCELLED,
				'expected note'      => '/A dispute was created for this order. Response is needed./',
			],
			'response needed, order status final'                          => [
				'order status'       => OrderStatus::PROCESSING,
				'order status final' => true,
				'dispute status'     => 'needs_response',
				'expected status'    => OrderStatus::PROCESSING,
				'expected note'      => '/A dispute was created for this order. Response is needed./',
			],
			'response not needed, order status not final'                  => [
				'order status'       => OrderStatus::PROCESSING,
				'order status final' => false,
				'dispute status'     => 'lost',
				'expected status'    => OrderStatus::ON_HOLD,
				'expected note'      => '/A dispute was created for this order. Order status changed from Processing to On hold./',
			],
		];
	}

	/**
	 * Test for `process_payment_intent`.
	 *
	 * @param string $event_type The event type.
	 * @param string $order_status The order status.
	 * @param bool $order_locked Whether the order is locked.
	 * @param string $payment_type The payment method.
	 * @param bool $order_status_final Whether the order status is final.
	 * @param string $expected_status The expected order status.
	 * @param string $expected_note The expected order note.
	 * @param int $expected_process_payment_calls The expected number of calls to process_payment.
	 * @param int $expected_process_payment_intent_incomplete_calls The expected number of calls to process_payment_intent_incomplete.
	 * @return void
	 * @dataProvider provide_test_process_payment_intent
	 * @throws WC_Data_Exception When order status is invalid.
	 */
	public function test_process_payment_intent(
		$event_type,
		$order_status,
		$order_locked,
		$payment_type,
		$order_status_final,
		$expected_status,
		$expected_note,
		$expected_process_payment_calls,
		$expected_process_payment_intent_incomplete_calls
	) {
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_charge',
			[ &$mock_action_process_payment, 'action' ]
		);

		$mock_action_process_payment_intent_incomplete = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_intent_incomplete',
			[ &$mock_action_process_payment_intent_incomplete, 'action' ]
		);

		$this->mock_webhook_handler->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		$order = WC_Helper_Order::create_order();
		$order->set_status( $order_status );

		// Reset WC_Stripe_Order_Helper instance to avoid issues with other tests.
		WC_Stripe_Order_Helper::set_instance( null );

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		if ( $order_locked ) {
			$order->update_meta_data( '_stripe_lock_payment', ( time() + MINUTE_IN_SECONDS ) );
		}
		if ( $order_status_final ) {
			$order->update_meta_data( '_stripe_status_final', true );
		}
		$order_helper->update_stripe_upe_payment_type( $order, $payment_type );
		$order_helper->update_stripe_upe_waiting_for_redirect( $order, true );
		$order->save_meta_data();
		$order->save();

		$notification = [
			'type' => $event_type,
			'data' => [
				'object' => [
					'id'                 => 'pi_mock',
					'charges'            => [
						[
							'metadata' => [
								'order_id' => $order->get_id(),
							],
						],
					],
					'last_payment_error' => [
						'message' => 'Your card was declined. You can call your bank for details.',
					],
				],
			],
		];

		$notification = json_decode( wp_json_encode( $notification ) );

		$this->mock_webhook_handler->process_payment_intent( $notification );

		$final_order = wc_get_order( $order->get_id() );

		$this->assertSame( $expected_status, $final_order->get_status() );
		if ( ! empty( $expected_note ) ) {
			$notes = wc_get_order_notes(
				[
					'order_id' => $final_order->get_id(),
					'limit'    => 1,
				]
			);
			$this->assertMatchesRegularExpression( $expected_note, $notes[0]->content );
		}

		$this->assertEquals( $expected_process_payment_calls, $mock_action_process_payment->get_call_count() );
		$this->assertEquals( $expected_process_payment_intent_incomplete_calls, $mock_action_process_payment_intent_incomplete->get_call_count() );
	}

	/**
	 * Test that when a PaymentIntent is in the `processing` status,
	 * the order is updated to on-hold and the transaction ID is set.
	 */
	public function test_process_webhook_payment_intent_processing() {
		$notification = (object) [
			'type' => 'payment_intent.processing',
			'data' => (object) [
				'object' => (object) [
					'id'      => 'pi_mock',
					'charges' => (object) [
						'data' => [
							(object) [
								'id' => 'ch_mock',
							],
						],
					],
				],
			],
		];

		// Order must be previously set to pending and have at least the payment intent set.
		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->add_payment_intent_to_order( $notification->data->object->id, $order );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'lock_order_payment' ] )
			->getMock();

		$this->mock_webhook_handler->method( 'lock_order_payment' )->willReturn( false );

		$this->mock_webhook_handler->process_payment_intent( $notification );

		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEquals( OrderStatus::ON_HOLD, $updated_order->get_status() );
		$this->assertEquals( 'ch_mock', $updated_order->get_transaction_id() );

		// Grab the latest order note and verify the content.
		$notes = wc_get_order_notes(
			[
				'order_id' => $updated_order->get_id(),
				'limit'    => 1,
			]
		);
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( 'Stripe charge awaiting payment: ch_mock.', $notes[0]->content );
	}

	/**
	 * Provider for `test_process_payment_intent`.
	 *
	 * @return array
	 */
	public function provide_test_process_payment_intent() {
		return [
			'invalid status'                                                                    => [
				'event type'                                       => 'payment_intent.succeeded',
				'order status'                                     => OrderStatus::CANCELLED,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::CARD,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::CANCELLED,
				'expected note'                                    => '',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 0,
			],
			'order is locked'                                                                   => [
				'event type'                                       => 'payment_intent.succeeded',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => true,
				'payment type'                                     => WC_Stripe_Payment_Methods::CARD,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::PENDING,
				'expected note'                                    => '',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 0,
			],
			'success, payment_intent.requires_action, voucher payment'                          => [
				'event type'                                       => 'payment_intent.requires_action',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::BOLETO,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::ON_HOLD,
				'expected note'                                    => '/Awaiting payment. Order status changed from Pending payment to On hold./',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 0,
			],
			'success, payment_intent.succeeded, voucher payment'                                => [
				'event type'                                       => 'payment_intent.succeeded',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::BOLETO,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::PROCESSING,
				'expected note'                                    => '',
				'expected process payment calls'                   => 1,
				'expected process payment intent incomplete calls' => 0,
			],
			'success, payment_intent.succeeded, BLIK payment'                                   => [
				'event type'                                       => 'payment_intent.succeeded',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::BLIK,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::PROCESSING,
				'expected note'                                    => '',
				'expected process payment calls'                   => 1,
				'expected process payment intent incomplete calls' => 0,
			],
			'success, payment_intent.amount_capturable_updated, async payment, awaiting action' => [
				'event type'                                       => 'payment_intent.amount_capturable_updated',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::CARD,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::PENDING,
				'expected note'                                    => '',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 1,
			],
			'success, payment_intent.payment_failed, voucher payment'                           => [
				'event type'                                       => 'payment_intent.payment_failed',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::BOLETO,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::FAILED,
				'expected note'                                    => '/Payment not completed in time Order status changed from Pending payment to Failed./',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 0,
			],
			'success, payment_intent.payment_failed, IPP'                                       => [
				'event type'                                       => 'payment_intent.payment_failed',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::CARD_PRESENT,
				'order status final'                               => false,
				'expected status'                                  => OrderStatus::FAILED,
				'expected note'                                    => '/Stripe SCA authentication failed. Reason: Your card was declined. You can call your bank for details. Order status changed from Pending payment to Failed./',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 0,
			],
			'success, payment_intent.payment_failed, IPP, status final'                         => [
				'event type'                                       => 'payment_intent.payment_failed',
				'order status'                                     => OrderStatus::PENDING,
				'order locked'                                     => false,
				'payment type'                                     => WC_Stripe_Payment_Methods::CARD_PRESENT,
				'order status final'                               => true,
				'expected status'                                  => OrderStatus::PENDING,
				'expected note'                                    => '/Stripe SCA authentication failed. Reason: Your card was declined. You can call your bank for details./',
				'expected process payment calls'                   => 0,
				'expected process payment intent incomplete calls' => 0,
			],
		];
	}

	/**
	 * Test for `process_webhook_charge_succeeded`, that it is skipped for synchronous payment methods.
	 *
	 * @param string $payment_method_type The payment method type.
	 * @return void
	 * @dataProvider provide_test_process_webhook_charge_succeeded_skipped_for_synchronous_payment_methods
	 */
	public function test_process_webhook_charge_succeeded_skipped_for_synchronous_payment_methods( $payment_method_type ) {
		$charge_id    = 'ch_mock9G5K2X1Q';
		$notification = (object) [
			'type' => 'charge.succeeded',
			'data' => (object) [
				'object' => (object) [
					'id'                     => $charge_id,
					'payment_method_details' => (object) [
						'type' => $payment_method_type,
					],
					'captured'               => true,
					'balance_transaction'    => (object) [
						'fee' => 100,
					],
				],
			],
		];

		// We want to assert an early return by checking that we don't run the next line, i.e.
		// retrieving the order by charge ID. However, we are using WC_Stripe_Helper::get_order_by_charge_id()
		// which is a static method, and phpunit does not natively support mocking static methods.

		// We will instead create the mock order for the charge ID, so we are able to retrieve an order,
		// and make sure the next few checks pass so that it reaches the line that calls update_fees()
		// which we can mock and check if it was called.
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'on-hold' );
		$order->set_transaction_id( $charge_id );
		$order->save();

		if ( WC_Stripe_Payment_Methods::SEPA_DEBIT === $payment_method_type ) {
			$this->mock_webhook_handler->expects( $this->once() )->method( 'update_fees' );
		} else {
			$this->mock_webhook_handler->expects( $this->never() )->method( 'update_fees' );
		}

		$this->mock_webhook_handler->process_webhook_charge_succeeded( $notification );
	}

	/**
	 * Provider for `test_process_webhook_charge_succeeded_skipped_for_synchronous_payment_methods`.
	 *
	 * @return array
	 */
	public function provide_test_process_webhook_charge_succeeded_skipped_for_synchronous_payment_methods() {
		return [
			'card'           => [ WC_Stripe_Payment_Methods::CARD ],
			'amazon_pay'     => [ WC_Stripe_Payment_Methods::AMAZON_PAY ],
			'three_d_secure' => [ 'three_d_secure' ],
			'sepa_debit'     => [ WC_Stripe_Payment_Methods::SEPA_DEBIT ],
		];
	}

	/**
	 * Tests for `process_webhook_refund_updated`.
	 *
	 * @param string $notification_status The notification status.
	 * @param bool   $email_triggered Whether an email should be triggered.
	 * @param string $expected_note The expected order note.
	 * @return void
	 *
	 * @dataProvider provide_test_process_webhook_refund_updated
	 */
	public function test_process_webhook_refund_updated( $notification_status, $email_triggered, $expected_note ) {
		$refund_id = 'refund_123';
		$charge_id = 'ch_123';

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( $charge_id );
		$order->save();

		WC_Stripe_Order_Helper::get_instance()->update_stripe_refund_id( $order, $refund_id );
		$order->save_meta_data();

		$refund_order = WC_Helper_Order::create_order();
		$refund_order->set_parent_id( $order->get_id() );
		$refund_order->save();

		$notification = (object) [
			'data' => (object) [
				'object' => (object) [
					'id'             => $refund_id,
					'charge'         => $charge_id,
					'amount'         => 1000,
					'failure_reason' => 'bank_account_rejected',
					'status'         => $notification_status,
				],
			],
		];

		$this->mock_webhook_handler
			->expects( $email_triggered ? $this->once() : $this->never() )
			->method( 'send_failed_refund_emails' );

		$this->mock_webhook_handler->process_webhook_refund_updated( $notification );

		$notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);

		if ( empty( $expected_note ) ) {
			$this->assertEquals( [], $notes );
			return;
		}

		$this->assertCount( 1, $notes );
		if ( '' === $expected_note ) {
			$this->assertSame( '', $notes[0]->content );
		} else {
			$this->assertMatchesRegularExpression( $expected_note, $notes[0]->content );
		}
	}

	/**
	 * Test that checkout session failure returns early when no order is found.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_failure_returns_when_order_is_not_found(): void {
		$notification = (object) [
			'type' => 'checkout.session.expired',
			'data' => (object) [
				'object' => (object) [
					'id' => 'cs_missing_order',
				],
			],
		];

		$hook_calls = 0;
		$hook       = function () use ( &$hook_calls ) {
			++$hook_calls;
		};
		add_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10, 2 );

		$this->mock_webhook_handler->process_checkout_session_failure( $notification );
		remove_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10 );

		$resolved_order_property = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'resolved_order' );
		$resolved_order_property->setAccessible( true );

		$this->assertNull( $resolved_order_property->getValue( $this->mock_webhook_handler ) );
		$this->assertSame( 0, $hook_calls );
	}

	/**
	 * Provider for checkout session failure event types.
	 *
	 * @return array
	 */
	public function provide_checkout_session_failure_event_types(): array {
		return [
			'checkout.session.expired'              => [
				'event_type'    => 'checkout.session.expired',
				'expected_note' => 'The checkout session has expired.',
			],
			'checkout.session.async_payment_failed' => [
				'event_type'    => 'checkout.session.async_payment_failed',
				'expected_note' => 'The async payment for this checkout session has failed.',
			],
		];
	}

	/**
	 * Test that checkout session failure marks pending orders as failed for both event types.
	 *
	 * @dataProvider provide_checkout_session_failure_event_types
	 *
	 * @param string $event_type Event type.
	 * @param string $expected_note Expected note content.
	 * @return void
	 */
	public function test_process_checkout_session_failure_marks_order_as_failed_for_event_type( string $event_type, string $expected_note ): void {
		$checkout_session_id = 'cs_test_failed';
		$order               = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$order_helper = $this->createPartialMock( WC_Stripe_Order_Helper::class, [ 'is_stripe_status_final' ] );
		$order_helper->expects( $this->once() )
			->method( 'is_stripe_status_final' )
			->willReturn( false );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$notification = (object) [
			'type' => $event_type,
			'data' => (object) [
				'object' => (object) [
					'id' => $checkout_session_id,
				],
			],
		];

		$hook_calls = 0;
		$hook       = function ( $hook_order, $hook_notification ) use ( $order, $notification, &$hook_calls ) {
			++$hook_calls;
			$this->assertSame( $order->get_id(), $hook_order->get_id() );
			$this->assertSame( $notification, $hook_notification );
		};
		add_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10, 2 );

		$this->mock_webhook_handler->process_checkout_session_failure( $notification );
		remove_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10 );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( OrderStatus::FAILED, $order->get_status() );
		$this->assertSame( 1, $hook_calls );

		$notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$this->assertNotEmpty( $notes );
		$this->assertStringContainsString( $expected_note, $notes[0]->content );
	}

	/**
	 * Test that checkout session failure does not change status for final Stripe orders.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_failure_returns_for_final_stripe_status(): void {
		$checkout_session_id = 'cs_test_final_status';
		$order               = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PROCESSING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$order_helper = $this->createPartialMock( WC_Stripe_Order_Helper::class, [ 'is_stripe_status_final' ] );
		$order_helper->expects( $this->once() )
			->method( 'is_stripe_status_final' )
			->willReturn( true );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$notification = (object) [
			'type' => 'checkout.session.async_payment_failed',
			'data' => (object) [
				'object' => (object) [
					'id' => $checkout_session_id,
				],
			],
		];

		$hook_calls = 0;
		$hook       = function () use ( &$hook_calls ) {
			++$hook_calls;
		};
		add_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10, 2 );

		$this->mock_webhook_handler->process_checkout_session_failure( $notification );
		remove_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10 );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( OrderStatus::PROCESSING, $order->get_status() );
		$this->assertSame( 0, $hook_calls );
	}

	/**
	 * Test that duplicate checkout session failure webhooks return early when the order is already failed.
	 *
	 * @dataProvider provide_checkout_session_failure_event_types
	 *
	 * @param string $event_type Event type.
	 * @param string $unused_note Unused; provider shares rows with other tests.
	 * @return void
	 */
	public function test_process_checkout_session_failure_returns_early_when_order_already_failed( string $event_type, string $unused_note ): void {
		$checkout_session_id = 'cs_test_duplicate';
		$order               = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::FAILED );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$notes_before = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 100,
			]
		);

		$order_helper = $this->createPartialMock( WC_Stripe_Order_Helper::class, [ 'is_stripe_status_final' ] );
		$order_helper->expects( $this->once() )
			->method( 'is_stripe_status_final' )
			->willReturn( false );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'send_failed_order_email' ] )
			->getMock();

		$webhook_handler->expects( $this->never() )->method( 'send_failed_order_email' );

		$notification = (object) [
			'type' => $event_type,
			'data' => (object) [
				'object' => (object) [
					'id' => $checkout_session_id,
				],
			],
		];

		$hook_calls = 0;
		$hook       = function () use ( &$hook_calls ) {
			++$hook_calls;
		};
		add_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10, 2 );

		$webhook_handler->process_checkout_session_failure( $notification );

		remove_action( 'wc_gateway_stripe_process_webhook_payment_error', $hook, 10 );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( OrderStatus::FAILED, $order->get_status() );
		$this->assertSame( 0, $hook_calls );

		$notes_after = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 100,
			]
		);
		$this->assertCount( count( $notes_before ), $notes_after );
	}

	/**
	 * Provider for `test_process_webhook_refund_updated`.
	 *
	 * @return array
	 */
	public function provide_test_process_webhook_refund_updated() {
		return [
			'invalid refund status' => [
				'notification status' => 'invalid_status',
				'email triggered'     => false,
				'expected note'       => '',
			],
			'failed refund'         => [
				'notification status' => 'failed',
				'email triggered'     => true,
				'expected note'       => '/Refund failed for <span class="woocommerce-Price-amount amount"><bdi( class="woocommerce-Price-bidi")?><span class="woocommerce-Price-currencySymbol">&#36;<\/span>10.00<\/bdi><\/span> - Refund ID: refund_123 - Reason: Unknown reason Order status changed from Pending payment to Processing\./',
			],
			'canceled refund'       => [
				'notification status' => 'canceled',
				'email triggered'     => true,
				'expected note'       => '/Refund canceled for <span class="woocommerce-Price-amount amount"><bdi( class="woocommerce-Price-bidi")?><span class="woocommerce-Price-currencySymbol">&#36;<\/span>10.00<\/bdi><\/span> - Refund ID: refund_123 - Reason: Unknown reason Order status changed from Pending payment to Processing\./',
			],
		];
	}

	/**
	 * Test that `process_checkout_session_metadata` makes the correct API request on success.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_metadata_success(): void {
		$checkout_session_id = 'cs_test_abc123';
		$metadata            = [
			'order_id'   => '100',
			'order_key'  => 'wc_order_abc',
			'signature'  => '100:abc',
			'tax_amount' => 10,
		];

		$request_captured = false;
		$pre_http_filter  = function ( $return_value, $parsed_args, $url ) use ( $checkout_session_id, $metadata, &$request_captured ) {
			$expected_url = WC_Stripe_API::ENDPOINT . 'checkout/sessions/' . $checkout_session_id;
			if ( $url !== $expected_url ) {
				return $return_value;
			}
			$request_captured = true;
			$this->assertEquals( 'POST', $parsed_args['method'] );
			$this->assertEquals( $metadata, $parsed_args['body']['metadata'] );
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [ 'id' => $checkout_session_id ] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		$handler = new WC_Stripe_Webhook_Handler();
		$handler->process_checkout_session_metadata( $checkout_session_id, $metadata );

		remove_filter( 'pre_http_request', $pre_http_filter );

		$this->assertTrue( $request_captured, 'Expected the API request to be made.' );
	}

	/**
	 * Test that `process_checkout_session_metadata` throws an exception when the API returns an error response.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_metadata_api_error_response(): void {
		$checkout_session_id = 'cs_test_abc123';
		$metadata            = [
			'order_id'   => '100',
			'order_key'  => 'wc_order_abc',
			'signature'  => '100:abc',
			'tax_amount' => 10,
		];

		$error_message   = 'No such checkout session.';
		$pre_http_filter = function () use ( $error_message ) {
			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'error' => [
							'message' => $error_message,
						],
					]
				),
				'response' => [
					'code'    => 404,
					'message' => 'Not Found',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		$handler = new WC_Stripe_Webhook_Handler();
		$caught  = null;
		try {
			$handler->process_checkout_session_metadata( $checkout_session_id, $metadata );
		} catch ( Exception $e ) {
			$caught = $e;
		}

		remove_filter( 'pre_http_request', $pre_http_filter );

		$this->assertNotNull( $caught, 'Expected an exception to be thrown.' );
		$this->assertInstanceOf( \WC_Stripe_Exception::class, $caught, 'Expected an instance of WC_Stripe_Exception.' );
		$this->assertSame( $error_message, $caught->getMessage() );
	}

	/**
	 * Test that `process_checkout_session` schedules the metadata job with the correct arguments.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_schedules_metadata_job(): void {
		$checkout_session_id = 'cs_test_schedule123';

		// Create an order and associate it with the checkout session.
		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		// Build the mock notification.
		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_abc',
				],
			],
		];

		// Mock the action scheduler service.
		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$scheduled_args = null;
		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->callback(
					function ( $timestamp ) {
						$this->assertIsInt( $timestamp, 'Expected timestamp to be an integer.' );
						$this->assertGreaterThanOrEqual( time() + 2 * MINUTE_IN_SECONDS, $timestamp, 'Expected timestamp to be in the future.' );

						return true;
					}
				),
				'wc_stripe_process_checkout_session_metadata',
				$this->callback(
					function ( $args ) use ( $checkout_session_id, &$scheduled_args ) {
						$scheduled_args = $args;
						return isset( $args['checkout_session_id'] ) && $args['checkout_session_id'] === $checkout_session_id
							&& isset( $args['metadata']['order_id'] )
							&& isset( $args['metadata']['order_key'] )
							&& isset( $args['metadata']['signature'] )
							&& isset( $args['metadata']['tax_amount'] );
					}
				)
			);

		// Rebuild mock webhook handler with necessary methods mocked.
		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ] )
			->getMock();

		// Include 'payment_method' to avoid undefined-property notice (phpunit converts notices/warnings to exceptions).
		$this->mock_webhook_handler->method( 'get_intent_from_order' )
			->willReturn( (object) array_merge( self::MOCK_PAYMENT_INTENT, [ 'payment_method' => null ] ) );

		$this->mock_webhook_handler->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		$this->mock_webhook_handler->method( 'process_response' );

		// Inject the mock action scheduler service.
		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $this->mock_webhook_handler, $mock_scheduler );

		$this->mock_webhook_handler->process_checkout_session_success( $notification );

		// Verify the metadata contains the correct order data.
		$this->assertNotNull( $scheduled_args );
		$this->assertEquals( $checkout_session_id, $scheduled_args['checkout_session_id'] );
		$this->assertEquals( $order->get_order_number(), $scheduled_args['metadata']['order_id'] );
		$this->assertEquals( $order->get_order_key(), $scheduled_args['metadata']['order_key'] );
		$this->assertNotEmpty( $scheduled_args['metadata']['signature'] );
		$this->assertIsInt( $scheduled_args['metadata']['tax_amount'] );
	}

	/**
	 * Test that `process_checkout_session` does not schedule the metadata job when an exception is thrown during processing.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_does_not_schedule_metadata_job_on_exception(): void {
		$checkout_session_id = 'cs_test_exception123';

		// Create an order and associate it with the checkout session.
		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		// Build the mock notification.
		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_abc',
				],
			],
		];

		// Mock the action scheduler service - it should not be called.
		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->never() )->method( 'schedule_job' );

		// Rebuild mock webhook handler where process_response throws an exception.
		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ] )
			->getMock();

		// Include 'payment_method' to avoid undefined-property notice (phpunit converts notices/warnings to exceptions).
		$this->mock_webhook_handler->method( 'get_intent_from_order' )
			->willReturn( (object) array_merge( self::MOCK_PAYMENT_INTENT, [ 'payment_method' => null ] ) );

		$this->mock_webhook_handler->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		$this->mock_webhook_handler->method( 'process_response' )
			->willThrowException( new Exception( 'Test processing exception' ) );

		// Inject the mock action scheduler service.
		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $this->mock_webhook_handler, $mock_scheduler );

		$this->mock_webhook_handler->process_checkout_session_success( $notification );

		// Needed to avoid flagging the test as `risky`. Actual assertions happen in the mock expectations above.
		$this->assertTrue( true );
	}

	/**
	 * Test that `process_checkout_session_success` sets the payment method title on the order.
	 *
	 * When adaptive pricing is used, payments go through checkout sessions and are finalised via
	 * the `checkout.session.completed` webhook. Without an explicit call to
	 * `set_payment_method_title_for_order()`, the order retains the gateway's default title
	 * ("Stripe") instead of the actual method name (e.g. "Credit / Debit Card" or "iDEAL").
	 *
	 * @dataProvider provider_checkout_session_payment_method_titles
	 *
	 * @param string $payment_method_type Stripe payment method type (e.g. 'card', 'ideal').
	 * @param string $expected_title      Expected WooCommerce payment method title on the order.
	 * @return void
	 */
	public function test_process_checkout_session_success_sets_payment_method_title( string $payment_method_type, string $expected_title ): void {
		$checkout_session_id = 'cs_test_title_' . $payment_method_type;

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_abc',
				],
			],
		];

		// Build a payment method object that mimics a Stripe expanded payment_method field.
		$payment_method_object = (object) [
			'id'   => 'pm_mock_' . $payment_method_type,
			'type' => $payment_method_type,
		];

		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->once() )->method( 'schedule_job' );

		$this->mock_webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ] )
			->getMock();

		$this->mock_webhook_handler->method( 'get_intent_from_order' )
			->willReturn( (object) array_merge( self::MOCK_PAYMENT_INTENT, [ 'payment_method' => $payment_method_object ] ) );

		$this->mock_webhook_handler->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		$this->mock_webhook_handler->method( 'process_response' );

		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $this->mock_webhook_handler, $mock_scheduler );

		$this->mock_webhook_handler->process_checkout_session_success( $notification );

		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEquals( $expected_title, $updated_order->get_payment_method_title() );
	}

	/**
	 * Data provider for `test_process_checkout_session_success_sets_payment_method_title`.
	 *
	 * @return array[]
	 */
	public function provider_checkout_session_payment_method_titles(): array {
		return [
			'card payment'   => [ WC_Stripe_Payment_Methods::CARD, 'Credit / Debit Card' ],
			'klarna payment' => [ WC_Stripe_Payment_Methods::KLARNA, 'Klarna' ],
			'ideal payment'  => [ WC_Stripe_Payment_Methods::IDEAL, 'iDEAL | Wero' ],
		];
	}

	/**
	 * Test that `checkout.session.async_payment_succeeded` processes on-hold orders.
	 *
	 * @return void
	 */
	public function test_process_async_checkout_session_success_for_on_hold_order(): void {
		$checkout_session_id = 'cs_test_async_success_on_hold';

		// Create an order in on-hold status and associate it with the checkout session.
		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::ON_HOLD );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$notification = (object) [
			'type' => 'checkout.session.async_payment_succeeded',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_async_success',
				],
			],
		];

		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->isType( 'int' ),
				'wc_stripe_process_checkout_session_metadata',
				$this->callback(
					function ( $args ) use ( $checkout_session_id ) {
						return isset( $args['checkout_session_id'] ) && $checkout_session_id === $args['checkout_session_id'];
					}
				)
			);

		$this->mock_webhook_handler->method( 'get_intent_from_order' )
			->willReturn( (object) array_merge( self::MOCK_PAYMENT_INTENT, [ 'payment_method' => null ] ) );

		$this->mock_webhook_handler->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) self::MOCK_PAYMENT_INTENT['charges']['data'][0] );

		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $this->mock_webhook_handler, $mock_scheduler );

		$this->mock_webhook_handler->process_webhook( wp_json_encode( $notification ) );

		$updated_order = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( WC_Order::class, $updated_order );
		$this->assertTrue( $updated_order->has_status( [ OrderStatus::PROCESSING, OrderStatus::COMPLETED ] ) );
	}
}
