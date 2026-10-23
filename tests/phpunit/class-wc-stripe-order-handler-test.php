<?php

/**
 * Class WC_Stripe_Order_Handler tests.
 */
class WC_Stripe_Order_Handler_Test extends WP_UnitTestCase {

	/**
	 * Order handler instance.
	 *
	 * @var WC_Stripe_Order_Handler
	 */
	private $order_handler;

	/**
	 * The `pre_http_request` callback added by mock_stripe_requests().
	 *
	 * @var callable|null
	 */
	private $stripe_request_mock;

	private const CHECKOUT_SESSION_ID = 'cs_test_cancel_unpaid';

	public function set_up() {
		parent::set_up();

		$this->order_handler = $this->createPartialMock( WC_Stripe_Order_Handler::class, [ 'get_intent_from_order' ] );
	}

	public function test_prevent_cancelling_orders_awaiting_action() {
		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->set_payment_awaiting_action( $order );

		// Read in a fresh order object with meta like `date_modified` set.
		$order = wc_get_order( $order->get_id() );

		// Test when false is passed that the order is not cancelled.
		$this->assertFalse( $this->order_handler->prevent_cancelling_orders_awaiting_action( false, $order ) );

		// Test non-stripe payment method is cancelled.
		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		// Test a stripe order with no intent is cancelled.
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();
		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		// Test a stripe order with meta + intent is not cancelled.
		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->with( $order )
			->willReturn( (object) [ 'intent_id' => 'pm_mockintentID' ] );
		$this->assertFalse( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		// Test a stripe order with meta + intent but was modified more than a day ago is cancelled.
		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-2 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
	}

	/**
	 * A SetupIntent that Stripe hasn't settled yet outlives the one-day awaiting-action window
	 * (bank microdeposits take days), so the order must not be auto-cancelled while it can still
	 * succeed — otherwise the later setup_intent.succeeded webhook finds a cancelled order.
	 *
	 * @param string $status   SetupIntent status returned for the order.
	 * @param bool   $expected Whether the order should still be cancelled.
	 * @dataProvider provide_setup_intent_statuses_for_cancellation
	 */
	public function test_does_not_cancel_orders_with_unsettled_setup_intent( string $status, bool $expected ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		// Well outside the one-day awaiting-action grace, so only the SetupIntent guard can save it.
		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-5 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'seti_mock',
					'object' => 'setup_intent',
					'status' => $status,
				]
			);

		$this->assertSame(
			$expected,
			$this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ),
			"SetupIntent status {$status} produced the wrong cancellation decision."
		);
	}

	/**
	 * Data provider for {@see test_does_not_cancel_orders_with_unsettled_setup_intent()}.
	 *
	 * @return array
	 */
	public function provide_setup_intent_statuses_for_cancellation(): array {
		return [
			'requires_action awaits microdeposits' => [ 'requires_action', false ],
			'requires_confirmation still settling' => [ 'requires_confirmation', false ],
			'processing still settling'            => [ 'processing', false ],
			'canceled is done'                     => [ 'canceled', true ],
			'requires_payment_method is done'      => [ 'requires_payment_method', true ],
		];
	}

	/**
	 * A PaymentIntent that is not waiting on bank verification keeps the one-day cancellation
	 * behaviour. Card authentication must still be treated as abandoned.
	 */
	public function test_payment_intent_awaiting_action_is_still_cancelled_after_a_day() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-5 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'pi_mock',
					'object' => 'payment_intent',
					'status' => 'requires_action',
				]
			);

		$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
	}

	/**
	 * US bank verification outlives the one-day awaiting-action window, and Adaptive Pricing
	 * never sets that meta. The order stays pending only while Stripe can still complete the
	 * debit. Card authentication, including an intent that merely allows us_bank_account, does not.
	 *
	 * @param string $gateway_id WooCommerce payment method id.
	 * @param string $upe_type   Value of _stripe_upe_payment_type, or an empty string.
	 * @param object $intent     Intent returned for the order.
	 * @param bool   $expected   Whether the order should still be cancelled.
	 * @dataProvider provide_payment_intents_for_bank_verification_cancellation
	 */
	public function test_payment_intent_awaiting_bank_verification_is_not_cancelled_after_a_day( string $gateway_id, string $upe_type, object $intent, bool $expected ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $gateway_id );
		if ( '' !== $upe_type ) {
			$order->update_meta_data( '_stripe_upe_payment_type', $upe_type );
		}

		// Outside the one-day window, with no awaiting-action meta, so only the bank guard can keep it.
		$modified_date = new DateTime( current_time( 'mysql' ) );
		$modified_date->modify( '-5 days' );
		$order->set_date_modified( $modified_date->format( 'Y-m-d H:i:s' ) );

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn( $intent );

		$this->assertSame(
			$expected,
			$this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ),
			"PaymentIntent status {$intent->status} produced the wrong cancellation decision."
		);
	}

	/**
	 * Data provider for {@see test_payment_intent_awaiting_bank_verification_is_not_cancelled_after_a_day()}.
	 *
	 * @return array
	 */
	public function provide_payment_intents_for_bank_verification_cancellation(): array {
		$ach_gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_Payment_Methods::ACH;

		return [
			'microdeposits'           => [
				$ach_gateway_id,
				'',
				(object) [
					'id'          => 'pi_mock',
					'object'      => 'payment_intent',
					'status'      => 'requires_action',
					'next_action' => (object) [ 'type' => 'verify_with_microdeposits' ],
				],
				false,
			],
			'bank redirect'           => [
				$ach_gateway_id,
				'',
				(object) [
					'id'             => 'pi_mock',
					'object'         => 'payment_intent',
					'status'         => 'requires_action',
					'payment_method' => (object) [
						'id'   => 'pm_mock',
						'type' => 'us_bank_account',
					],
					'next_action'    => (object) [ 'type' => 'redirect_to_url' ],
				],
				false,
			],
			'order gateway id'        => [
				$ach_gateway_id,
				'',
				(object) [
					'id'             => 'pi_mock',
					'object'         => 'payment_intent',
					'status'         => 'requires_confirmation',
					'payment_method' => 'pm_mock',
				],
				false,
			],
			'upe payment type'        => [
				WC_Stripe_UPE_Payment_Gateway::ID,
				WC_Stripe_Payment_Methods::ACH,
				(object) [
					'id'             => 'pi_mock',
					'object'         => 'payment_intent',
					'status'         => 'requires_action',
					'payment_method' => 'pm_mock',
				],
				false,
			],
			'requires payment method' => [
				$ach_gateway_id,
				WC_Stripe_Payment_Methods::ACH,
				(object) [
					'id'             => 'pi_mock',
					'object'         => 'payment_intent',
					'status'         => 'requires_payment_method',
					'payment_method' => (object) [ 'type' => 'us_bank_account' ],
				],
				true,
			],
			'canceled'                => [
				$ach_gateway_id,
				'',
				(object) [
					'id'          => 'pi_mock',
					'object'      => 'payment_intent',
					'status'      => 'canceled',
					'next_action' => (object) [ 'type' => 'verify_with_microdeposits' ],
				],
				true,
			],
			'card authentication'     => [
				WC_Stripe_UPE_Payment_Gateway::ID,
				'',
				(object) [
					'id'                   => 'pi_mock',
					'object'               => 'payment_intent',
					'status'               => 'requires_action',
					'payment_method_types' => [ 'card', 'link', 'us_bank_account' ],
					'payment_method'       => (object) [
						'id'   => 'pm_card',
						'type' => 'card',
					],
					'next_action'          => (object) [ 'type' => 'use_stripe_sdk' ],
				],
				true,
			],
		];
	}

	public function test_prevent_cancelling_orders_that_have_been_paid() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		// Mimic the race outcome: payment captured (date_paid set) but status stuck at pending.
		$order->set_date_paid( time() );
		$order->set_status( 'pending' );
		$order->save();

		// Read in a fresh order object.
		$order = wc_get_order( $order->get_id() );

		// A paid order must never be cancelled as unpaid, and we shouldn't need to fetch the intent.
		$this->order_handler->expects( $this->never() )->method( 'get_intent_from_order' );

		$this->assertFalse( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
	}

	/**
	 * Surfacing a blocked paid-order cancellation is idempotent: one note and one action fire even
	 * though wc_cancel_unpaid_orders() re-runs the filter on every scheduled pass.
	 */
	public function test_surfaces_prevented_paid_order_cancellation_only_once() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_date_paid( time() );
		$order->set_status( 'pending' );
		$order->save();

		$order = wc_get_order( $order->get_id() );

		$fired = 0;
		add_action(
			'wc_stripe_paid_order_cancellation_prevented',
			function () use ( &$fired ) {
				$fired++;
			}
		);

		// First scheduled pass: the work happens here — meta flag set, action fired, note added.
		$this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order );

		$this->assertSame( 1, $fired, 'Action should fire on the first pass.' );
		$this->assertSame( 'yes', wc_get_order( $order->get_id() )->get_meta( '_stripe_paid_order_cancellation_prevented' ) );
		$this->assertCount( 1, $this->get_prevented_cancellation_notes( $order->get_id() ), 'Order note should be added on the first pass.' );

		// Second scheduled pass over the same stuck order: the meta flag short-circuits all side effects.
		$this->order_handler->prevent_cancelling_orders_awaiting_action( true, wc_get_order( $order->get_id() ) );

		$this->assertSame( 1, $fired, 'Action should not fire again on the second pass.' );
		$this->assertSame( 'yes', wc_get_order( $order->get_id() )->get_meta( '_stripe_paid_order_cancellation_prevented' ) );
		$this->assertCount( 1, $this->get_prevented_cancellation_notes( $order->get_id() ), 'Order note should not be added again on the second pass.' );
	}

	/**
	 * Tests that cancel_payment leaves orders with a missing captured flag alone.
	 * The cancelled/refunded status hooks fire on transitions that must not touch the
	 * gateway (e.g. "Refund manually"); resolving a missing flag from Stripe here could
	 * turn a captured charge into an unrequested full refund.
	 */
	public function test_cancel_payment_skips_orders_with_missing_captured_flag() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_transaction_id( 'ch_123' );
		$order->save();

		$requested_urls = [];

		$callback = function ( $preempt, $request_args, $url ) use ( &$requested_urls ) {
			$requested_urls[] = $url;
			return $preempt;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		$status_before = $order->get_status();

		$this->order_handler->cancel_payment( $order->get_id() );

		remove_filter( 'pre_http_request', $callback );

		// No captured-state lookup, no refund: Stripe must not be contacted at all.
		$this->assertSame( [], $requested_urls );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_stripe_charge_captured' ) );

		// And the order itself is untouched: no refund records, no status change, nothing refunded.
		$order = wc_get_order( $order->get_id() );
		$this->assertCount( 0, $order->get_refunds() );
		$this->assertSame( 0.0, (float) $order->get_total_refunded() );
		$this->assertSame( $status_before, $order->get_status() );
	}

	/**
	 * Tests that cancel_payment still voids a charge explicitly recorded as uncaptured.
	 *
	 * @dataProvider provide_test_cancel_payment_voids_explicit_preauth
	 */
	public function test_cancel_payment_voids_explicit_preauth( $captured_meta, $expects_void ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', $captured_meta );
		$order->save();

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'pi_123',
					'object' => 'payment_intent',
					'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
				]
			);

		$cancel_requested = false;

		$callback = function ( $preempt, $request_args, $url ) use ( &$cancel_requested ) {
			if ( strpos( $url, 'payment_intents/pi_123/cancel' ) !== false ) {
				$cancel_requested = true;

				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'            => 'pi_123',
							'object'        => 'payment_intent',
							'status'        => 'canceled',
							'latest_charge' => 'ch_123',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
			if ( strpos( $url, 'charges/ch_123' ) !== false ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'       => 'ch_123',
							'object'   => 'charge',
							'captured' => false,
							'refunds'  => [
								'data' => [
									[
										'id'     => 're_123',
										'object' => 'refund',
										'amount' => 5000,
										'status' => 'succeeded',
									],
								],
							],
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		$this->order_handler->cancel_payment( $order->get_id() );

		remove_filter( 'pre_http_request', $callback );

		$this->assertSame( $expects_void, $cancel_requested );
	}

	/**
	 * Provider for `test_cancel_payment_voids_explicit_preauth`.
	 *
	 * @return array[]
	 */
	public function provide_test_cancel_payment_voids_explicit_preauth(): array {
		return [
			'explicit no voids the intent'   => [ 'no', true ],
			'captured charge is not touched' => [ 'yes', false ],
		];
	}

	/**
	 * An order whose PaymentIntent Stripe reports as paid must be settled, not cancelled as unpaid.
	 *
	 * @param string $intent_status PaymentIntent status reported by Stripe.
	 * @dataProvider provide_paid_intent_statuses
	 */
	public function test_settles_order_with_paid_intent_instead_of_cancelling( $intent_status ) {
		$order = WC_Helper_Order::create_order();
		// ACH: an async method whose delayed status updates are the typical way
		// a paid order is still pending when the auto-cancel runs.
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID );
		$order->set_status( 'pending' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$charge = (object) [
			'id'       => 'ch_mock',
			'captured' => true,
			'status'   => 'succeeded',
		];
		$intent = (object) [
			'id'     => 'pi_mock',
			'object' => 'payment_intent',
			'status' => $intent_status,
		];

		$order_handler = $this->createPartialMock(
			WC_Stripe_Order_Handler::class,
			[ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ]
		);
		$order_handler->method( 'get_intent_from_order' )->willReturn( $intent );
		$order_handler->method( 'get_latest_charge_from_intent' )->with( $intent )->willReturn( $charge );
		$order_handler->expects( $this->once() )
			->method( 'process_response' )
			->with( $charge, $this->callback( fn( $passed ) => $passed->get_id() === $order->get_id() ) );

		$this->assertFalse( $order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );

		$notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 5,
			]
		);
		$this->assertNotEmpty(
			array_filter(
				$notes,
				function ( $note ) {
					return false !== strpos( $note->content, 'instead of being auto-cancelled' );
				}
			),
			'A note should explain why the cancellation was blocked.'
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_paid_intent_statuses() {
		return [
			'captured payment'         => [ WC_Stripe_Intent_Status::SUCCEEDED ],
			'uncaptured authorization' => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE ],
			'async payment processing' => [ WC_Stripe_Intent_Status::PROCESSING ],
		];
	}

	/**
	 * When another process holds the payment lock, block the cancellation but settle nothing here.
	 */
	public function test_blocks_cancellation_without_settling_when_payment_locked() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( 'pending' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$intent = (object) [
			'id'     => 'pi_mock_locked',
			'object' => 'payment_intent',
			'status' => WC_Stripe_Intent_Status::SUCCEEDED,
		];

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);
		$order_helper->method( 'lock_order_payment' )->willReturn( true );
		WC_Stripe_Order_Helper::set_instance( $order_helper );

		$order_handler = $this->createPartialMock(
			WC_Stripe_Order_Handler::class,
			[ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ]
		);
		$order_handler->method( 'get_intent_from_order' )->willReturn( $intent );
		$order_handler->expects( $this->never() )->method( 'process_response' );

		try {
			$this->assertFalse( $order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
		} finally {
			WC_Stripe_Order_Helper::set_instance( null );
		}
	}

	/**
	 * An unpaid intent must not block the existing cancellation flow.
	 */
	public function test_cancels_order_whose_intent_is_not_paid() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( 'pending' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$intent = (object) [
			'id'     => 'pi_mock_unpaid',
			'object' => 'payment_intent',
			'status' => WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD,
		];

		$order_handler = $this->createPartialMock(
			WC_Stripe_Order_Handler::class,
			[ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ]
		);
		$order_handler->method( 'get_intent_from_order' )->willReturn( $intent );
		$order_handler->expects( $this->never() )->method( 'process_response' );

		$this->assertTrue( $order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
	}

	/**
	 * A Checkout Session order has no intent stored until checkout.session.completed is processed,
	 * so a late webhook must not let the hold-stock cron cancel an order whose session can still be,
	 * or already was, paid.
	 *
	 * @dataProvider provide_checkout_session_states_for_cancellation
	 *
	 * @param array|null $retrieve_response Stripe's answer to the session lookup, null for a network failure.
	 * @param array|null $expire_response   Stripe's answer to expiring the session, null when no expiry is expected.
	 * @param bool       $expected          Whether the order may be cancelled.
	 */
	public function test_checkout_session_order_without_intent_cancellation( ?array $retrieve_response, ?array $expire_response, bool $expected ) {
		$order = $this->create_checkout_session_order();

		$session_url = WC_Stripe_API::ENDPOINT . 'checkout/sessions/' . self::CHECKOUT_SESSION_ID;
		$retrieve    = 'GET ' . $session_url . '?expand[]=payment_intent';
		$expire      = 'POST ' . $session_url . '/expire';
		$requests    = $this->mock_stripe_requests(
			[
				$retrieve => $retrieve_response,
				$expire   => $expire_response,
			]
		);

		try {
			$this->assertSame( $expected, $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
		} finally {
			$this->restore_stripe_requests();
		}

		$this->assertSame( null !== $expire_response ? [ $retrieve, $expire ] : [ $retrieve ], $requests->getArrayCopy() );
	}

	/**
	 * @return array<string, array{0: array|null, 1: array|null, 2: bool}>
	 */
	public function provide_checkout_session_states_for_cancellation(): array {
		$session = function ( string $status, ?string $intent_status = null, string $payment_status = 'unpaid' ): array {
			return $this->make_stripe_response(
				200,
				[
					'id'             => self::CHECKOUT_SESSION_ID,
					'object'         => 'checkout.session',
					'status'         => $status,
					'payment_status' => $payment_status,
					'payment_intent' => null === $intent_status ? null : [
						'id'     => 'pi_test_cancel_unpaid',
						'object' => 'payment_intent',
						'status' => $intent_status,
					],
				]
			);
		};
		$error   = function ( int $code, string $type, string $error_code = '' ): array {
			return $this->make_stripe_response(
				$code,
				[
					'error' => [
						'type'    => $type,
						'code'    => $error_code,
						'message' => 'Mocked Stripe error.',
					],
				]
			);
		};

		return [
			'complete and paid'                      => [ $session( 'complete', WC_Stripe_Intent_Status::SUCCEEDED, 'paid' ), null, false ],
			'complete, bank debit still settling'    => [ $session( 'complete', WC_Stripe_Intent_Status::PROCESSING ), null, false ],
			'complete, voucher not paid yet'         => [ $session( 'complete', WC_Stripe_Intent_Status::REQUIRES_ACTION ), null, false ],
			'complete, intent missing'               => [ $session( 'complete' ), null, false ],
			'complete, async payment failed'         => [ $session( 'complete', WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD ), null, true ],
			'complete, intent cancelled'             => [ $session( 'complete', WC_Stripe_Intent_Status::CANCELED ), null, true ],
			'expired without payment'                => [ $session( 'expired' ), null, true ],
			'open, expired before cancelling'        => [ $session( 'open' ), $session( 'expired' ), true ],
			'open, completed before it could expire' => [ $session( 'open' ), $error( 400, 'invalid_request_error', 'checkout_session_not_open' ), false ],
			'open, expiry not confirmed'             => [ $session( 'open' ), $session( 'complete' ), false ],
			'unknown to Stripe'                      => [ $error( 404, 'invalid_request_error', 'resource_missing' ), null, true ],
			'API key rejected'                       => [ $error( 401, 'invalid_request_error', 'api_key_expired' ), null, true ],
			'network failure'                        => [ null, null, false ],
			'rate limited'                           => [ $error( 429, 'invalid_request_error', 'rate_limit' ), null, false ],
			'lock timeout'                           => [ $error( 429, 'invalid_request_error', 'lock_timeout' ), null, false ],
			'Stripe API error'                       => [ $error( 400, 'api_error' ), null, false ],
			'Stripe server error'                    => [ $error( 500, 'api_error' ), null, false ],
		];
	}

	/**
	 * When a stored intent can't be fetched, the session must not decide: an order that has an intent was
	 * settled from it, so keep the previous behavior instead of expiring or reading the session.
	 */
	public function test_order_with_unfetchable_intent_ignores_checkout_session() {
		$order = $this->create_checkout_session_order();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, 'pi_test_unfetchable' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$requests = $this->mock_stripe_requests( [] );

		try {
			$this->assertTrue( $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
		} finally {
			$this->restore_stripe_requests();
		}

		$this->assertSame( [], $requests->getArrayCopy() );
	}

	/**
	 * Creates a pending Stripe order linked only to a Checkout Session, as the order is before its webhook arrives.
	 *
	 * @return WC_Order
	 */
	private function create_checkout_session_order(): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( 'pending' );
		$order->save();

		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, self::CHECKOUT_SESSION_ID );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Serves mocked Stripe responses by "METHOD URL". Unlisted requests, and listed ones without a
	 * response, fail like a network error, so no request can reach Stripe.
	 *
	 * @param array<string, array|null> $responses Responses keyed by "METHOD URL".
	 * @return ArrayObject The requests made, as "METHOD URL", filled in as they happen.
	 */
	private function mock_stripe_requests( array $responses ): ArrayObject {
		$requests = new ArrayObject();

		$this->stripe_request_mock = function ( $preempt, $request_args, $url ) use ( $responses, $requests ) {
			$request    = ( $request_args['method'] ?? '' ) . ' ' . $url;
			$requests[] = $request;

			return $responses[ $request ] ?? new WP_Error( 'http_request_failed', $request );
		};
		add_filter( 'pre_http_request', $this->stripe_request_mock, 10, 3 );

		return $requests;
	}

	/**
	 * Removes the mock added by mock_stripe_requests().
	 */
	private function restore_stripe_requests(): void {
		remove_filter( 'pre_http_request', $this->stripe_request_mock );
		// A simulated network failure records an API outage, which would leak into later tests.
		WC_Stripe_API_Outage_Status::record_success();
	}

	/**
	 * Builds a mocked Stripe HTTP response for the `pre_http_request` filter.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $body Decoded response body.
	 * @return array
	 */
	private function make_stripe_response( int $code, array $body ): array {
		return [
			'response' => [
				'code'    => $code,
				'message' => '',
			],
			'body'     => wp_json_encode( $body ),
		];
	}

	/**
	 * Returns the order notes that announce a prevented paid-order cancellation.
	 *
	 * @param int $order_id The order to read notes from.
	 * @return array The matching notes.
	 */
	private function get_prevented_cancellation_notes( $order_id ) {
		$notes = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 5,
			]
		);

		return array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->content, 'already been paid' );
			}
		);
	}
}
