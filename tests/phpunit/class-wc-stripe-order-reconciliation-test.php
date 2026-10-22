<?php

/**
 * Tests for pending Stripe order recovery.
 *
 * @package WooCommerce/Stripe
 */
class WC_Stripe_Order_Reconciliation_Test extends WP_UnitTestCase {

	/**
	 * @var callable|null
	 */
	private $http_filter;

	/**
	 * @var WC_Stripe_Order_Reconciliation
	 */
	private $reconciliation;

	/**
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $webhook_handler;

	public function set_up() {
		parent::set_up();

		$this->gateway         = $this->getMockBuilder( WC_Gateway_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_latest_charge_from_intent', 'process_response' ] )
			->getMock();
		$this->webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'process_checkout_session_payment', 'process_reconciled_payment' ] )
			->getMock();
		$this->reconciliation  = new WC_Stripe_Order_Reconciliation( $this->gateway, $this->webhook_handler );

		$order_helper = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->onlyMethods( [ 'lock_order_payment', 'unlock_order_payment' ] )
			->getMock();
		$order_helper->method( 'lock_order_payment' )->willReturn( false );
		WC_Stripe_Order_Helper::set_instance( $order_helper );
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
	}

	public function tear_down() {
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		if ( $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
			$this->http_filter = null;
		}
		WC_Stripe_Order_Helper::set_instance( null );
		parent::tear_down();
	}

	/** @dataProvider provide_session_expansions */
	public function test_recovers_completed_checkout_session_with_verified_charge( bool $expanded, array $expected_endpoints ): void {
		$order     = $this->create_pending_stripe_order( true );
		$charge    = $this->successful_charge();
		$intent    = $this->successful_intent( $expanded ? $charge : 'ch_recovery' );
		$session   = $this->completed_session( $intent->id );
		$response  = clone $session;
		$endpoints = [];
		if ( $expanded ) {
			$response->payment_intent = $intent;
		}
		$this->mock_stripe_objects(
			[
				'/checkout/sessions/cs_recovery' => $response,
				'/payment_intents/pi_recovery'   => $intent,
			],
			$endpoints
		);
		$this->gateway->expects( $expanded ? $this->never() : $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->with( $this->equalTo( $intent ) )
			->willReturn( $charge );
		// The session reaches the shared webhook code with the PaymentIntent ID, as webhooks deliver it.
		$this->webhook_handler->expects( $this->once() )
			->method( 'process_checkout_session_payment' )
			->with( $this->isInstanceOf( WC_Order::class ), $this->equalTo( $session ), '', $this->equalTo( $intent ), $this->equalTo( $charge ) );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( $expected_endpoints, $endpoints );
		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
	}

	public static function provide_session_expansions(): array {
		return [
			'expanded PaymentIntent and charge' => [ true, [ '/checkout/sessions/cs_recovery' ] ],
			'PaymentIntent ID only'             => [ false, [ '/checkout/sessions/cs_recovery', '/payment_intents/pi_recovery' ] ],
		];
	}

	/** @dataProvider provide_invalid_sessions */
	public function test_does_not_fall_back_to_stored_intent_when_session_is_invalid( $session ): void {
		$order = $this->create_pending_stripe_order( true );
		$order->update_meta_data( '_stripe_intent_id', 'pi_stale' );
		$order->save();
		$endpoints = [];
		$this->mock_stripe_objects( [ '/checkout/sessions/cs_recovery' => $session ], $endpoints );
		$this->gateway->expects( $this->never() )->method( 'get_latest_charge_from_intent' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_checkout_session_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( [ '/checkout/sessions/cs_recovery' ], $endpoints );
	}

	public static function provide_invalid_sessions(): array {
		$open              = (object) [
			'id'             => 'cs_recovery',
			'object'         => 'checkout.session',
			'mode'           => 'payment',
			'status'         => 'open',
			'payment_intent' => 'pi_recovery',
		];
		$no_intent         = clone $open;
		$no_intent->status = 'complete';
		unset( $no_intent->payment_intent );

		return [
			'open session'                     => [ $open ],
			'completed session without intent' => [ $no_intent ],
		];
	}

	/** @dataProvider provide_final_sessions */
	public function test_final_session_falls_back_to_the_intent_of_a_later_payment_attempt( string $change ): void {
		$order = $this->create_pending_stripe_order( true );
		$order->update_meta_data( '_stripe_intent_id', 'pi_recovery' );
		$order->save();
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );

		$objects['/checkout/sessions/cs_recovery']->payment_intent = 'pi_from_the_session';
		if ( 'expired' === $change ) {
			$objects['/checkout/sessions/cs_recovery']->status = 'expired';
		} else {
			$objects['/checkout/sessions/cs_recovery']->mode = 'setup';
		}
		$endpoints = [];
		$this->mock_stripe_objects( $objects, $endpoints );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$settled = wc_get_order( $order->get_id() );
		$this->assertTrue( $settled->is_paid() );
		$this->assertSame( 'ch_recovery', $settled->get_transaction_id() );
		$this->assertContains( '/payment_intents/pi_recovery', $endpoints );
	}

	public static function provide_final_sessions(): array {
		return [
			'expired session'          => [ 'expired' ],
			'session for another mode' => [ 'mode' ],
		];
	}

	/** @dataProvider provide_later_attempts_while_the_session_is_open */
	public function test_open_session_gives_way_only_to_a_paid_later_payment_attempt( string $later_attempt, bool $expect_paid ): void {
		$order = $this->create_pending_stripe_order( true );
		$order->update_meta_data( '_stripe_intent_id', 'pi_recovery' );
		$order->save();
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		$objects['/checkout/sessions/cs_recovery']->status         = 'open';
		$objects['/checkout/sessions/cs_recovery']->payment_intent = null;
		if ( 'processing' === $later_attempt ) {
			$objects['/payment_intents/pi_recovery']->status = WC_Stripe_Intent_Status::PROCESSING;
		} elseif ( 'missing' === $later_attempt ) {
			$objects['/payment_intents/pi_recovery'] = (object) [
				'error' => (object) [
					'code' => 'resource_missing',
					'type' => 'invalid_request_error',
				],
			];
		}
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$checked = wc_get_order( $order->get_id() );
		$this->assertSame( $expect_paid, $checked->is_paid() );
		// Anything short of a paid later attempt keeps following the session, which can still be paid.
		$this->assertCount( $expect_paid ? 0 : 1, $this->scheduled_checks( $order->get_id() ) );
	}

	public static function provide_later_attempts_while_the_session_is_open(): array {
		return [
			'paid'           => [ 'paid', true ],
			'not paid yet'   => [ 'processing', false ],
			'missing intent' => [ 'missing', false ],
		];
	}

	public function test_final_session_without_another_intent_stops(): void {
		$order = $this->create_pending_stripe_order( true );
		$order->update_meta_data( '_stripe_intent_id', 'pi_recovery' );
		$order->save();
		$session         = $this->completed_session( 'pi_recovery' );
		$session->status = 'expired';
		$endpoints       = [];
		$this->mock_stripe_objects( [ '/checkout/sessions/cs_recovery' => $session ], $endpoints );
		$this->gateway->expects( $this->never() )->method( 'process_response' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_reconciled_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( [ '/checkout/sessions/cs_recovery' ], $endpoints );
		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_paid_session_with_another_intent_settles_and_warns(): void {
		$order = $this->create_pending_stripe_order( true );
		$order->update_meta_data( '_stripe_intent_id', 'pi_other_attempt' );
		$order->save();
		$this->use_real_settlement();
		$this->mock_stripe_objects( $this->successful_stripe_objects( $order ) );
		$warnings = [];
		$logger   = $this->getMockBuilder( WC_Logger::class )->getMock();
		$logger->method( 'warning' )->willReturnCallback(
			static function ( $message, $context ) use ( &$warnings ) {
				$warnings[] = [ $message, $context ];
			}
		);
		WC_Stripe_Logger::$logger = $logger;
		add_filter( 'wc_stripe_logger_can_log', '__return_true' );

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			remove_filter( 'wc_stripe_logger_can_log', '__return_true' );
			WC_Stripe_Logger::$logger = null;
		}

		$this->assertTrue( wc_get_order( $order->get_id() )->is_paid() );
		$second_intent_warnings = array_filter(
			$warnings,
			static function ( $warning ) {
				return false !== strpos( $warning[0], 'also has another PaymentIntent' ) && 'pi_other_attempt' === ( $warning[1]['other_intent_id'] ?? '' );
			}
		);
		$this->assertCount( 1, $second_intent_warnings );
	}

	/** @dataProvider provide_invalid_charges */
	public function test_rejects_invalid_charge_before_settlement( $charge ): void {
		$order  = $this->create_pending_stripe_order( true );
		$intent = $this->successful_intent( $charge );
		$this->mock_stripe_objects(
			[
				'/checkout/sessions/cs_recovery' => $this->completed_session( $intent->id ),
				'/payment_intents/pi_recovery'   => $intent,
			]
		);
		$this->gateway->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
		$this->webhook_handler->expects( $this->never() )->method( 'process_checkout_session_payment' );
		$this->gateway->expects( $this->never() )->method( 'process_response' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_reconciled_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	public static function provide_invalid_charges(): array {
		$wrong_owner                         = self::fixture_charge();
		$wrong_owner->payment_intent         = 'pi_other';
		$refunded                            = self::fixture_charge();
		$refunded->refunded                  = true;
		$wrong_amount                        = self::fixture_charge();
		$wrong_amount->amount                = 999;
		$not_captured                        = self::fixture_charge();
		$not_captured->captured              = false;
		$partially_refunded                  = self::fixture_charge();
		$partially_refunded->amount_refunded = 1;
		$disputed                            = self::fixture_charge();
		$disputed->disputed                  = true;
		$pending                             = self::fixture_charge();
		$pending->status                     = 'pending';

		return [
			'charge belongs to another intent' => [ $wrong_owner ],
			'refunded charge'                  => [ $refunded ],
			'charge amount differs'            => [ $wrong_amount ],
			'charge is not captured'           => [ $not_captured ],
			'partially refunded charge'        => [ $partially_refunded ],
			'disputed charge'                  => [ $disputed ],
			'pending charge'                   => [ $pending ],
		];
	}

	public function test_payment_lock_prevents_stripe_lookups(): void {
		$order        = $this->create_pending_stripe_order( true );
		$order_helper = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->onlyMethods( [ 'lock_order_payment', 'unlock_order_payment' ] )
			->getMock();
		$order_helper->expects( $this->once() )->method( 'lock_order_payment' )->willReturn( true );
		$order_helper->expects( $this->never() )->method( 'unlock_order_payment' );
		WC_Stripe_Order_Helper::set_instance( $order_helper );
		$this->gateway->expects( $this->never() )->method( 'get_latest_charge_from_intent' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_checkout_session_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertCount( 1, $this->scheduled_checks( $order->get_id() ) );
	}

	/** @dataProvider provide_ineligible_orders */
	public function test_skips_ineligible_orders( string $status, string $payment_method ): void {
		$order = $this->create_pending_stripe_order( true );
		$order->set_status( $status );
		$order->set_payment_method( $payment_method );
		$order->save();
		$this->gateway->expects( $this->never() )->method( 'get_latest_charge_from_intent' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );
	}

	public static function provide_ineligible_orders(): array {
		return [
			'processing order' => [ 'processing', 'stripe' ],
			'non Stripe order' => [ 'pending', 'woocommerce_payments' ],
		];
	}

	public function test_payment_intent_status_can_advance_to_an_async_payment_without_settling_it(): void {
		$order          = $this->create_pending_stripe_order( false );
		$intent         = $this->successful_intent( self::fixture_charge() );
		$intent->status = 'processing';
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ] );
		$this->gateway->expects( $this->never() )->method( 'get_latest_charge_from_intent' );
		$this->gateway->expects( $this->never() )->method( 'process_response' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_reconciled_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_order_links_are_rechecked_after_stripe_requests(): void {
		$order             = $this->create_pending_stripe_order( false );
		$intent            = $this->successful_intent( self::fixture_charge() );
		$charge            = self::fixture_charge();
		$this->http_filter = static function ( $preempt, $args, $url ) use ( $order, $intent ) {
			if ( false === strpos( $url, '/payment_intents/pi_recovery' ) ) {
				return $preempt;
			}

			$order->update_meta_data( '_stripe_checkout_session_id', 'cs_added_during_request' );
			$order->save();
			return [
				'headers'  => [],
				'body'     => wp_json_encode( $intent ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
		$this->gateway->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
		$this->gateway->expects( $this->never() )->method( 'process_response' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_reconciled_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_recovers_session_payment_through_the_real_settlement_flow_only_once(): void {
		$order = $this->create_pending_stripe_order( true );
		$this->use_real_settlement();
		$objects  = $this->successful_stripe_objects( $order );
		$requests = [];
		$this->mock_stripe_objects( $objects, $requests );
		$payment_complete_count    = 0;
		$payment_complete_listener = static function () use ( &$payment_complete_count ) {
			++$payment_complete_count;
		};
		add_action( 'woocommerce_payment_complete', $payment_complete_listener );
		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
			$note_count    = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );
			$request_count = count( $requests );
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			remove_action( 'woocommerce_payment_complete', $payment_complete_listener );
		}

		$settled   = wc_get_order( $order->get_id() );
		$notes     = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$note_text = implode(
			"\n",
			array_map(
				static function ( $note ) {
					return $note->content;
				},
				$notes
			)
		);
		$this->assertTrue( $settled->is_paid() );
		$this->assertNotEmpty( $settled->get_date_paid() );
		$this->assertSame( 'ch_recovery', $settled->get_transaction_id() );
		$this->assertSame( 'pi_recovery', $settled->get_meta( '_stripe_intent_id', true ) );
		$this->assertSame( 'eur', WC_Stripe_Order_Helper::get_instance()->get_stripe_presentment_currency( $settled ) );
		$this->assertSame( 1500, (int) WC_Stripe_Order_Helper::get_instance()->get_stripe_presentment_amount( $settled ) );
		$this->assertSame( 1, $payment_complete_count );
		$this->assertSame( $note_count, count( $notes ) );
		$this->assertSame( $request_count, count( $requests ) );
		$this->assertStringContainsString( 'Stripe charge complete (Charge ID: ch_recovery)', $note_text );
		$this->assertStringNotContainsString( '(via webhook)', $note_text );
		$this->assertSame( 1, substr_count( $note_text, 'A Stripe payment status check updated this order, because no webhook did.' ) );
	}

	/** @dataProvider provide_settlement_paths */
	public function test_radar_manual_review_puts_the_order_on_hold( bool $has_session ): void {
		$order = $this->create_pending_stripe_order( $has_session );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		if ( ! $has_session ) {
			unset( $objects['/checkout/sessions/cs_recovery'] );
		}
		$objects['/charges/ch_recovery']->outcome = (object) [ 'type' => 'manual_review' ];
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$held      = wc_get_order( $order->get_id() );
		$note_text = $this->note_text( $held );
		$this->assertSame( 'on-hold', $held->get_status() );
		$this->assertFalse( $held->is_paid() );
		$this->assertSame( 'ch_recovery', $held->get_transaction_id() );
		// Kept as the payment-complete status (not the pending status it was in), so closing the review completes the order.
		$this->assertSame( $held->needs_processing() ? 'processing' : 'completed', $held->get_meta( '_stripe_status_before_hold', true ) );
		$this->assertStringContainsString( 'Stripe Radar is reviewing this payment', $note_text );
		$this->assertStringContainsString( 'because no webhook did', $note_text );
		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
	}

	public static function provide_settlement_paths(): array {
		return [
			'PaymentIntent'    => [ false ],
			'Checkout Session' => [ true ],
		];
	}

	public function test_order_settled_by_a_webhook_during_the_check_is_not_settled_again(): void {
		$order           = $this->create_pending_stripe_order( false );
		$intent          = $this->successful_intent( self::fixture_charge() );
		$webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'process_checkout_session_payment', 'process_reconciled_payment' ] )
			->getMock();
		$webhook_handler->expects( $this->never() )->method( 'process_reconciled_payment' );
		$this->reconciliation = new WC_Stripe_Order_Reconciliation( $this->gateway, $webhook_handler );
		$this->gateway->method( 'get_latest_charge_from_intent' )->willReturn( self::fixture_charge() );
		$this->http_filter = static function ( $preempt, $args, $url ) use ( $order, $intent ) {
			if ( false === strpos( $url, '/payment_intents/pi_recovery' ) ) {
				return $preempt;
			}

			// The webhook settles the order while the check waits for Stripe.
			$order->payment_complete( 'ch_recovery' );
			return [
				'headers'  => [],
				'body'     => wp_json_encode( $intent ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
		$this->assertStringNotContainsString( 'A Stripe payment status check updated this order', $this->note_text( $order ) );
	}

	public function test_late_webhook_does_not_settle_an_order_the_check_already_settled(): void {
		$order = $this->create_pending_stripe_order( false );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		unset( $objects['/checkout/sessions/cs_recovery'] );
		$this->mock_stripe_objects( $objects );
		$payment_complete_count    = 0;
		$payment_complete_listener = static function () use ( &$payment_complete_count ) {
			++$payment_complete_count;
		};
		add_action( 'woocommerce_payment_complete', $payment_complete_listener );

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
			$notes_after_check = $this->note_text( $order );

			$webhook = new WC_Stripe_Webhook_Handler();
			$webhook->process_payment_intent(
				(object) [
					'type' => 'payment_intent.succeeded',
					'data' => (object) [ 'object' => $objects['/payment_intents/pi_recovery'] ],
				]
			);
		} finally {
			remove_action( 'woocommerce_payment_complete', $payment_complete_listener );
		}

		$this->assertTrue( wc_get_order( $order->get_id() )->is_paid() );
		$this->assertSame( 1, $payment_complete_count );
		$this->assertSame( $notes_after_check, $this->note_text( $order ) );
	}

	/** @dataProvider provide_session_settlement_mismatches */
	public function test_session_settlement_mismatch_is_held_for_review( string $schema ): void {
		$order = $this->create_pending_stripe_order( true );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		if ( 'legacy' === $schema ) {
			$objects['/checkout/sessions/cs_recovery']->currency            = 'eur';
			$objects['/checkout/sessions/cs_recovery']->amount_total        = 1500;
			$objects['/checkout/sessions/cs_recovery']->currency_conversion = (object) [
				'source_currency' => strtolower( get_woocommerce_currency() ),
				'amount_total'    => 1,
			];
		} else {
			$objects['/checkout/sessions/cs_recovery']->currency     = 'eur';
			$objects['/checkout/sessions/cs_recovery']->amount_total = 1;
		}
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$review_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $review_order->get_status() );
		$this->assertFalse( $review_order->is_paid() );
		$this->assertSame( '', $review_order->get_transaction_id() );
		$this->assertStringNotContainsString( 'because no webhook did', $this->note_text( $review_order ) );
	}

	public static function provide_session_settlement_mismatches(): array {
		return [
			'modern schema' => [ 'modern' ],
			'legacy schema' => [ 'legacy' ],
		];
	}

	public function test_presentment_currency_can_differ_from_store_settlement_currency(): void {
		$order = $this->create_pending_stripe_order( true );
		$this->use_real_settlement();
		$this->mock_stripe_objects( $this->successful_stripe_objects( $order ) );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$settled = wc_get_order( $order->get_id() );
		$this->assertTrue( $settled->is_paid() );
		$this->assertSame( strtolower( get_woocommerce_currency() ), strtolower( $settled->get_currency() ) );
		$this->assertSame( 'eur', WC_Stripe_Order_Helper::get_instance()->get_stripe_presentment_currency( $settled ) );
	}

	public function test_payment_intent_only_recovery_uses_normal_charge_settlement(): void {
		$order = $this->create_pending_stripe_order( false );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		unset( $objects['/checkout/sessions/cs_recovery'] );
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$settled = wc_get_order( $order->get_id() );
		$this->assertTrue( $settled->is_paid() );
		$this->assertSame( 'ch_recovery', $settled->get_transaction_id() );
		$this->assertSame( 'pi_recovery', $settled->get_meta( '_stripe_intent_id', true ) );
	}

	public function test_payment_intent_recovery_preserves_unreleased_pre_order_lifecycle(): void {
		$order   = $this->create_pending_stripe_order( false );
		$handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->onlyMethods( [ 'maybe_mark_order_as_pre_ordered' ] )
			->getMock();
		$handler->expects( $this->once() )
			->method( 'maybe_mark_order_as_pre_ordered' )
			->with( $this->isInstanceOf( WC_Order::class ), $this->isInstanceOf( stdClass::class ) )
			->willReturnCallback(
				static function ( $pre_order, $charge ) {
					$pre_order->set_transaction_id( $charge->id );
					WC_Stripe_Order_Helper::get_instance()->sync_stripe_charge_captured( $pre_order, $charge );
					$pre_order->save();
					return true;
				}
			);
		$this->gateway         = $handler;
		$this->webhook_handler = $handler;
		$this->reconciliation  = new WC_Stripe_Order_Reconciliation( $handler, $handler );
		$objects               = $this->successful_stripe_objects( $order );
		unset( $objects['/checkout/sessions/cs_recovery'] );
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$pre_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'pending', $pre_order->get_status() );
		$this->assertFalse( $pre_order->is_paid() );
		$this->assertSame( 'ch_recovery', $pre_order->get_transaction_id() );
		$this->assertSame( 'yes', WC_Stripe_Order_Helper::get_instance()->get_stripe_charge_captured( $pre_order ) );
	}

	public function test_authorized_payment_intent_is_held_for_capture(): void {
		$order = $this->create_pending_stripe_order( false );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		unset( $objects['/checkout/sessions/cs_recovery'] );
		$objects['/payment_intents/pi_recovery']->status          = 'requires_capture';
		$objects['/payment_intents/pi_recovery']->amount_received = 0;
		$objects['/charges/ch_recovery']->captured                = false;
		$objects['/charges/ch_recovery']->paid                    = false;
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$authorized = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $authorized->get_status() );
		$this->assertFalse( $authorized->is_paid() );
		$this->assertSame( 'ch_recovery', $authorized->get_transaction_id() );
	}

	/** @dataProvider provide_payment_intent_mismatches */
	public function test_payment_intent_mismatch_is_held_for_review( string $mismatch ): void {
		$order = $this->create_pending_stripe_order( false );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		unset( $objects['/checkout/sessions/cs_recovery'] );
		if ( 'amount' === $mismatch ) {
			$objects['/payment_intents/pi_recovery']->amount          = 1;
			$objects['/payment_intents/pi_recovery']->amount_received = 1;
			$objects['/charges/ch_recovery']->amount                  = 1;
		} else {
			$objects['/payment_intents/pi_recovery']->currency = 'eur';
			$objects['/charges/ch_recovery']->currency         = 'eur';
		}
		$this->mock_stripe_objects( $objects );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$mismatch_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $mismatch_order->get_status() );
		$this->assertFalse( $mismatch_order->is_paid() );
		$this->assertSame( '', $mismatch_order->get_transaction_id() );
		$expected_amount = WC_Stripe_Helper::get_stripe_amount( (float) $mismatch_order->get_total(), $mismatch_order->get_currency() );
		$note_text       = $this->note_text( $mismatch_order );
		$this->assertStringContainsString( 'Stripe payment pi_recovery was found, but it does not match this order', $note_text );
		$this->assertStringContainsString( 'the order expects ' . $expected_amount . ' ' . strtoupper( $mismatch_order->get_currency() ), $note_text );
		$this->assertStringNotContainsString( 'because no webhook did', $note_text );
	}

	public static function provide_payment_intent_mismatches(): array {
		return [
			'amount mismatch'   => [ 'amount' ],
			'currency mismatch' => [ 'currency' ],
		];
	}

	/** @dataProvider provide_settlement_paths */
	public function test_mismatch_hold_is_logged_as_held_for_review( bool $has_session ): void {
		$order = $this->create_pending_stripe_order( $has_session );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		if ( $has_session ) {
			$objects['/checkout/sessions/cs_recovery']->amount_total = 1;
		} else {
			unset( $objects['/checkout/sessions/cs_recovery'] );
			$objects['/payment_intents/pi_recovery']->amount          = 1;
			$objects['/payment_intents/pi_recovery']->amount_received = 1;
			$objects['/charges/ch_recovery']->amount                  = 1;
		}
		$this->mock_stripe_objects( $objects );
		$messages = [];
		$logger   = $this->getMockBuilder( WC_Logger::class )->getMock();
		$logger->method( 'log' )->willReturnCallback(
			static function ( $level, $message ) use ( &$messages ) {
				$messages[] = $level . ': ' . $message;
			}
		);
		foreach ( [ 'info', 'warning' ] as $level ) {
			$logger->method( $level )->willReturnCallback(
				static function ( $message ) use ( &$messages, $level ) {
					$messages[] = $level . ': ' . $message;
				}
			);
		}
		WC_Stripe_Logger::$logger = $logger;
		add_filter( 'wc_stripe_logger_can_log', '__return_true' );

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			remove_filter( 'wc_stripe_logger_can_log', '__return_true' );
			WC_Stripe_Logger::$logger = null;
		}

		$log = implode( "\n", $messages );
		$this->assertSame( 'on-hold', wc_get_order( $order->get_id() )->get_status() );
		$this->assertStringContainsString( 'info: Reconciliation found a Stripe payment that does not match the order; the order is held for review.', $log );
		$this->assertStringNotContainsString( 'did not record it on the order', $log );
	}

	public function test_stripe_api_error_keeps_order_pending_and_schedules_a_retry(): void {
		$order        = $this->create_pending_stripe_order( true );
		$order_helper = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->onlyMethods( [ 'lock_order_payment', 'unlock_order_payment' ] )
			->getMock();
		$order_helper->expects( $this->once() )->method( 'lock_order_payment' )->willReturn( false );
		$order_helper->expects( $this->once() )->method( 'unlock_order_payment' );
		WC_Stripe_Order_Helper::set_instance( $order_helper );
		$request_count     = 0;
		$this->http_filter = static function ( $preempt, $args, $url ) use ( &$request_count ) {
			if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions/cs_recovery' ) ) {
				++$request_count;
				return new WP_Error( 'stripe_timeout', 'Stripe is unavailable.' );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
		$this->assertSame( 1, $request_count );
		$this->assertCount( 1, $this->scheduled_checks( $order->get_id() ) );
	}

	/** @dataProvider provide_check_outcomes */
	public function test_scheduled_check_retries_only_while_stripe_may_still_complete_the_payment( string $scenario, bool $expect_retry ): void {
		$has_session = in_array( $scenario, [ 'open session', 'expired session' ], true );
		$order       = $this->create_pending_stripe_order( $has_session );
		$charge      = self::fixture_charge();
		$intent      = $this->successful_intent( $charge );
		$objects     = [ '/payment_intents/pi_recovery' => $intent ];

		switch ( $scenario ) {
			case 'processing intent':
				$intent->status = WC_Stripe_Intent_Status::PROCESSING;
				break;
			case 'intent requires action':
				$intent->status = WC_Stripe_Intent_Status::REQUIRES_ACTION;
				break;
			case 'intent requires payment method':
				$intent->status = WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD;
				break;
			case 'canceled intent':
				$intent->status = WC_Stripe_Intent_Status::CANCELED;
				break;
			case 'missing intent':
				$objects['/payment_intents/pi_recovery'] = (object) [
					'error' => (object) [
						'code' => 'resource_missing',
						'type' => 'invalid_request_error',
					],
				];
				break;
			case 'refunded charge':
				$charge->refunded = true;
				break;
			case 'open session':
			case 'expired session':
				$session         = $this->completed_session( $intent->id );
				$session->status = 'open session' === $scenario ? 'open' : 'expired';
				$objects         = [ '/checkout/sessions/cs_recovery' => $session ];
				break;
		}
		$this->mock_stripe_objects( $objects );
		$this->gateway->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
		$this->gateway->expects( $this->never() )->method( 'process_response' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_reconciled_payment' );
		$this->webhook_handler->expects( $this->never() )->method( 'process_checkout_session_payment' );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
		$this->assertCount( $expect_retry ? 1 : 0, $this->scheduled_checks( $order->get_id() ) );
	}

	public static function provide_check_outcomes(): array {
		return [
			'processing intent'              => [ 'processing intent', true ],
			'intent requires action'         => [ 'intent requires action', true ],
			'intent requires payment method' => [ 'intent requires payment method', true ],
			'open session'                   => [ 'open session', true ],
			'canceled intent'                => [ 'canceled intent', false ],
			'expired session'                => [ 'expired session', false ],
			'missing intent'                 => [ 'missing intent', false ],
			'refunded charge'                => [ 'refunded charge', false ],
		];
	}

	public function test_missing_stripe_object_logs_stripes_message(): void {
		$order   = $this->create_pending_stripe_order( false );
		$message = "No such payment_intent: 'pi_recovery'; a similar object exists in test mode, but a live mode key was used to make this request.";
		$this->mock_stripe_objects(
			[
				'/payment_intents/pi_recovery' => (object) [
					'error' => (object) [
						'code'    => 'resource_missing',
						'type'    => 'invalid_request_error',
						'message' => $message,
					],
				],
			]
		);
		$logger = $this->getMockBuilder( WC_Logger::class )->getMock();
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with(
				$this->stringContains( 'Stopped reconciling a pending Stripe order' ),
				$this->callback(
					static function ( $context ) use ( $message ) {
						return is_array( $context ) && ( $context['stripe_message'] ?? null ) === $message && 'pi_recovery' === ( $context['stripe_id'] ?? null );
					}
				)
			);
		WC_Stripe_Logger::$logger = $logger;
		add_filter( 'wc_stripe_logger_can_log', '__return_true' );

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			remove_filter( 'wc_stripe_logger_can_log', '__return_true' );
			WC_Stripe_Logger::$logger = null;
		}

		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
	}

	/** @dataProvider provide_check_windows */
	public function test_next_check_follows_the_payment_method_window( string $payment_type, int $started_ago, ?int $expected_delay ): void {
		$order = $this->create_pending_stripe_order( false );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::STARTED_META, (string) ( time() - $started_ago ) );
		if ( '' !== $payment_type ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $payment_type );
		}
		$order->save_meta_data();
		$intent         = $this->successful_intent( self::fixture_charge() );
		$intent->status = WC_Stripe_Intent_Status::PROCESSING;
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ] );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$checks = $this->scheduled_checks( $order->get_id() );
		if ( null === $expected_delay ) {
			$this->assertSame( [], $checks );
			return;
		}
		$this->assertCount( 1, $checks );
		$this->assertEqualsWithDelta( time() + $expected_delay, $checks[0], 5 );
	}

	public static function provide_check_windows(): array {
		return [
			'card, after the first check'          => [ WC_Stripe_Payment_Methods::CARD, 30 * MINUTE_IN_SECONDS, 6 * HOUR_IN_SECONDS ],
			'card, last check at the deadline'     => [ WC_Stripe_Payment_Methods::CARD, 20 * HOUR_IN_SECONDS, 4 * HOUR_IN_SECONDS ],
			'card, window passed'                  => [ WC_Stripe_Payment_Methods::CARD, DAY_IN_SECONDS, null ],
			'unknown type uses the shorter window' => [ '', DAY_IN_SECONDS, null ],
			'SEPA, after the first check'          => [ WC_Stripe_Payment_Methods::SEPA_DEBIT, 30 * MINUTE_IN_SECONDS, DAY_IN_SECONDS ],
			'OXXO, last check at the deadline'     => [ WC_Stripe_Payment_Methods::OXXO, 20 * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS ],
			'ACH, window passed'                   => [ WC_Stripe_Payment_Methods::ACH, 21 * DAY_IN_SECONDS, null ],
		];
	}

	/** @dataProvider provide_unreachable_stripe_windows */
	public function test_checks_that_cannot_reach_stripe_continue_up_to_the_longest_window( int $started_ago, ?int $expected_delay ): void {
		$order = $this->create_pending_stripe_order( false );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::STARTED_META, (string) ( time() - $started_ago ) );
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, WC_Stripe_Payment_Methods::CARD );
		$order->save_meta_data();
		$this->http_filter = static function ( $preempt, $args, $url ) {
			return false !== strpos( $url, 'api.stripe.com' ) ? new WP_Error( 'http_request_failed', 'Timeout.' ) : $preempt;
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$checks = $this->scheduled_checks( $order->get_id() );
		if ( null === $expected_delay ) {
			$this->assertSame( [], $checks );
			return;
		}
		$this->assertCount( 1, $checks );
		$this->assertEqualsWithDelta( time() + $expected_delay, $checks[0], 5 );
	}

	public static function provide_unreachable_stripe_windows(): array {
		return [
			'within the card window'          => [ 30 * MINUTE_IN_SECONDS, 6 * HOUR_IN_SECONDS ],
			'card window passed, daily after' => [ DAY_IN_SECONDS, DAY_IN_SECONDS ],
			'last check at the longest limit' => [ 20 * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS ],
			'longest window passed'           => [ 21 * DAY_IN_SECONDS, null ],
		];
	}

	public function test_delayed_payment_method_reported_by_stripe_keeps_the_longer_window(): void {
		$order = $this->create_pending_stripe_order( false );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::STARTED_META, (string) ( time() - 2 * DAY_IN_SECONDS ) );
		$order->save_meta_data();
		$intent                 = $this->successful_intent( self::fixture_charge() );
		$intent->status         = WC_Stripe_Intent_Status::PROCESSING;
		$intent->payment_method = (object) [
			'id'   => 'pm_recovery',
			'type' => WC_Stripe_Payment_Methods::ACH,
		];
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ] );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'yes', wc_get_order( $order->get_id() )->get_meta( WC_Stripe_Order_Reconciliation::DELAYED_META, true ) );
		$checks = $this->scheduled_checks( $order->get_id() );
		$this->assertCount( 1, $checks );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, $checks[0], 5 );
	}

	public function test_window_starts_at_order_creation_when_checks_were_not_scheduled_at_checkout(): void {
		$order = $this->create_pending_stripe_order( false );
		$order->set_date_created( time() - 2 * DAY_IN_SECONDS );
		$order->save();
		$intent         = $this->successful_intent( self::fixture_charge() );
		$intent->status = WC_Stripe_Intent_Status::PROCESSING;
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ] );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
	}

	public function test_queued_check_exits_without_stripe_requests_while_the_feature_is_off(): void {
		$order     = $this->create_pending_stripe_order( false );
		$endpoints = [];
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $this->successful_intent( self::fixture_charge() ) ], $endpoints );
		update_option( WC_Stripe_Feature_Flags::ORDER_RECONCILIATION_FEATURE_FLAG_NAME, 'no' );

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			delete_option( WC_Stripe_Feature_Flags::ORDER_RECONCILIATION_FEATURE_FLAG_NAME );
		}

		$this->assertSame( [], $endpoints );
		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_errors_thrown_during_the_check_schedule_a_retry(): void {
		$order = $this->create_pending_stripe_order( false );
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $this->successful_intent( 'ch_recovery' ) ] );
		$this->gateway->method( 'get_latest_charge_from_intent' )->willThrowException( new Error( 'A callback failed.' ) );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
		$this->assertCount( 1, $this->scheduled_checks( $order->get_id() ) );
	}

	/**
	 * @dataProvider provide_settlement_failures
	 */
	public function test_settlement_errors_stop_the_checks( bool $has_session, bool $paid_before_error, bool $expect_note ): void {
		$order = $this->create_pending_stripe_order( $has_session );
		$this->mock_settlement_error( $order, $has_session, $paid_before_error );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
		$this->assertSame( $expect_note ? 1 : 0, substr_count( $this->note_text( $order ), 'but the order could not be updated with it' ) );
	}

	public static function provide_settlement_failures(): array {
		return [
			'Checkout Session'            => [ true, false, true ],
			'PaymentIntent'               => [ false, false, true ],
			'order paid before the error' => [ false, true, false ],
		];
	}

	public function test_manual_check_reports_a_settlement_error_once(): void {
		$order = $this->create_pending_stripe_order( false );
		$this->mock_settlement_error( $order, false, false );

		$this->reconciliation->reconcile_order_payment( $order->get_id(), true );

		$notes = $this->note_text( $order );
		$this->assertSame( 1, substr_count( $notes, 'but the order could not be updated with it' ) );
		$this->assertStringNotContainsString( 'Stripe could not be reached', $notes );
	}

	public function test_reconciled_payment_fires_the_deprecated_process_payment_action_once(): void {
		$this->setExpectedDeprecated( 'wc_gateway_stripe_process_payment' );
		$order = $this->create_pending_stripe_order( false );
		$this->use_real_settlement();
		$objects = $this->successful_stripe_objects( $order );
		unset( $objects['/checkout/sessions/cs_recovery'] );
		$this->mock_stripe_objects( $objects );
		$calls    = 0;
		$listener = static function () use ( &$calls ) {
			++$calls;
		};
		add_action( 'wc_gateway_stripe_process_payment', $listener );

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			remove_action( 'wc_gateway_stripe_process_payment', $listener );
		}

		$this->assertSame( 1, $calls );
		$this->assertTrue( wc_get_order( $order->get_id() )->is_paid() );
	}

	public function test_throttled_api_keys_skip_stripe_and_schedule_a_retry(): void {
		$order             = $this->create_pending_stripe_order( false );
		$http_calls        = 0;
		$this->http_filter = static function ( $preempt, $args, $url ) use ( &$http_calls ) {
			if ( false !== strpos( $url, 'api.stripe.com' ) ) {
				++$http_calls;
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
		foreach ( [ 'test', 'live' ] as $mode ) {
			WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY, 99, HOUR_IN_SECONDS, $mode );
		}

		try {
			$this->reconciliation->reconcile_order_payment( $order->get_id() );
		} finally {
			foreach ( [ 'test', 'live' ] as $mode ) {
				WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY, $mode );
			}
		}

		$this->assertSame( 0, $http_calls );
		$this->assertCount( 1, $this->scheduled_checks( $order->get_id() ) );
	}

	public function test_manual_check_explains_a_payment_that_is_not_confirmed_yet(): void {
		$order          = $this->create_pending_stripe_order( false );
		$intent         = $this->successful_intent( self::fixture_charge() );
		$intent->status = WC_Stripe_Intent_Status::PROCESSING;
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ] );

		$this->reconciliation->reconcile_order_payment( $order->get_id(), true );

		$this->assertStringContainsString( 'Stripe has not confirmed this payment yet (status: processing)', $this->note_text( $order ) );
	}

	public function test_manual_check_explains_when_stripe_cannot_be_reached(): void {
		$order             = $this->create_pending_stripe_order( false );
		$this->http_filter = static function ( $preempt, $args, $url ) {
			return false !== strpos( $url, 'api.stripe.com' ) ? new WP_Error( 'http_request_failed', 'Timeout.' ) : $preempt;
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$this->reconciliation->reconcile_order_payment( $order->get_id(), true );

		$note_text = $this->note_text( $order );
		$this->assertStringContainsString( 'the payment status could not be confirmed right now', $note_text );
		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
	}

	/** @dataProvider provide_manual_stops */
	public function test_manual_check_explains_why_it_stopped( string $scenario, string $expected_note ): void {
		$this->mock_stop_scenario( $scenario, $order );

		$this->reconciliation->reconcile_order_payment( $order->get_id(), true );

		$this->assertStringContainsString( $expected_note, $this->note_text( $order ) );
		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	public static function provide_manual_stops(): array {
		return [
			'expired session' => [ 'expired session', 'the Stripe Checkout Session for this order can no longer be paid' ],
			'canceled intent' => [ 'canceled intent', 'the Stripe payment for this order was canceled' ],
			'missing intent'  => [ 'missing intent', 'Stripe has no record of this payment for the connected account and mode' ],
			'refunded charge' => [ 'refunded charge', 'was refunded, disputed, or does not cover the full amount' ],
		];
	}

	public function test_scheduled_check_adds_no_note_when_it_stops(): void {
		$this->mock_stop_scenario( 'canceled intent', $order );
		$notes_before = $this->note_text( $order );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( $notes_before, $this->note_text( $order ) );
	}

	public function test_scheduled_check_adds_no_note_when_the_payment_is_not_confirmed_yet(): void {
		$order          = $this->create_pending_stripe_order( false );
		$intent         = $this->successful_intent( self::fixture_charge() );
		$intent->status = WC_Stripe_Intent_Status::PROCESSING;
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ] );
		$notes_before = $this->note_text( $order );

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( $notes_before, $this->note_text( $order ) );
	}

	public function test_manual_check_does_not_schedule_a_retry(): void {
		$order          = $this->create_pending_stripe_order( false );
		$intent         = $this->successful_intent( self::fixture_charge() );
		$intent->status = WC_Stripe_Intent_Status::PROCESSING;
		$endpoints      = [];
		$this->mock_stripe_objects( [ '/payment_intents/pi_recovery' => $intent ], $endpoints );

		$this->reconciliation->reconcile_order_payment( $order->get_id(), true );

		$this->assertSame( [ '/payment_intents/pi_recovery' ], $endpoints );
		$this->assertSame( [], $this->scheduled_checks( $order->get_id() ) );
	}

	/**
	 * Creates a pending order and mocks Stripe so that checking it stops for the given reason.
	 *
	 * @param string        $scenario One of 'expired session', 'canceled intent', 'missing intent', 'refunded charge'.
	 * @param WC_Order|null $order    Receives the created order.
	 */
	private function mock_stop_scenario( string $scenario, ?WC_Order &$order ): void {
		$order  = $this->create_pending_stripe_order( 'expired session' === $scenario );
		$charge = self::fixture_charge();
		$intent = $this->successful_intent( $charge );
		switch ( $scenario ) {
			case 'expired session':
				$session         = $this->completed_session( $intent->id );
				$session->status = 'expired';
				$objects         = [ '/checkout/sessions/cs_recovery' => $session ];
				break;
			case 'canceled intent':
				$intent->status = WC_Stripe_Intent_Status::CANCELED;
				$objects        = [ '/payment_intents/pi_recovery' => $intent ];
				break;
			case 'missing intent':
				$objects = [
					'/payment_intents/pi_recovery' => (object) [
						'error' => (object) [
							'code' => 'resource_missing',
							'type' => 'invalid_request_error',
						],
					],
				];
				break;
			default:
				$charge->refunded = true;
				$objects          = [ '/payment_intents/pi_recovery' => $intent ];
		}
		$this->mock_stripe_objects( $objects );
		$this->gateway->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
	}

	/**
	 * Mocks a verified Stripe payment whose settlement throws.
	 *
	 * @param WC_Order $order             Order being checked.
	 * @param bool     $has_session       Whether the order has a Checkout Session.
	 * @param bool     $paid_before_error Whether settlement pays the order before it throws.
	 */
	private function mock_settlement_error( WC_Order $order, bool $has_session, bool $paid_before_error ): void {
		$intent  = $this->successful_intent( self::fixture_charge() );
		$objects = [ '/payment_intents/pi_recovery' => $intent ];
		if ( $has_session ) {
			$objects['/checkout/sessions/cs_recovery'] = $this->completed_session( $intent->id );
		}
		$this->mock_stripe_objects( $objects );

		$webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'process_checkout_session_payment', 'process_reconciled_payment' ] )
			->getMock();
		$webhook_handler->expects( $this->once() )
			->method( $has_session ? 'process_checkout_session_payment' : 'process_reconciled_payment' )
			->willReturnCallback(
				static function () use ( $order, $paid_before_error ) {
					if ( $paid_before_error ) {
						wc_get_order( $order->get_id() )->payment_complete( 'ch_recovery' );
					}
					throw new Error( 'Settlement failed.' );
				}
			);
		$this->reconciliation = new WC_Stripe_Order_Reconciliation( $this->gateway, $webhook_handler );
	}

	private function note_text( WC_Order $order ): string {
		return implode(
			"\n",
			array_map(
				static function ( $note ) {
					return $note->content;
				},
				wc_get_order_notes( [ 'order_id' => $order->get_id() ] )
			)
		);
	}

	/**
	 * Timestamps of the pending scheduled (not manual) checks of an order.
	 */
	private function scheduled_checks( int $order_id ): array {
		$actions    = as_get_scheduled_actions(
			[
				'hook'     => WC_Stripe_Order_Reconciliation::ORDER_HOOK,
				'args'     => [ 'order_id' => $order_id ],
				'group'    => WC_Stripe_Order_Reconciliation::GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => -1,
			],
			'objects'
		);
		$timestamps = [];
		foreach ( $actions as $action ) {
			$timestamps[] = $action->get_schedule()->get_date()->getTimestamp();
		}
		return $timestamps;
	}

	private function create_pending_stripe_order( bool $has_session ): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'pending' );
		$order->set_payment_method( 'stripe' );
		$order->set_total( 12.34 );
		if ( $has_session ) {
			$order->update_meta_data( '_stripe_checkout_session_id', 'cs_recovery' );
		} else {
			$order->update_meta_data( '_stripe_intent_id', 'pi_recovery' );
		}
		$order->save();
		return $order;
	}

	private function successful_intent( $charge ): object {
		return (object) [
			'id'              => 'pi_recovery',
			'object'          => 'payment_intent',
			'status'          => 'succeeded',
			'amount'          => WC_Stripe_Helper::get_stripe_amount( 12.34, get_woocommerce_currency() ),
			'amount_received' => WC_Stripe_Helper::get_stripe_amount( 12.34, get_woocommerce_currency() ),
			'currency'        => strtolower( get_woocommerce_currency() ),
			'latest_charge'   => $charge,
		];
	}

	private function completed_session( string $intent_id ): object {
		return (object) [
			'id'             => 'cs_recovery',
			'object'         => 'checkout.session',
			'mode'           => 'payment',
			'status'         => 'complete',
			'payment_intent' => $intent_id,
		];
	}

	private function use_real_settlement(): void {
		$handler               = new WC_Stripe_Webhook_Handler();
		$this->gateway         = $handler;
		$this->webhook_handler = $handler;
		$this->reconciliation  = new WC_Stripe_Order_Reconciliation( $handler, $handler );
	}

	private function successful_stripe_objects( WC_Order $order ): array {
		$amount  = WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $order->get_currency() );
		$charge  = (object) [
			'id'                     => 'ch_recovery',
			'object'                 => 'charge',
			'payment_intent'         => 'pi_recovery',
			'amount'                 => $amount,
			'currency'               => strtolower( $order->get_currency() ),
			'status'                 => 'succeeded',
			'captured'               => true,
			'paid'                   => true,
			'refunded'               => false,
			'amount_refunded'        => 0,
			'payment_method'         => 'pm_recovery',
			'payment_method_details' => (object) [
				'card' => (object) [
					'brand' => 'visa',
					'last4' => '4242',
				],
			],
		];
		$intent  = (object) [
			'id'              => 'pi_recovery',
			'object'          => 'payment_intent',
			'status'          => 'succeeded',
			'amount'          => $amount,
			'amount_received' => $amount,
			'currency'        => strtolower( $order->get_currency() ),
			'payment_method'  => (object) [
				'id'     => 'pm_recovery',
				'object' => 'payment_method',
				'type'   => 'card',
				'card'   => (object) [],
			],
			'latest_charge'   => $charge,
		];
		$session = (object) [
			'id'                  => 'cs_recovery',
			'object'              => 'checkout.session',
			'mode'                => 'payment',
			'status'              => 'complete',
			'payment_intent'      => $intent,
			'customer'            => null,
			'currency'            => strtolower( $order->get_currency() ),
			'amount_total'        => $amount,
			'presentment_details' => (object) [
				'presentment_currency' => 'eur',
				'presentment_amount'   => 1500,
			],
		];

		return [
			'/checkout/sessions/cs_recovery' => $session,
			'/payment_intents/pi_recovery'   => $intent,
			'/charges/ch_recovery'           => $charge,
		];
	}

	private function successful_charge(): object {
		return self::fixture_charge();
	}

	private static function fixture_charge(): object {
		return (object) [
			'id'              => 'ch_recovery',
			'object'          => 'charge',
			'payment_intent'  => 'pi_recovery',
			'amount'          => WC_Stripe_Helper::get_stripe_amount( 12.34, get_woocommerce_currency() ),
			'currency'        => strtolower( get_woocommerce_currency() ),
			'status'          => 'succeeded',
			'captured'        => true,
			'paid'            => true,
			'refunded'        => false,
			'amount_refunded' => 0,
		];
	}

	private function mock_stripe_objects( array $objects, ?array &$endpoints = null ): void {
		$this->http_filter = static function ( $preempt, $args, $url ) use ( $objects, &$endpoints ) {
			if ( false === strpos( $url, 'api.stripe.com' ) ) {
				return $preempt;
			}
			foreach ( $objects as $path => $object ) {
				if ( false === strpos( $url, $path ) ) {
					continue;
				}
				if ( null !== $endpoints ) {
					$endpoints[] = $path;
				}
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $object ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}
			return new WP_Error( 'unexpected_stripe_request', 'Unexpected Stripe API request in reconciliation test.' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}
}
