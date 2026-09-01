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
	 * The guard is specific to SetupIntents: a PaymentIntent in the same status keeps the
	 * pre-existing one-day cancellation behaviour.
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
	 * Manual capture must use the payment method stored on the order while disregarding filters
	 * that can be applied to the displayed method.
	 *
	 * @param string $stored_method   Payment method persisted on the order.
	 * @param string $filtered_method Payment method the view-context filter reports.
	 * @param bool   $expects_capture Whether the pre-auth should be captured at Stripe.
	 *
	 * @dataProvider provide_capture_payment_filtered_payment_methods
	 */
	public function test_capture_payment_uses_stored_payment_method( string $stored_method, string $filtered_method, bool $expects_capture ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $stored_method );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
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

		$payment_method_filter = function () use ( $filtered_method ) {
			return $filtered_method;
		};
		add_filter( 'woocommerce_order_get_payment_method', $payment_method_filter );

		$capture_requested = false;
		$http_callback     = function ( $preempt, $request_args, $url ) use ( &$capture_requested ) {
			if ( 'https://api.stripe.com/v1/payment_intents/pi_123/capture' !== $url ) {
				return $preempt;
			}

			$capture_requested = true;

			return $this->build_stripe_response(
				[
					'id'      => 'pi_123',
					'object'  => 'payment_intent',
					'status'  => WC_Stripe_Intent_Status::SUCCEEDED,
					'charges' => [
						'data' => [
							[
								'id'       => 'ch_123',
								'object'   => 'charge',
								'captured' => true,
							],
						],
					],
				]
			);
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$this->order_handler->capture_payment( $order->get_id() );

		remove_filter( 'pre_http_request', $http_callback );
		remove_filter( 'woocommerce_order_get_payment_method', $payment_method_filter );

		$this->assertSame( $expects_capture, $capture_requested );
		$this->assertSame(
			$expects_capture ? 'yes' : 'no',
			wc_get_order( $order->get_id() )->get_meta( '_stripe_charge_captured' )
		);
	}

	/**
	 * Provider for `test_capture_payment_uses_stored_payment_method`.
	 *
	 * @return array[]
	 */
	public function provide_capture_payment_filtered_payment_methods(): array {
		return [
			'stripe order masked as another gateway' => [ 'stripe', 'cheque', true ],
			'other gateway masked as stripe'         => [ 'cheque', 'stripe', false ],
			'unfiltered stripe order'                => [ 'stripe', 'stripe', true ],
		];
	}

	/**
	 * An intent Stripe has already captured (`succeeded`) must fetch the charge from the intent.
	 *
	 * @param array $intent Intent returned for the order, including both `charges` and `latest_charge` fields.
	 *
	 * @dataProvider provide_succeeded_intent_shapes
	 */
	public function test_capture_payment_records_charge_from_succeeded_intent( array $intent ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn( json_decode( wp_json_encode( $intent ) ) );

		$http_callback = function ( $preempt, $request_args, $url ) {
			// Only reached for the `latest_charge` intent shape, which carries the charge by ID.
			if ( 'https://api.stripe.com/v1/charges/ch_123' === $url ) {
				return $this->build_stripe_response(
					[
						'id'                  => 'ch_123',
						'object'              => 'charge',
						'captured'            => true,
						'balance_transaction' => 'txn_123',
					]
				);
			}

			if ( 'https://api.stripe.com/v1/balance/history/txn_123' === $url ) {
				return $this->build_stripe_response(
					[
						'id'       => 'txn_123',
						'object'   => 'balance_transaction',
						'amount'   => 5000,
						'fee'      => 250,
						'net'      => 4750,
						'currency' => 'usd',
					]
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$captured_result = null;
		$capture_action  = function ( $hooked_order, $result ) use ( &$captured_result ) {
			$captured_result = $result;
		};
		add_action( 'woocommerce_stripe_process_manual_capture', $capture_action, 10, 2 );

		$result = $this->order_handler->capture_payment( $order->get_id() );

		remove_action( 'woocommerce_stripe_process_manual_capture', $capture_action, 10 );
		remove_filter( 'pre_http_request', $http_callback );

		$this->assertSame( 'ch_123', $result->id ?? null, 'The charge from the intent should be returned.' );
		$this->assertSame( 'ch_123', $captured_result->id ?? null, 'The manual capture hook should receive the charge.' );

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( 'ch_123', $order->get_transaction_id(), 'The transaction ID must survive the capture.' );
		$this->assertSame( 'yes', $order->get_meta( '_stripe_charge_captured' ) );
		$this->assertStringContainsString(
			'Stripe charge complete (Charge ID: ch_123)',
			implode( "\n", wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) )
		);

		// Fees are only reachable through the returned charge's balance transaction.
		$this->assertSame( 2.5, (float) $order->get_meta( '_stripe_fee' ) );
		$this->assertSame( 47.5, (float) $order->get_meta( '_stripe_net' ) );
	}

	/**
	 * Provider for `test_capture_payment_records_charge_from_succeeded_intent`.
	 *
	 * Stripe replaced the intent's `charges` collection with `latest_charge` in API version
	 * 2022-11-15, but we include both to ensure we support both versions.
	 *
	 * @return array[]
	 */
	public function provide_succeeded_intent_shapes(): array {
		$intent = [
			'id'     => 'pi_123',
			'object' => 'payment_intent',
			'status' => WC_Stripe_Intent_Status::SUCCEEDED,
		];

		return [
			'expanded charges collection' => [
				array_merge(
					$intent,
					[
						'charges' => [
							'data' => [
								[
									'id'                  => 'ch_123',
									'object'              => 'charge',
									'captured'            => true,
									'balance_transaction' => 'txn_123',
								],
							],
						],
					]
				),
			],
			'latest_charge reference'     => [ array_merge( $intent, [ 'latest_charge' => 'ch_123' ] ) ],
		];
	}

	/**
	 * Builds a `pre_http_request` return value carrying a Stripe API payload.
	 *
	 * @param array $body The response body to encode.
	 * @return array
	 */
	private function build_stripe_response( array $body ): array {
		return [
			'headers'  => [],
			'body'     => wp_json_encode( $body ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
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
