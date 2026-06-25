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
	 * Payloads captured from the wc_stripe_unexpected_charge_detected action during a test.
	 *
	 * @var array
	 */
	private $captured_unexpected_charges = [];

	/**
	 * Listeners registered via spy_on_unexpected_charge_detected(), removed in tear_down().
	 *
	 * @var callable[]
	 */
	private $unexpected_charge_listeners = [];

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
	 * Tear down the test.
	 */
	public function tear_down() {
		foreach ( $this->unexpected_charge_listeners as $listener ) {
			remove_action( 'wc_stripe_unexpected_charge_detected', $listener, 10 );
		}
		$this->unexpected_charge_listeners = [];

		parent::tear_down();
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
	 * Creates an order carrying a Stripe PaymentIntent that was ultimately settled via the given
	 * gateway — the shared setup for the unexpected-charge detection tests.
	 *
	 * @param string $payment_method The gateway recorded on the order.
	 * @param string $intent_id      The Stripe PaymentIntent ID stored on the order.
	 * @return WC_Order
	 */
	private function create_order_with_intent( string $payment_method, string $intent_id ): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PROCESSING );
		$order->set_payment_method( $payment_method );
		$order->set_currency( 'EUR' );
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->save();

		return $order;
	}

	/**
	 * Builds a Stripe charge webhook notification for the unexpected-charge detection tests.
	 *
	 * @param string $type             The webhook event type ('charge.succeeded' or 'charge.captured').
	 * @param string $intent_id        The parent PaymentIntent ID (matches the order's stored intent).
	 * @param array  $charge_overrides Charge properties to override the defaults.
	 * @return object
	 */
	private function build_charge_notification( string $type, string $intent_id, array $charge_overrides = [] ): object {
		$charge = (object) array_merge(
			[
				'id'             => 'py_unexpected',
				'captured'       => true,
				'payment_intent' => $intent_id,
				'amount'         => 1000,
				'currency'       => 'eur',
			],
			$charge_overrides
		);

		return (object) [
			'type' => $type,
			'data' => (object) [ 'object' => $charge ],
		];
	}

	/**
	 * Registers a spy on wc_stripe_unexpected_charge_detected that records every invocation into
	 * $this->captured_unexpected_charges. The listener is removed automatically in tear_down().
	 */
	private function spy_on_unexpected_charge_detected(): void {
		$listener = function ( $order, $charge, $type ) {
			$this->captured_unexpected_charges[] = [
				'order_id'     => $order instanceof WC_Order ? $order->get_id() : null,
				'charge_id'    => is_object( $charge ) ? $charge->id : null,
				'webhook_type' => $type,
			];
		};

		add_action( 'wc_stripe_unexpected_charge_detected', $listener, 10, 3 );
		$this->unexpected_charge_listeners[] = $listener;
	}

	/**
	 * Returns the content of the most recent note on the given order (empty string when none).
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	private function get_latest_order_note_content( WC_Order $order ): string {
		$notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);

		return empty( $notes ) ? '' : $notes[0]->content;
	}

	/**
	 * Returns the content of every note on the given order, newest first.
	 *
	 * @param int $order_id
	 * @return string[]
	 */
	private function get_order_note_contents( int $order_id ): array {
		return array_map(
			static function ( $note ) {
				return $note->content;
			},
			wc_get_order_notes( [ 'order_id' => $order_id ] )
		);
	}

	/**
	 * Asserts that at least one note on the given order matches the PCRE pattern.
	 *
	 * @param string $pattern  PCRE pattern.
	 * @param int    $order_id Order ID.
	 */
	private function assert_order_has_note_matching( string $pattern, int $order_id ): void {
		$notes = $this->get_order_note_contents( $order_id );
		$this->assertNotEmpty(
			preg_grep( $pattern, $notes ),
			sprintf( 'Failed asserting that an order note matches %s. Notes: %s', $pattern, implode( ' | ', $notes ) )
		);
	}

	/**
	 * Asserts that at least one note on the given order contains the substring.
	 *
	 * @param string $needle   Expected substring.
	 * @param int    $order_id Order ID.
	 */
	private function assert_order_has_note_containing( string $needle, int $order_id ): void {
		$this->assert_order_has_note_matching( '/' . preg_quote( $needle, '/' ) . '/', $order_id );
	}

	/**
	 * When charge.succeeded fires for a charge whose ID isn't stored on any order (because the shopper
	 * settled the order via a different gateway), the handler must fall back to looking up the order
	 * by the parent PaymentIntent and flag the unexpected charge instead of silently dropping the event.
	 */
	public function test_process_webhook_charge_succeeded_flags_unexpected_charge_via_payment_intent_fallback() {
		$intent_id = 'pi_unexpected_charge_lookup';

		$order        = $this->create_order_with_intent( 'cod', $intent_id );
		$notification = $this->build_charge_notification(
			'charge.succeeded',
			$intent_id,
			[
				'id'                     => 'py_unexpected_xxx',
				'amount'                 => 4200,
				'payment_method_details' => (object) [ 'type' => 'sepa_debit' ],
			]
		);

		$this->mock_webhook_handler->expects( $this->never() )
			->method( 'process_response' );

		$this->spy_on_unexpected_charge_detected();

		$this->mock_webhook_handler->process_webhook_charge_succeeded( $notification );

		$this->assertCount( 1, $this->captured_unexpected_charges );
		$this->assertSame( $order->get_id(), $this->captured_unexpected_charges[0]['order_id'] );
		$this->assertSame( 'py_unexpected_xxx', $this->captured_unexpected_charges[0]['charge_id'] );
		$this->assertSame( 'charge.succeeded', $this->captured_unexpected_charges[0]['webhook_type'] );

		$note = $this->get_latest_order_note_content( $order );
		$this->assertStringContainsString( $intent_id, $note );
		$this->assertStringContainsString( 'py_unexpected_xxx', $note );
	}

	/**
	 * charge.succeeded fallback must not flag when the charge has not been captured yet.
	 */
	public function test_process_webhook_charge_succeeded_fallback_skips_uncaptured_charge() {
		$intent_id = 'pi_uncaptured';

		// The order must exist so the only reason the charge isn't flagged is the uncaptured guard.
		$this->create_order_with_intent( 'cod', $intent_id );
		$notification = $this->build_charge_notification(
			'charge.succeeded',
			$intent_id,
			[
				'id'                     => 'py_uncaptured',
				'captured'               => false,
				'payment_method_details' => (object) [ 'type' => 'sepa_debit' ],
			]
		);

		$this->spy_on_unexpected_charge_detected();

		$this->mock_webhook_handler->process_webhook_charge_succeeded( $notification );

		$this->assertCount( 0, $this->captured_unexpected_charges );
	}

	/**
	 * charge.succeeded for an unexpected card charge (shopper completed 3DS but never returned, then
	 * settled the order via a different gateway) must still flag it. This verifies that
	 * the unexpected-charge fallback runs before the synchronous-payment-method filter that otherwise short-
	 * circuits card / Amazon Pay / 3DS events.
	 */
	public function test_process_webhook_charge_succeeded_flags_unexpected_card_charge_via_payment_intent_fallback() {
		$intent_id = 'pi_card_unexpected';

		$order        = $this->create_order_with_intent( 'klarna_payments', $intent_id );
		$notification = $this->build_charge_notification(
			'charge.succeeded',
			$intent_id,
			[
				'id'                     => 'ch_card_unexpected',
				'amount'                 => 5000,
				'payment_method_details' => (object) [ 'type' => 'card' ],
			]
		);

		$this->spy_on_unexpected_charge_detected();

		$this->mock_webhook_handler->process_webhook_charge_succeeded( $notification );

		$this->assertCount( 1, $this->captured_unexpected_charges );
		$this->assertSame( $order->get_id(), $this->captured_unexpected_charges[0]['order_id'] );
		$this->assertSame( 'ch_card_unexpected', $this->captured_unexpected_charges[0]['charge_id'] );
		$this->assertSame( 'charge.succeeded', $this->captured_unexpected_charges[0]['webhook_type'] );
	}

	/**
	 * charge.captured for a captured charge whose ID isn't on any order (manual-capture unexpected charge)
	 * must fall back to the parent PaymentIntent and flag the unexpected charge.
	 */
	public function test_process_webhook_capture_flags_unexpected_charge_via_payment_intent_fallback() {
		$intent_id = 'pi_capture_unexpected';

		$order        = $this->create_order_with_intent( 'klarna_payments', $intent_id );
		$notification = $this->build_charge_notification(
			'charge.captured',
			$intent_id,
			[
				'id'     => 'ch_capture_unexpected',
				'amount' => 8000,
			]
		);

		$this->spy_on_unexpected_charge_detected();

		$this->mock_webhook_handler->process_webhook_capture( $notification );

		$this->assertCount( 1, $this->captured_unexpected_charges );
		$this->assertSame( $order->get_id(), $this->captured_unexpected_charges[0]['order_id'] );
		$this->assertSame( 'ch_capture_unexpected', $this->captured_unexpected_charges[0]['charge_id'] );
		$this->assertSame( 'charge.captured', $this->captured_unexpected_charges[0]['webhook_type'] );

		$note = $this->get_latest_order_note_content( $order );
		$this->assertStringContainsString( $intent_id, $note );
		$this->assertStringContainsString( 'ch_capture_unexpected', $note );
	}

	/**
	 * charge.captured for an order paid via Stripe must not flag an unexpected charge.
	 */
	public function test_process_webhook_capture_does_not_flag_when_paid_via_stripe() {
		$intent_id = 'pi_stripe_capture';

		$this->create_order_with_intent( 'stripe', $intent_id );
		$notification = $this->build_charge_notification(
			'charge.captured',
			$intent_id,
			[ 'id' => 'ch_stripe_capture' ]
		);

		$this->spy_on_unexpected_charge_detected();

		$this->mock_webhook_handler->process_webhook_capture( $notification );

		$this->assertCount( 0, $this->captured_unexpected_charges );
	}

	/**
	 * Repeat unexpected-charge detections for the same PaymentIntent on the same order must be no-ops: only one
	 * note is added and the action fires exactly once, even when multiple webhook paths detect it.
	 */
	public function test_flag_unexpected_charge_is_idempotent_per_intent() {
		$intent_id = 'pi_dedup';

		$order = $this->create_order_with_intent( 'klarna_payments', $intent_id );

		// The same charge is detected first via charge.succeeded and then via charge.captured.
		$charge_overrides     = [
			'id'                     => 'py_dedup',
			'payment_method_details' => (object) [ 'type' => 'sepa_debit' ],
		];
		$charge_notification  = $this->build_charge_notification( 'charge.succeeded', $intent_id, $charge_overrides );
		$capture_notification = $this->build_charge_notification( 'charge.captured', $intent_id, $charge_overrides );

		$this->spy_on_unexpected_charge_detected();

		$this->mock_webhook_handler->process_webhook_charge_succeeded( $charge_notification );
		$this->mock_webhook_handler->process_webhook_capture( $capture_notification );

		$this->assertCount( 1, $this->captured_unexpected_charges );

		$notes            = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 10,
			]
		);
		$unexpected_notes = array_filter(
			$notes,
			static function ( $note ) use ( $intent_id ) {
				return false !== strpos( $note->content, $intent_id ) && false !== strpos( $note->content, 'unexpected' );
			}
		);
		$this->assertCount( 1, $unexpected_notes );
	}

	/**
	 * The unexpected-charge note must link to the Stripe dashboard for the charge's own mode
	 * (derived from the charge's `livemode`), not the gateway's configured test/live flag.
	 *
	 * @dataProvider provider_unexpected_charge_dashboard_mode
	 */
	public function test_unexpected_charge_note_dashboard_url_follows_charge_livemode( $livemode, $expected_url ) {
		$intent_id = 'pi_unexpected_mode';

		$order = $this->create_order_with_intent( 'cod', $intent_id );

		$charge_overrides = [ 'id' => 'py_unexpected_mode' ];
		if ( null !== $livemode ) {
			$charge_overrides['livemode'] = $livemode;
		}
		$notification = $this->build_charge_notification( 'charge.succeeded', $intent_id, $charge_overrides );

		$this->mock_webhook_handler->process_webhook_charge_succeeded( $notification );

		$this->assertStringContainsString( $expected_url, $this->get_latest_order_note_content( $order ) );
	}

	/**
	 * Data provider for test_unexpected_charge_note_dashboard_url_follows_charge_livemode.
	 *
	 * @return array
	 */
	public function provider_unexpected_charge_dashboard_mode() {
		return [
			'live charge links to live dashboard' => [ true, 'https://dashboard.stripe.com/payments/pi_unexpected_mode' ],
			'test charge links to test dashboard' => [ false, 'https://dashboard.stripe.com/test/payments/pi_unexpected_mode' ],
			'missing livemode falls back to test' => [ null, 'https://dashboard.stripe.com/test/payments/pi_unexpected_mode' ],
		];
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
	 * Regression: when the order is found but its payment lock is held by a concurrent process
	 * (e.g. the order-received redirect handler holding it across a Stripe API call), settlement
	 * must be re-queued, otherwise the paid order is left stuck pending.
	 *
	 * @return void
	 */
	public function test_handle_checkout_session_success_requeues_when_order_payment_locked(): void {
		$checkout_session_id = 'cs_test_locked_requeue';

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		// Clear per-session caches so the handler doesn't short-circuit on a stale lock entry.
		WC_Stripe_Database_Cache::delete( 'checkout_session_lock_' . $checkout_session_id );
		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		// Simulate a concurrent process holding the order payment lock.
		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);
		$order_helper->method( 'lock_order_payment' )->willReturn( true );
		$order_helper->method( 'unlock_order_payment' );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_locked',
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
						// The lock clears in ~1s, so the retry uses a short dedicated backoff rather than
						// the 2-minute deferred delay — the order must not sit pending that long.
						$this->assertGreaterThanOrEqual( $start + 10, $timestamp );
						$this->assertLessThan( $start + MINUTE_IN_SECONDS, $timestamp );

						return true;
					}
				),
				'wc_stripe_deferred_webhook',
				$this->callback(
					function ( $args ) use ( $checkout_session_id ) {
						return 'checkout.session.completed' === ( $args['type'] ?? '' )
							&& ( $args['data']['session_id'] ?? '' ) === $checkout_session_id;
					}
				)
			);

		$handler = new WC_Stripe_Webhook_Handler();
		$prop    = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $handler, $mock_scheduler );

		$handler->process_checkout_session_success( $notification );

		// Settlement is deferred to the retry, so the order must still be unsettled.
		$this->assertTrue( wc_get_order( $order->get_id() )->has_status( OrderStatus::PENDING ) );
	}

	/**
	 * Regression: when the order is locked and settlement is re-queued, process_webhook must NOT fire
	 * wc_stripe_webhook_received on this live pass. Settlement hasn't happened yet; the action fires
	 * once from the retry. Firing here would double-dispatch and run before the payment settles.
	 *
	 * @return void
	 */
	public function test_process_webhook_skips_received_action_when_order_payment_locked(): void {
		$checkout_session_id = 'cs_test_locked_no_action';

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		WC_Stripe_Database_Cache::delete( 'checkout_session_lock_' . $checkout_session_id );
		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);
		$order_helper->method( 'lock_order_payment' )->willReturn( true );
		$order_helper->method( 'unlock_order_payment' );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_locked_no_action',
				],
			],
		];

		$handler = new WC_Stripe_Webhook_Handler();
		$prop    = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $handler, $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );

		$fired    = 0;
		$listener = function () use ( &$fired ) {
			++$fired;
		};
		add_action( 'wc_stripe_webhook_received', $listener );

		try {
			$handler->process_webhook( wp_json_encode( $notification ) );
		} finally {
			remove_action( 'wc_stripe_webhook_received', $listener );
		}

		$this->assertSame( 0, $fired, 'wc_stripe_webhook_received must not fire while settlement is deferred.' );
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
				$this->assertContains( $expected_note, $this->get_order_note_contents( $final_order->get_id() ) );
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
	 * Builds a pre_http_request stub for the "list Checkout Sessions by PaymentIntent" lookup.
	 *
	 * @param string      $intent_id           PaymentIntent the failed event references.
	 * @param string|null $checkout_session_id Session to return, or null to simulate no match.
	 * @param bool        $called              Set to true when the lookup endpoint is hit.
	 * @return callable
	 */
	private function stub_checkout_session_lookup( string $intent_id, ?string $checkout_session_id, &$called = false ) {
		return function ( $return_value, $parsed_args, $url ) use ( $intent_id, $checkout_session_id, &$called ) {
			$expected_url = WC_Stripe_API::ENDPOINT . 'checkout/sessions?payment_intent=' . $intent_id . '&limit=1';
			if ( $url !== $expected_url ) {
				return $return_value;
			}
			$called = true;
			$data   = null === $checkout_session_id ? [] : [
				[
					'id'             => $checkout_session_id,
					'payment_intent' => $intent_id,
				],
			];
			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'object' => 'list',
						'data'   => $data,
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
	}

	/**
	 * Adaptive Pricing declines leave the order linked only to its Checkout Session — the failed
	 * intent carries no order metadata and the intent ID isn't stored on the order. The handler runs
	 * the (extra) session lookup only for intents flagged as Checkout Session intents, and marks the
	 * order failed when the session resolves it.
	 *
	 * @dataProvider provide_process_payment_intent_checkout_session_fallback
	 *
	 * @param bool   $has_marker       Whether the failed intent carries the Adaptive Pricing checkout_type.
	 * @param bool   $session_resolves Whether Stripe returns the order's Checkout Session for the intent.
	 * @param bool   $expect_lookup    Whether the session lookup should be attempted.
	 * @param string $expected_status  The order status after processing.
	 */
	public function test_process_payment_intent_checkout_session_fallback(
		bool $has_marker,
		bool $session_resolves,
		bool $expect_lookup,
		string $expected_status
	): void {
		$checkout_session_id = 'cs_test_pi_fallback';
		$intent_id           = 'pi_test_pi_fallback';

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$lookup_called   = false;
		$pre_http_filter = $this->stub_checkout_session_lookup( $intent_id, $session_resolves ? $checkout_session_id : null, $lookup_called );
		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		$intent = [
			'id'                 => $intent_id,
			'last_payment_error' => (object) [ 'message' => 'Your card was declined.' ],
		];
		if ( $has_marker ) {
			$intent['metadata'] = (object) [ 'checkout_type' => WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE ];
		}

		$notification = (object) [
			'type' => 'payment_intent.payment_failed',
			'data' => (object) [
				'object' => (object) $intent,
			],
		];

		$this->mock_webhook_handler->process_payment_intent( $notification );
		remove_filter( 'pre_http_request', $pre_http_filter );

		$this->assertSame( $expect_lookup, $lookup_called );
		$this->assertSame( $expected_status, wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * Data provider for `test_process_payment_intent_checkout_session_fallback`.
	 *
	 * @return array<string, array{0: bool, 1: bool, 2: bool, 3: string}>
	 */
	public function provide_process_payment_intent_checkout_session_fallback(): array {
		return [
			'flagged intent, session resolves -> order failed'       => [ true, true, true, OrderStatus::FAILED ],
			'flagged intent, no matching session -> order untouched' => [ true, false, true, OrderStatus::PENDING ],
			'unflagged intent -> lookup skipped, order untouched'    => [ false, true, false, OrderStatus::PENDING ],
		];
	}

	/**
	 * Adaptive Pricing Checkout Session PaymentIntents can arrive without order metadata. When
	 * Stripe includes the Checkout Session reference on the event, the handler should use that
	 * direct link instead of depending on a second API lookup.
	 *
	 * @dataProvider provide_process_adaptive_pricing_payment_intent_order_reference_fallback
	 *
	 * @param string      $event_type           The PaymentIntent event type.
	 * @param string|null $checkout_type        Checkout type metadata to include on the intent.
	 * @param string      $expected_status      The order status after processing.
	 * @param bool        $expect_deferred      Whether the event should schedule deferred settlement.
	 * @param bool        $expect_intent_stored Whether the PaymentIntent ID should be stored on the order.
	 * @param bool        $order_ref_resolves   Whether order_reference should resolve the order directly.
	 * @param bool        $expect_lookup        Whether the PaymentIntent session lookup should be attempted.
	 */
	public function test_process_adaptive_pricing_payment_intent_order_reference_fallback(
		string $event_type,
		?string $checkout_type,
		string $expected_status,
		bool $expect_deferred,
		bool $expect_intent_stored,
		bool $order_ref_resolves,
		bool $expect_lookup
	): void {
		$event_slug          = str_replace( [ 'payment_intent.', '_' ], '', $event_type );
		$checkout_session_id = 'cs_test_pi_reference_' . $event_slug;
		$intent_id           = 'pi_test_pi_reference_' . $event_slug;
		$order_reference     = $order_ref_resolves ? $checkout_session_id : 'cs_test_stale_' . $event_slug;

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$lookup_called   = false;
		$pre_http_filter = $this->stub_checkout_session_lookup( $intent_id, $checkout_session_id, $lookup_called );
		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		if ( $expect_deferred ) {
			$mock_scheduler->expects( $this->once() )
				->method( 'schedule_job' )
				->with(
					$this->isType( 'int' ),
					'wc_stripe_deferred_webhook',
					$this->callback(
						function ( $args ) use ( $event_type, $intent_id, $order ) {
							return ( $args['type'] ?? '' ) === $event_type
								&& ( $args['data']['intent_id'] ?? '' ) === $intent_id
								&& $order->get_id() === ( $args['data']['order_id'] ?? 0 )
								&& isset( $args['notification'] );
						}
					)
				);
		} else {
			$mock_scheduler->expects( $this->never() )->method( 'schedule_job' );
		}

		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $this->mock_webhook_handler, $mock_scheduler );

		$metadata = (object) [];
		if ( null !== $checkout_type ) {
			$metadata->checkout_type = $checkout_type;
		}

		$notification = (object) [
			'type' => $event_type,
			'data' => (object) [
				'object' => (object) [
					'id'                 => $intent_id,
					'metadata'           => $metadata,
					'payment_details'    => (object) [
						'order_reference' => $order_reference,
					],
					'last_payment_error' => (object) [ 'message' => 'Your card was declined.' ],
				],
			],
		];

		$this->mock_webhook_handler->process_payment_intent( $notification );
		remove_filter( 'pre_http_request', $pre_http_filter );

		$final_order        = wc_get_order( $order->get_id() );
		$stored_intent_id   = WC_Stripe_Order_Helper::get_instance()->get_intent_id_from_order( $final_order );
		$expected_intent_id = $expect_intent_stored ? $intent_id : '';

		$this->assertSame( $expect_lookup, $lookup_called );
		$this->assertSame( $expected_status, $final_order->get_status() );
		$this->assertSame( $expected_intent_id, $stored_intent_id );
	}

	/**
	 * Data provider for `test_process_adaptive_pricing_payment_intent_order_reference_fallback`.
	 *
	 * @return array<string, array{0: string, 1: string|null, 2: string, 3: bool, 4: bool, 5: bool, 6: bool}>
	 */
	public function provide_process_adaptive_pricing_payment_intent_order_reference_fallback(): array {
		return [
			'AP succeeded intent -> order linked and deferred'              => [
				'payment_intent.succeeded',
				WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE,
				OrderStatus::PENDING,
				true,
				true,
				true,
				false,
			],
			'AP succeeded intent, stale reference -> lookup resolves order' => [
				'payment_intent.succeeded',
				WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE,
				OrderStatus::PENDING,
				true,
				true,
				false,
				true,
			],
			'AP capturable intent -> order linked and deferred'             => [
				'payment_intent.amount_capturable_updated',
				WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE,
				OrderStatus::PENDING,
				true,
				true,
				true,
				false,
			],
			'AP failed intent -> order failed'                              => [
				'payment_intent.payment_failed',
				WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE,
				OrderStatus::FAILED,
				false,
				false,
				true,
				false,
			],
			'standard checkout succeeded intent -> order ignored'           => [
				'payment_intent.succeeded',
				'standard_checkout',
				OrderStatus::PENDING,
				false,
				false,
				true,
				false,
			],
			'unmarked succeeded intent -> order ignored'                    => [
				'payment_intent.succeeded',
				null,
				OrderStatus::PENDING,
				false,
				false,
				true,
				false,
			],
		];
	}

	/**
	 * Checkout Session-recovered successful PaymentIntents must be retried through
	 * process_payment_intent() when the order lock is held, because the intent has not been stored yet.
	 */
	public function test_process_adaptive_pricing_payment_intent_order_reference_requeues_when_order_locked(): void {
		$suffix              = str_replace( '-', '', wp_generate_uuid4() );
		$checkout_session_id = 'cs_test_pi_reference_locked_' . substr( $suffix, 0, 12 );
		$intent_id           = 'pi_test_pi_reference_locked_' . substr( $suffix, 0, 12 );

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$this->assertSame( $order->get_id(), WC_Stripe_Helper::get_order_by_checkout_session_id( $checkout_session_id )->get_id() );
		$this->assertTrue( $order->has_status( OrderStatus::PENDING ) );

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);
		$order_helper->method( 'lock_order_payment' )->willReturn( true );
		$order_helper->method( 'unlock_order_payment' );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$notification = (object) [
			'type' => 'payment_intent.succeeded',
			'data' => (object) [
				'object' => (object) [
					'id'              => $intent_id,
					'metadata'        => (object) [
						'checkout_type' => WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE,
					],
					'payment_details' => (object) [
						'order_reference' => $checkout_session_id,
					],
				],
			],
		];

		$start               = time();
		$scheduled_timestamp = null;
		$scheduled_hook      = null;
		$scheduled_args      = null;
		$mock_scheduler      = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_job' )
			->willReturnCallback(
				function ( $timestamp, $hook, $args ) use ( &$scheduled_timestamp, &$scheduled_hook, &$scheduled_args ) {
					$scheduled_timestamp = $timestamp;
					$scheduled_hook      = $hook;
					$scheduled_args      = $args;
				}
			);

		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $this->mock_webhook_handler, $mock_scheduler );

		$this->mock_webhook_handler->process_payment_intent( $notification );

		$final_order      = wc_get_order( $order->get_id() );
		$stored_intent_id = WC_Stripe_Order_Helper::get_instance()->get_intent_id_from_order( $final_order );

		$this->assertIsInt( $scheduled_timestamp );
		$this->assertGreaterThanOrEqual( $start + 10, $scheduled_timestamp );
		$this->assertLessThan( $start + MINUTE_IN_SECONDS, $scheduled_timestamp );
		$this->assertSame( 'wc_stripe_deferred_webhook', $scheduled_hook );
		$this->assertSame( 'payment_intent.succeeded', $scheduled_args['type'] ?? '' );
		$this->assertSame( $intent_id, $scheduled_args['data']['intent_id'] ?? '' );
		$this->assertSame( $order->get_id(), $scheduled_args['data']['order_id'] ?? 0 );
		$this->assertTrue( $scheduled_args['data']['retry_process_payment_intent'] ?? false );
		$this->assertSame( $notification, $scheduled_args['notification'] ?? null );
		$this->assertSame( OrderStatus::PENDING, $final_order->get_status() );
		$this->assertEmpty( $stored_intent_id );
	}

	/**
	 * Lock retry jobs need to recover and store the intent before the normal deferred settlement path can run.
	 */
	public function test_process_deferred_webhook_retries_payment_intent_processing_when_requested(): void {
		$notification = (object) [
			'type' => 'payment_intent.succeeded',
			'data' => (object) [
				'object' => (object) [
					'id' => 'pi_test_retry_process_payment_intent',
				],
			],
		];

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'process_payment_intent', 'handle_deferred_payment_intent_succeeded' ] )
			->getMock();

		$handler->expects( $this->once() )
			->method( 'process_payment_intent' )
			->with( $notification );

		$handler->expects( $this->never() )
			->method( 'handle_deferred_payment_intent_succeeded' );

		$handler->process_deferred_webhook(
			'payment_intent.succeeded',
			[
				'retry_process_payment_intent' => true,
			],
			$notification
		);
	}

	/**
	 * charge.failed is intentionally not used to resolve declined Adaptive Pricing orders — the
	 * paired payment_intent.payment_failed event handles that — so it must not run the session lookup.
	 */
	public function test_process_webhook_charge_failed_does_not_resolve_declined_adaptive_pricing_order_via_checkout_session() {
		$checkout_session_id = 'cs_test_charge_no_resolve';
		$intent_id           = 'pi_test_charge_no_resolve';

		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		$lookup_called   = false;
		$pre_http_filter = $this->stub_checkout_session_lookup( $intent_id, $checkout_session_id, $lookup_called );
		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		$notification = (object) [
			'type' => 'charge.failed',
			'data' => (object) [
				'object' => (object) [
					'id'             => 'ch_test_no_resolve',
					'payment_intent' => $intent_id,
				],
			],
		];

		$this->mock_webhook_handler->process_webhook_charge_failed( $notification );
		remove_filter( 'pre_http_request', $pre_http_filter );

		$this->assertFalse( $lookup_called, 'charge.failed must not trigger the Checkout Session lookup.' );
		$final_order = wc_get_order( $order->get_id() );
		$this->assertSame( OrderStatus::PENDING, $final_order->get_status() );
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

		$this->assertSame( $expected_status, $final_order->get_status() );
		$this->assert_order_has_note_matching( $expected_note, $final_order->get_id() );
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
			$this->assert_order_has_note_matching( $expected_note, $final_order->get_id() );
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

		// Verify the awaiting-payment note was added to the order.
		$this->assert_order_has_note_containing( 'Stripe charge awaiting payment: ch_mock.', $updated_order->get_id() );
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
	 * Tests that a refund webhook finds the order via the intent ID when the charge ID is missing.
	 */
	public function test_process_webhook_refund_finds_order_via_intent_when_charge_id_missing() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, 'pi_intent_1' );
		$order->save();

		$notification = (object) [
			'data' => (object) [
				'object' => (object) [
					'id'              => 'ch_missing',
					'object'          => 'charge',
					'payment_intent'  => 'pi_intent_1',
					'captured'        => true,
					'amount'          => 5000,
					'amount_refunded' => 1000,
					'currency'        => 'usd',
					'refunds'         => (object) [
						'data' => [
							(object) [
								'id'                  => 're_xyz',
								'amount'              => 1000,
								'balance_transaction' => 'txn_1',
							],
						],
					],
				],
			],
		];

		$this->mock_webhook_handler->process_webhook_refund( $notification );

		$reloaded = wc_get_order( $order->get_id() );

		// Charge ID back-filled and refund synced against the recovered order.
		$this->assertSame( 'ch_missing', $reloaded->get_transaction_id() );
		$this->assertSame( 're_xyz', WC_Stripe_Order_Helper::get_instance()->get_stripe_refund_id( $reloaded ) );
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

		if ( empty( $expected_note ) ) {
			$this->assertSame( [], $this->get_order_note_contents( $order->get_id() ) );
			return;
		}

		$this->assert_order_has_note_matching( $expected_note, $order->get_id() );
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

		$this->assert_order_has_note_containing( $expected_note, $order->get_id() );
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
				'expected note'       => '/Refund failed for <span class="woocommerce-Price-amount amount"><bdi( class="woocommerce-Price-bidi")?><span class="woocommerce-Price-currencySymbol"( translate="no")?>&#36;<\/span>10.00<\/bdi><\/span> - Refund ID: refund_123 - Reason: Unknown reason Order status changed from Pending payment to Processing\./',
			],
			'canceled refund'       => [
				'notification status' => 'canceled',
				'email triggered'     => true,
				'expected note'       => '/Refund canceled for <span class="woocommerce-Price-amount amount"><bdi( class="woocommerce-Price-bidi")?><span class="woocommerce-Price-currencySymbol"( translate="no")?>&#36;<\/span>10.00<\/bdi><\/span> - Refund ID: refund_123 - Reason: Unknown reason Order status changed from Pending payment to Processing\./',
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
	 * Test that `process_payment_intent_metadata` updates the intent with the order description and metadata.
	 *
	 * @return void
	 */
	public function test_process_payment_intent_metadata_success(): void {
		$payment_intent_id = 'pi_test_abc123';

		$request = [
			'description' => 'Test Blog - Order 100',
			'metadata'    => [
				'order_id'   => '100',
				'order_key'  => 'wc_order_test123',
				'signature'  => '100:abc123hash',
				'tax_amount' => 250,
			],
		];

		$request_captured = false;
		$pre_http_filter  = function ( $return_value, $parsed_args, $url ) use ( $payment_intent_id, $request, &$request_captured ) {
			$expected_url = WC_Stripe_API::ENDPOINT . 'payment_intents/' . $payment_intent_id;
			if ( $url !== $expected_url ) {
				return $return_value;
			}
			$request_captured = true;
			$this->assertEquals( 'POST', $parsed_args['method'] );
			$this->assertIsArray( $parsed_args['body'] );
			$this->assertArrayHasKey( 'description', $parsed_args['body'] );
			$this->assertArrayHasKey( 'metadata', $parsed_args['body'] );
			$this->assertEquals( $request['description'], $parsed_args['body']['description'] );
			$this->assertEquals( $request['metadata'], $parsed_args['body']['metadata'] );
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [ 'id' => $payment_intent_id ] ),
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
		$handler->process_payment_intent_metadata( $payment_intent_id, $request );

		remove_filter( 'pre_http_request', $pre_http_filter );

		$this->assertTrue( $request_captured, 'Expected the API request to be made.' );
	}

	/**
	 * Test that `process_payment_intent_metadata` throws an exception when the API returns an error response.
	 *
	 * @return void
	 */
	public function test_process_payment_intent_metadata_api_error_response(): void {
		$error_message   = 'No such payment intent.';
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
			$handler->process_payment_intent_metadata( 'pi_test_abc123', [ 'metadata' => [ 'order_id' => '100' ] ] );
		} catch ( Exception $e ) {
			$caught = $e;
		}

		remove_filter( 'pre_http_request', $pre_http_filter );

		$this->assertNotNull( $caught, 'Expected an exception to be thrown.' );
		$this->assertInstanceOf( \WC_Stripe_Exception::class, $caught, 'Expected an instance of WC_Stripe_Exception.' );
		$this->assertSame( $error_message, $caught->getMessage() );
	}

	/**
	 * Test that `process_checkout_session` schedules the payment intent metadata job with the correct arguments.
	 *
	 * @return void
	 */
	public function test_process_checkout_session_schedules_payment_intent_metadata_job(): void {
		$checkout_session_id = 'cs_test_schedule123';

		// Create an order and associate it with the checkout session.
		$order = WC_Helper_Order::create_order();
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order->save_meta_data();

		// Defensively clear any leftover session-specific cache entries.
		// handle_checkout_session_success() returns early without calling schedule_job()
		// when the per-session lock is present, which caused this test to intermittently
		// fail in CI under paratest/randomized ordering.
		WC_Stripe_Database_Cache::delete( 'checkout_session_lock_' . $checkout_session_id );
		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

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

		// Capture a fixed baseline before scheduling so the timestamp assertion can't race a 1-second rollover.
		$test_start_time = time();

		// Mock the action scheduler service.
		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$scheduled_args = null;
		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->callback(
					function ( $timestamp ) use ( $test_start_time ) {
						$this->assertIsInt( $timestamp, 'Expected timestamp to be an integer.' );

						// The timestamp is captured between the test's start and now, so bound it on both sides
						// to assert it lands exactly 2 minutes out rather than some arbitrary point after.
						$current_time = time();
						$this->assertGreaterThanOrEqual( $test_start_time + 2 * MINUTE_IN_SECONDS, $timestamp, 'Expected timestamp to be at least 2 minutes in the future.' );
						$this->assertLessThanOrEqual( $current_time + 2 * MINUTE_IN_SECONDS, $timestamp, 'Expected timestamp to be at most 2 minutes in the future.' );

						return true;
					}
				),
				'wc_stripe_process_payment_intent_metadata',
				$this->callback(
					function ( $args ) use ( &$scheduled_args ) {
						$scheduled_args = $args;
						return isset( $args['payment_intent_id'] ) && isset( $args['request'] ) && is_array( $args['request'] );
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

		// Verify the job is scheduled with the intent and the full payload snapshot.
		$this->assertNotNull( $scheduled_args );
		$this->assertEquals( 'pi_test_abc', $scheduled_args['payment_intent_id'] );
		$this->assertEquals( sprintf( '%1$s - Order %2$s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ), $scheduled_args['request']['description'] );
		$this->assertEquals( $order->get_order_number(), $scheduled_args['request']['metadata']['order_id'] );
		$this->assertEquals( $order->get_order_key(), $scheduled_args['request']['metadata']['order_key'] );
		$this->assertNotEmpty( $scheduled_args['request']['metadata']['signature'] );
		$this->assertIsInt( $scheduled_args['request']['metadata']['tax_amount'] );
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
	 * Redirect-based APMs that are saved as SEPA tokens (Bancontact, iDEAL, Sofort) must not fatal in
	 * the Adaptive Pricing / Checkout Sessions webhook when the save-payment-method flag is set.
	 *
	 * Stripe returns the APM PaymentMethod (no `sepa_debit` child). The handler must convert it to the
	 * charge's `generated_sepa_debit` PaymentMethod before saving — and skip saving entirely when Stripe
	 * did not generate a reusable mandate — instead of passing a non-SEPA object to set_fingerprint().
	 *
	 * Regression for STRIPE-1205.
	 *
	 * @dataProvider provider_checkout_session_apm_sepa_save
	 *
	 * @param string  $payment_method_type   Redirect APM type (e.g. 'bancontact', 'ideal', 'sofort').
	 * @param ?string $generated_sepa_debit  The generated SEPA debit PM id on the charge, or null.
	 * @param bool    $expect_token_saved    Whether a SEPA token should be saved for the user.
	 * @return void
	 */
	public function test_process_checkout_session_success_saves_apm_as_sepa_token( string $payment_method_type, ?string $generated_sepa_debit, bool $expect_token_saved ): void {
		$checkout_session_id = 'cs_test_apm_' . $payment_method_type;
		$generated_pm_id     = 'pm_generated_sepa_' . $payment_method_type;

		$user_id = self::factory()->user->create();

		$order = WC_Helper_Order::create_order( $user_id );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order_helper->update_should_save_stripe_payment_method( $order, true );
		$order->save_meta_data();

		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $checkout_session_id,
					'payment_intent' => 'pi_test_' . $payment_method_type,
				],
			],
		];

		// The APM PaymentMethod as Stripe returns it: its own type, no sepa_debit child.
		$payment_method_object = (object) [
			'id'   => 'pm_mock_' . $payment_method_type,
			'type' => $payment_method_type,
		];

		// The charge exposes the reusable mandate (or null) via payment_method_details.<type>.generated_sepa_debit.
		$charge = (object) [
			'id'                     => 'ch_mock_' . $payment_method_type,
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => (object) [
				$payment_method_type => (object) [
					'generated_sepa_debit' => $generated_sepa_debit,
				],
			],
		];

		// Intercept the generated SEPA PaymentMethod retrieval and return a SEPA-shaped object.
		$pre_http_filter = function ( $return_value, $parsed_args, $url ) use ( $generated_pm_id ) {
			if ( WC_Stripe_API::ENDPOINT . 'payment_methods/' . $generated_pm_id !== $url ) {
				return $return_value;
			}
			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'id'         => $generated_pm_id,
						'type'       => WC_Stripe_Payment_Methods::SEPA_DEBIT,
						'customer'   => 'cus_mock_apm',
						'sepa_debit' => [
							'last4'       => '7061',
							'fingerprint' => 'Fxxxxxxxxxxxxxxx',
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
		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->once() )->method( 'schedule_job' );

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ] )
			->getMock();

		$handler->method( 'get_intent_from_order' )
			->willReturn( (object) array_merge( self::MOCK_PAYMENT_INTENT, [ 'payment_method' => $payment_method_object ] ) );
		$handler->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
		$handler->method( 'process_response' );

		$prop = new ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'action_scheduler_service' );
		$prop->setAccessible( true );
		$prop->setValue( $handler, $mock_scheduler );

		// Must not fatal.
		$handler->process_checkout_session_success( $notification );

		remove_filter( 'pre_http_request', $pre_http_filter );

		// The save flag is always cleared so webhook retries do not loop.
		$this->assertFalse( $order_helper->get_should_save_stripe_payment_method( wc_get_order( $order->get_id() ) ) );

		$tokens = WC_Payment_Tokens::get_customer_tokens( $user_id );
		if ( $expect_token_saved ) {
			$this->assertCount( 1, $tokens );
			$token = array_shift( $tokens );
			$this->assertInstanceOf( WC_Payment_Token_SEPA::class, $token );
			$this->assertSame( $generated_pm_id, $token->get_token() );
			$this->assertSame( '7061', $token->get_last4() );
		} else {
			$this->assertCount( 0, $tokens );
		}
	}

	/**
	 * Data provider for `test_process_checkout_session_success_saves_apm_as_sepa_token`.
	 *
	 * @return array[]
	 */
	public function provider_checkout_session_apm_sepa_save(): array {
		return [
			'bancontact with generated mandate' => [ WC_Stripe_Payment_Methods::BANCONTACT, 'pm_generated_sepa_bancontact', true ],
			'ideal with generated mandate'      => [ WC_Stripe_Payment_Methods::IDEAL, 'pm_generated_sepa_ideal', true ],
			'sofort with generated mandate'     => [ WC_Stripe_Payment_Methods::SOFORT, 'pm_generated_sepa_sofort', true ],
			'bancontact without mandate'        => [ WC_Stripe_Payment_Methods::BANCONTACT, null, false ],
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
				'wc_stripe_process_payment_intent_metadata',
				$this->callback(
					function ( $args ) {
						return isset( $args['payment_intent_id'] ) && isset( $args['request'] ) && is_array( $args['request'] );
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

	/**
	 * Guards that events are processed only when their Stripe account matches the connected
	 * account, failing open on a missing or unknown account, in both live and test modes.
	 *
	 * @dataProvider provide_event_belongs_to_connected_account
	 *
	 * @param string      $mode              Plugin mode ('yes' test, 'no' live).
	 * @param string      $field             Event field carrying the account ('account' for Connect, 'context' for agentic).
	 * @param string|null $event_account     The account ID to place on the event, or null to omit it.
	 * @param string      $connected_account The connected account ID.
	 * @param bool        $expected          Whether the event should be allowed through.
	 */
	public function test_event_belongs_to_connected_account( string $mode, string $field, $event_account, string $connected_account, bool $expected ) {
		update_option(
			'woocommerce_stripe_settings',
			array_merge( (array) get_option( 'woocommerce_stripe_settings', [] ), [ 'testmode' => $mode ] )
		);

		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'get_connected_account_id' ] )
			->getMock();
		$handler->method( 'get_connected_account_id' )->willReturn( $connected_account );

		$event = (object) [
			'id'   => 'evt_mock_1234',
			'type' => 'payment_intent.succeeded',
		];
		if ( null !== $event_account ) {
			$event->$field = $event_account;
		}

		$method = new ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'event_belongs_to_connected_account' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( $handler, $event ) );
	}

	/**
	 * Provider for `test_event_belongs_to_connected_account`.
	 *
	 * @return array
	 */
	public function provide_event_belongs_to_connected_account() {
		return [
			'Connect: live mode, matching account is processed'    => [ 'no', 'account', 'acct_connected', 'acct_connected', true ],
			'Connect: live mode, mismatched account is skipped'    => [ 'no', 'account', 'acct_other', 'acct_connected', false ],
			'Connect: test mode, matching account is processed'    => [ 'yes', 'account', 'acct_connected', 'acct_connected', true ],
			'Connect: test mode, mismatched account is skipped'    => [ 'yes', 'account', 'acct_other', 'acct_connected', false ],
			'Agentic: matching context account is processed'       => [ 'yes', 'context', 'acct_connected', 'acct_connected', true ],
			'Agentic: mismatched context account is skipped'       => [ 'yes', 'context', 'acct_other', 'acct_connected', false ],
			'event without an account field is processed'          => [ 'no', 'account', null, 'acct_connected', true ],
			'event with an empty account field is processed'       => [ 'yes', 'account', '', 'acct_connected', true ],
			'unknown connected account fails open (live)'          => [ 'no', 'account', 'acct_other', '', true ],
			'unknown connected account fails open (test, agentic)' => [ 'yes', 'context', 'acct_other', '', true ],
		];
	}

	/**
	 * Locks in that a real agentic `v1.delegated_checkout.*` payload is matched against the
	 * connected account via its top-level `context` field, processing on a match and skipping
	 * on a mismatch. Uses the committed sample event so reviewers can replay the same body.
	 */
	public function test_event_belongs_to_connected_account_reads_context_from_real_agentic_event() {
		$event         = json_decode( file_get_contents( __DIR__ . '/dummy-data/agentic_customize_checkout_event.json' ) );
		$event_account = 'acct_sample_connected'; // The `context` value in the fixture.
		$reflection    = new ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'event_belongs_to_connected_account' );
		$reflection->setAccessible( true );

		$this->assertSame( $event_account, $event->context, 'Fixture is expected to carry the account in `context`.' );

		$matching = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )->setMethods( [ 'get_connected_account_id' ] )->getMock();
		$matching->method( 'get_connected_account_id' )->willReturn( $event_account );
		$this->assertTrue( $reflection->invoke( $matching, $event ) );

		$mismatched = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )->setMethods( [ 'get_connected_account_id' ] )->getMock();
		$mismatched->method( 'get_connected_account_id' )->willReturn( 'acct_someone_else' );
		$this->assertFalse( $reflection->invoke( $mismatched, $event ) );
	}
}
