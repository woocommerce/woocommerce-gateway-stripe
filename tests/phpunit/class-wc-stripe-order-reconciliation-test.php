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
			->onlyMethods( [ 'process_checkout_session_payment' ] )
			->getMock();
		$this->reconciliation  = new WC_Stripe_Order_Reconciliation( $this->gateway, $this->webhook_handler );

		$order_helper = $this->getMockBuilder( WC_Stripe_Order_Helper::class )
			->onlyMethods( [ 'lock_order_payment', 'unlock_order_payment' ] )
			->getMock();
		$order_helper->method( 'lock_order_payment' )->willReturn( false );
		WC_Stripe_Order_Helper::set_instance( $order_helper );
	}

	public function tear_down() {
		if ( $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
			$this->http_filter = null;
		}
		WC_Stripe_Order_Helper::set_instance( null );
		parent::tear_down();
	}

	public function test_recovers_completed_checkout_session_with_verified_charge(): void {
		$order     = $this->create_pending_stripe_order( true );
		$charge    = $this->successful_charge();
		$intent    = $this->successful_intent( $charge );
		$session   = $this->completed_session( $intent->id );
		$endpoints = [];
		$this->mock_stripe_objects(
			[
				'/checkout/sessions/cs_recovery' => $session,
				'/payment_intents/pi_recovery'   => $intent,
			],
			$endpoints
		);
		$this->gateway->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->with( $this->equalTo( $intent ) )
			->willReturn( $charge );
		$this->webhook_handler->expects( $this->once() )
			->method( 'process_checkout_session_payment' )
			->with( $this->isInstanceOf( WC_Order::class ), $this->equalTo( $session ), '', $this->equalTo( $intent ), $this->equalTo( $charge ) );

		$this->reconciliation->reconcile_order_payment( [ 'order_id' => $order->get_id() ] );

		$this->assertSame( [ '/checkout/sessions/cs_recovery', '/payment_intents/pi_recovery' ], $endpoints );
		$this->assertNotEmpty( wc_get_order( $order->get_id() )->get_meta( WC_Stripe_Order_Reconciliation::ATTEMPT_META, true ) );
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

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', $order->get_status() );
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

		return [
			'charge belongs to another intent' => [ $wrong_owner ],
			'refunded charge'                  => [ $refunded ],
			'charge amount differs'            => [ $wrong_amount ],
			'charge is not captured'           => [ $not_captured ],
			'partially refunded charge'        => [ $partially_refunded ],
			'disputed charge'                  => [ $disputed ],
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

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', $order->get_status() );
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

		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', $order->get_status() );
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

		$mismatch = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $mismatch->get_status() );
		$this->assertFalse( $mismatch->is_paid() );
		$this->assertSame( '', $mismatch->get_transaction_id() );
	}

	public static function provide_payment_intent_mismatches(): array {
		return [
			'amount mismatch'   => [ 'amount' ],
			'currency mismatch' => [ 'currency' ],
		];
	}

	public function test_stripe_api_error_keeps_order_pending_and_cooldown_suppresses_retry(): void {
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
		$this->reconciliation->reconcile_order_payment( $order->get_id() );

		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
		$this->assertSame( 1, $request_count );
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
			'latest_charge'   => 'ch_recovery',
		];
		$session = (object) [
			'id'                  => 'cs_recovery',
			'object'              => 'checkout.session',
			'mode'                => 'payment',
			'status'              => 'complete',
			'payment_intent'      => 'pi_recovery',
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
