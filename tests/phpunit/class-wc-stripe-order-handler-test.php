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
	 * Voiding a pre-auth must use the payment method stored on the order while disregarding
	 * filters that can be applied to the displayed method.
	 *
	 * @param string      $stored_method   Payment method persisted on the order.
	 * @param string|null $filtered_method Payment method the view-context filter reports. Null means no filter is applied.
	 * @param bool        $expects_void    Whether the pre-auth should be voided at Stripe.
	 *
	 * @dataProvider provide_filtered_payment_methods
	 */
	public function test_cancel_payment_uses_stored_payment_method( string $stored_method, ?string $filtered_method, bool $expects_void ): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $stored_method );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$this->order_handler
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'pi_123',
					'object' => 'payment_intent',
					'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
				]
			);

		$payment_method_filter = null;
		if ( null !== $filtered_method ) {
			$payment_method_filter = function () use ( $filtered_method ) {
				return $filtered_method;
			};
			add_filter( 'woocommerce_order_get_payment_method', $payment_method_filter );
		}

		$cancel_requested = false;
		$http_callback    = function ( $preempt, $request_args, $url ) use ( &$cancel_requested ) {
			if ( 'https://api.stripe.com/v1/payment_intents/pi_123/cancel' !== $url ) {
				return $preempt;
			}

			$cancel_requested = true;

			return $this->make_stripe_response(
				200,
				[
					'id'            => 'pi_123',
					'object'        => 'payment_intent',
					'status'        => 'canceled',
					'latest_charge' => 'ch_123',
				]
			);
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$manual_cancel_count = did_action( 'woocommerce_stripe_process_manual_cancel' );

		try {
			$this->order_handler->cancel_payment( $order->get_id() );
		} finally {
			remove_filter( 'pre_http_request', $http_callback );
			if ( null !== $payment_method_filter ) {
				remove_filter( 'woocommerce_order_get_payment_method', $payment_method_filter );
			}
		}

		$this->assertSame( $expects_void, $cancel_requested );
		$expected_manual_cancel_count = $manual_cancel_count + ( $expects_void ? 1 : 0 );
		$this->assertSame( $expected_manual_cancel_count, did_action( 'woocommerce_stripe_process_manual_cancel' ) );
	}

	/**
	 * The uncaptured-order warning must use the payment method stored on the order while
	 * disregarding filters that can be applied to the displayed method.
	 *
	 * @param string      $stored_method   Payment method persisted on the order.
	 * @param string|null $filtered_method Payment method the view-context filter reports. Null means no filter is applied.
	 * @param bool        $expects_warning Whether the warning should be shown.
	 *
	 * @dataProvider provide_filtered_payment_methods
	 */
	public function test_show_warning_for_uncaptured_orders_uses_stored_payment_method( string $stored_method, ?string $filtered_method, bool $expects_warning ): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $stored_method );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$this->order_handler
			->method( 'get_intent_from_order' )
			->willReturn(
				(object) [
					'id'     => 'pi_123',
					'object' => 'payment_intent',
					'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
				]
			);

		$gateway                      = WC_Stripe::get_instance()->get_main_stripe_gateway();
		$original_settings            = $gateway->settings;
		$gateway->settings['capture'] = 'no';

		$payment_method_filter = null;
		if ( null !== $filtered_method ) {
			$payment_method_filter = function () use ( $filtered_method ) {
				return $filtered_method;
			};
			add_filter( 'woocommerce_order_get_payment_method', $payment_method_filter );
		}

		$output = null;
		try {
			ob_start();
			$this->order_handler->show_warning_for_uncaptured_orders( $order->get_id() );
			$output = ob_get_clean();
		} finally {
			if ( null !== $payment_method_filter ) {
				remove_filter( 'woocommerce_order_get_payment_method', $payment_method_filter );
			}
			$gateway->settings = $original_settings;
		}

		$this->assertNotNull( $output, 'Successfully captured output from show_warning_for_uncaptured_orders.' );
		if ( $expects_warning ) {
			$this->assertStringContainsString( 'Attempting to capture more than the authorized amount will fail with an error.', $output );
		} else {
			$this->assertSame( '', $output );
		}
	}

	/**
	 * Provider for tests checking that filters on the order's payment method are ignored.
	 *
	 * @return array[]
	 */
	public function provide_filtered_payment_methods(): array {
		return [
			'stripe order masked as another gateway' => [ 'stripe', 'cheque', true ],
			'other gateway masked as stripe'         => [ 'cheque', 'stripe', false ],
			'other gateway no filter'                => [ 'cheque', null, false ],
			'filter returns stripe'                  => [ 'stripe', 'stripe', true ],
			'value is stripe and not filtered'       => [ 'stripe', null, true ],
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
			'amount'               => WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() ),
			'currency'             => strtolower( $order->get_currency() ),
			'payment_method_types' => [ 'us_bank_account' ],
			'id'                   => 'pi_mock',
			'object'               => 'payment_intent',
			'status'               => $intent_status,
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

			return $this->make_stripe_response(
				200,
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
				return $this->make_stripe_response(
					200,
					[
						'id'                  => 'ch_123',
						'object'              => 'charge',
						'captured'            => true,
						'balance_transaction' => 'txn_123',
					]
				);
			}

			if ( 'https://api.stripe.com/v1/balance/history/txn_123' === $url ) {
				return $this->make_stripe_response(
					200,
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
	 * An intent that doesn't have a charge must not be recorded as captured.
	 *
	 * @param string $status        Status of the intent returned for the order.
	 * @param array  $charge_fields Charge-related fields on the intent or on the capture response.
	 * @param array  $expected_urls Stripe URLs expected to be requested.
	 *
	 * @dataProvider provide_intents_without_a_charge
	 */
	public function test_capture_payment_does_not_record_capture_when_intent_has_no_charge( string $status, array $charge_fields, array $expected_urls ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$intent = [
			'id'     => 'pi_123',
			'object' => 'payment_intent',
			'status' => $status,
		];

		if ( WC_Stripe_Intent_Status::SUCCEEDED === $status ) {
			$intent = array_merge( $intent, $charge_fields );
		}

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn( json_decode( wp_json_encode( $intent ) ) );

		$requested_urls = [];
		$http_callback  = function ( $preempt, $request_args, $url ) use ( &$requested_urls, $charge_fields ) {
			$requested_urls[] = $url;

			if ( 'https://api.stripe.com/v1/payment_intents/pi_123/capture' === $url ) {
				return $this->make_stripe_response(
					200,
					array_merge(
						[
							'id'     => 'pi_123',
							'object' => 'payment_intent',
							'status' => WC_Stripe_Intent_Status::SUCCEEDED,
						],
						$charge_fields
					)
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$hook_fired      = false;
		$captured_result = 'not-set';
		$capture_action  = function ( $hooked_order, $result ) use ( &$hook_fired, &$captured_result ) {
			$hook_fired      = true;
			$captured_result = $result;
		};
		add_action( 'woocommerce_stripe_process_manual_capture', $capture_action, 10, 2 );

		$result = $this->order_handler->capture_payment( $order->get_id() );

		remove_action( 'woocommerce_stripe_process_manual_capture', $capture_action, 10 );
		remove_filter( 'pre_http_request', $http_callback );

		$this->assertSame( $expected_urls, $requested_urls );

		$this->assertNull( $result, 'No charge could be resolved from the intent.' );
		$this->assertTrue( $hook_fired, 'The manual capture hook should still fire.' );
		$this->assertInstanceOf( stdClass::class, $captured_result );
		$this->assertEquals( '{}', wp_json_encode( $captured_result ), 'The manual capture hook should receive an empty charge result.' );

		$order = wc_get_order( $order->get_id() );
		$notes = implode( "\n", wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) );

		$this->assertSame( 'ch_123', $order->get_transaction_id(), 'The transaction ID must survive the capture attempt.' );
		$this->assertSame( 'no', $order->get_meta( '_stripe_charge_captured' ), 'An unverified capture must not be recorded.' );
		$this->assertStringContainsString( 'Unable to verify whether charge has been captured.', $notes );
		$this->assertStringNotContainsString( 'Stripe charge complete', $notes );

		// Confirm that no fees are recorded when there is no charge.
		$this->assertSame( '', $order->get_meta( '_stripe_fee' ) );
		$this->assertSame( '', $order->get_meta( '_stripe_net' ) );
	}

	/**
	 * Data provider for {@see test_capture_payment_does_not_record_capture_when_intent_has_no_charge()}.
	 *
	 * @return array[]
	 */
	public function provide_intents_without_a_charge(): array {
		$capture_url = [ 'https://api.stripe.com/v1/payment_intents/pi_123/capture' ];
		$shapes      = [
			'no charge fields at all'  => [],
			'null latest_charge'       => [ 'latest_charge' => null ],
			'empty charges collection' => [ 'charges' => [ 'data' => [] ] ],
		];

		$cases = [];
		foreach ( $shapes as $label => $charge_fields ) {
			// The intent is already captured at Stripe, so nothing is requested to reach that conclusion.
			$cases[ "succeeded intent, {$label}" ] = [ WC_Stripe_Intent_Status::SUCCEEDED, $charge_fields, [] ];
			$cases[ "capture response, {$label}" ] = [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, $charge_fields, $capture_url ];
		}

		return $cases;
	}

	/**
	 * A charge with an error must not be recorded as captured.
	 *
	 * @param string $status        Status of the intent returned for the order.
	 * @param object $error         The error carried on the resolved charge.
	 * @param string $expected_note The order note expected to explain the failure.
	 * @param array  $expected_urls Stripe URLs expected to be requested.
	 *
	 * @dataProvider provide_charge_errors
	 */
	public function test_capture_payment_does_not_record_capture_when_charge_has_error( string $status, $error, string $expected_note, array $expected_urls ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$charges = [
			'charges' => [
				'data' => [
					[
						'id'     => 'ch_123',
						'object' => 'charge',
						'error'  => $error,
					],
				],
			],
		];

		$intent = [
			'id'     => 'pi_123',
			'object' => 'payment_intent',
			'status' => $status,
		];

		if ( WC_Stripe_Intent_Status::SUCCEEDED === $status ) {
			$intent = array_merge( $intent, $charges );
		}

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn( json_decode( wp_json_encode( $intent ) ) );

		$requested_urls = [];
		$http_callback  = function ( $preempt, $request_args, $url ) use ( &$requested_urls, $charges ) {
			$requested_urls[] = $url;

			if ( 'https://api.stripe.com/v1/payment_intents/pi_123/capture' === $url ) {
				return $this->make_stripe_response(
					200,
					array_merge(
						[
							'id'     => 'pi_123',
							'object' => 'payment_intent',
							'status' => WC_Stripe_Intent_Status::SUCCEEDED,
						],
						$charges
					)
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$hook_fired     = false;
		$capture_action = function () use ( &$hook_fired ) {
			$hook_fired = true;
		};
		add_action( 'woocommerce_stripe_process_manual_capture', $capture_action );

		$result = $this->order_handler->capture_payment( $order->get_id() );

		remove_action( 'woocommerce_stripe_process_manual_capture', $capture_action );
		remove_filter( 'pre_http_request', $http_callback );

		$this->assertSame( $expected_urls, $requested_urls );
		$this->assertSame( 'ch_123', $result->id ?? null, 'The errored charge should be returned as-is.' );
		$this->assertTrue( $hook_fired, 'The manual capture hook should still fire.' );

		$order = wc_get_order( $order->get_id() );
		$notes = wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' );

		$this->assertSame( 'ch_123', $order->get_transaction_id(), 'The transaction ID must survive the capture attempt.' );
		$this->assertSame( 'no', $order->get_meta( '_stripe_charge_captured' ), 'An errored capture must not be recorded.' );
		$this->assertContains( $expected_note, $notes );
		$this->assertStringNotContainsString( 'Stripe charge complete', implode( "\n", $notes ) );
		$this->assertSame( '', $order->get_meta( '_stripe_fee' ) );
	}

	/**
	 * Data provider for {@see test_capture_payment_does_not_record_capture_when_charge_has_error()}.
	 *
	 * Only a charge embedded in the intent's `charges` collection can reach the error check: a
	 * `latest_charge` reference is fetched via get_charge_object(), which throws on an API error.
	 *
	 * @return array[]
	 */
	public function provide_charge_errors(): array {
		$capture_url    = [ 'https://api.stripe.com/v1/payment_intents/pi_123/capture' ];
		$string_error   = [ 'message' => 'Charge lookup failed.' ];
		$no_message     = [ 'type' => 'api_error' ];
		$string_note    = 'Unable to verify whether charge has been captured: Charge lookup failed.';
		$no_detail_note = 'Unable to verify whether charge has been captured.';

		return [
			'succeeded intent, string message'     => [ WC_Stripe_Intent_Status::SUCCEEDED, $string_error, $string_note, [] ],
			'succeeded intent, no message'         => [ WC_Stripe_Intent_Status::SUCCEEDED, $no_message, $no_detail_note, [] ],
			'succeeded intent, non-string message' => [ WC_Stripe_Intent_Status::SUCCEEDED, [ 'message' => [ 'nested' ] ], $no_detail_note, [] ],
			'capture response, string message'     => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, $string_error, $string_note, $capture_url ],
			'capture response, no message'         => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, $no_message, $no_detail_note, $capture_url ],
			'capture response, non-string message' => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, [ 'message' => [ 'nested' ] ], $no_detail_note, $capture_url ],
		];
	}

	/**
	 * A resolved charge that isn't a usable charge object must not be recorded as captured,
	 * since recording it would overwrite the transaction ID with a missing or wrong value.
	 *
	 * @param string $status        Status of the intent returned for the order.
	 * @param array  $charge        The malformed charge embedded in the intent's `charges` collection.
	 * @param array  $expected_urls Stripe URLs expected to be requested.
	 *
	 * @dataProvider provide_malformed_charges
	 */
	public function test_capture_payment_does_not_record_capture_when_charge_is_malformed( string $status, array $charge, array $expected_urls ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$charges = [ 'charges' => [ 'data' => [ $charge ] ] ];

		$intent = [
			'id'     => 'pi_123',
			'object' => 'payment_intent',
			'status' => $status,
		];

		if ( WC_Stripe_Intent_Status::SUCCEEDED === $status ) {
			$intent = array_merge( $intent, $charges );
		}

		$this->order_handler
			->method( 'get_intent_from_order' )
			->willReturn( json_decode( wp_json_encode( $intent ) ) );

		$requested_urls = [];
		$http_callback  = function ( $preempt, $request_args, $url ) use ( &$requested_urls, $charges ) {
			$requested_urls[] = $url;

			if ( 'https://api.stripe.com/v1/payment_intents/pi_123/capture' !== $url ) {
				return $preempt;
			}

			return $this->make_stripe_response(
				200,
				array_merge(
					[
						'id'     => 'pi_123',
						'object' => 'payment_intent',
						'status' => WC_Stripe_Intent_Status::SUCCEEDED,
					],
					$charges
				)
			);
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$hook_fired     = false;
		$capture_action = function () use ( &$hook_fired ) {
			$hook_fired = true;
		};
		add_action( 'woocommerce_stripe_process_manual_capture', $capture_action );

		try {
			$this->order_handler->capture_payment( $order->get_id() );
		} finally {
			remove_action( 'woocommerce_stripe_process_manual_capture', $capture_action );
			remove_filter( 'pre_http_request', $http_callback );
		}

		$this->assertSame( $expected_urls, $requested_urls );
		$this->assertTrue( $hook_fired, 'The manual capture hook should still fire.' );

		$order = wc_get_order( $order->get_id() );
		$notes = wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' );

		$this->assertSame( 'ch_123', $order->get_transaction_id(), 'The transaction ID must survive the capture attempt.' );
		$this->assertSame( 'no', $order->get_meta( '_stripe_charge_captured' ), 'A malformed charge must not be recorded as captured.' );
		$this->assertContains( 'Unable to verify whether charge has been captured.', $notes );
		$this->assertStringNotContainsString( 'Stripe charge complete', implode( "\n", $notes ) );
		$this->assertSame( '', $order->get_meta( '_stripe_fee' ) );
	}

	/**
	 * Data provider for {@see test_capture_payment_does_not_record_capture_when_charge_is_malformed()}.
	 *
	 * @return array[]
	 */
	public function provide_malformed_charges(): array {
		$capture_url = [ 'https://api.stripe.com/v1/payment_intents/pi_123/capture' ];
		$charges     = [
			'charge without id'     => [ 'object' => 'charge' ],
			'charge with empty id'  => [
				'id'     => '',
				'object' => 'charge',
			],
			'non-charge object'     => [
				'id'     => 'pi_456',
				'object' => 'payment_intent',
			],
			'charge without object' => [ 'id' => 'ch_456' ],
		];

		$cases = [];
		foreach ( $charges as $label => $charge ) {
			$cases[ "succeeded intent, {$label}" ] = [ WC_Stripe_Intent_Status::SUCCEEDED, $charge, [] ];
			$cases[ "capture response, {$label}" ] = [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, $charge, $capture_url ];
		}

		return $cases;
	}

	/**
	 * A failed `latest_charge` lookup throws, and the capture must not crash or be recorded.
	 *
	 * @param string $status        Status of the intent returned for the order.
	 * @param array  $expected_urls Stripe URLs expected to be requested.
	 *
	 * @dataProvider provide_intent_statuses_for_failed_charge_lookup
	 */
	public function test_capture_payment_does_not_record_capture_when_charge_lookup_fails( string $status, array $expected_urls ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'stripe' );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'no' );
		$order->save();

		$intent = [
			'id'     => 'pi_123',
			'object' => 'payment_intent',
			'status' => $status,
		];

		if ( WC_Stripe_Intent_Status::SUCCEEDED === $status ) {
			$intent['latest_charge'] = 'ch_123';
		}

		$this->order_handler
			->expects( $this->any() )
			->method( 'get_intent_from_order' )
			->willReturn( json_decode( wp_json_encode( $intent ) ) );

		$requested_urls = [];
		$http_callback  = function ( $preempt, $request_args, $url ) use ( &$requested_urls ) {
			$requested_urls[] = $url;

			if ( 'https://api.stripe.com/v1/payment_intents/pi_123/capture' === $url ) {
				return $this->make_stripe_response(
					200,
					[
						'id'            => 'pi_123',
						'object'        => 'payment_intent',
						'status'        => WC_Stripe_Intent_Status::SUCCEEDED,
						'latest_charge' => 'ch_123',
					]
				);
			}

			if ( 'https://api.stripe.com/v1/charges/ch_123' === $url ) {
				return $this->make_stripe_response(
					404,
					[
						'error' => [
							'type'    => 'invalid_request_error',
							'message' => 'No such charge: ch_123',
						],
					]
				);
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $http_callback, 10, 3 );

		$hook_fired      = false;
		$captured_result = 'not-set';
		$capture_action  = function ( $hooked_order, $result ) use ( &$hook_fired, &$captured_result ) {
			$hook_fired      = true;
			$captured_result = $result;
		};
		add_action( 'woocommerce_stripe_process_manual_capture', $capture_action, 10, 2 );

		try {
			$result = $this->order_handler->capture_payment( $order->get_id() );
		} finally {
			remove_action( 'woocommerce_stripe_process_manual_capture', $capture_action, 10 );
			remove_filter( 'pre_http_request', $http_callback );
		}

		$this->assertSame( $expected_urls, $requested_urls );

		$this->assertNull( $result, 'A failed charge lookup should not return a charge.' );
		$this->assertTrue( $hook_fired, 'The manual capture hook should still fire.' );
		$this->assertInstanceOf( stdClass::class, $captured_result );
		$this->assertEquals( '{}', wp_json_encode( $captured_result ), 'The manual capture hook should receive an empty charge result.' );

		$order = wc_get_order( $order->get_id() );
		$notes = implode( "\n", wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) );

		$this->assertSame( 'ch_123', $order->get_transaction_id(), 'The transaction ID must survive the capture attempt.' );
		$this->assertSame( 'no', $order->get_meta( '_stripe_charge_captured' ), 'An unverified capture must not be recorded.' );
		$this->assertStringContainsString( 'Unable to verify whether charge has been captured.', $notes );
		$this->assertStringNotContainsString( 'Stripe charge complete', $notes );
		$this->assertSame( '', $order->get_meta( '_stripe_fee' ) );
	}

	/**
	 * Data provider for {@see test_capture_payment_does_not_record_capture_when_charge_lookup_fails()}.
	 *
	 * @return array[]
	 */
	public function provide_intent_statuses_for_failed_charge_lookup(): array {
		$charge_url  = 'https://api.stripe.com/v1/charges/ch_123';
		$capture_url = 'https://api.stripe.com/v1/payment_intents/pi_123/capture';

		return [
			'succeeded intent' => [ WC_Stripe_Intent_Status::SUCCEEDED, [ $charge_url ] ],
			'capture response' => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, [ $capture_url, $charge_url ] ],
		];
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
			$cancel_order = $this->order_handler->prevent_cancelling_orders_awaiting_action( true, $order );
			$cancel_order = $this->order_handler->expire_open_checkout_session_before_cancelling( $cancel_order, $order );
			$this->assertSame( $expected, $cancel_order );
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
			'malformed error'                        => [ $this->make_stripe_response( 400, [ 'error' => 'temporarily unavailable' ] ), null, false ],
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
