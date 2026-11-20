<?php

/**
 * Exercises settlement callbacks against the same charge before and after a reversal.
 *
 * @since x.x.x
 */
class WC_Stripe_Payment_Replay_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Replaying a checkout settlement callback can't re-settle an order whose payment was already reversed.
	 *
	 * @since x.x.x
	 * @dataProvider provide_callback_replays
	 * @param string      $reversal How the payment was reversed.
	 * @param string      $callback The checkout callback being replayed.
	 * @param string|null $refund_flag Stored refund marker, or null to leave it absent.
	 */
	public function test_checkout_callback_cannot_reinstate_reversed_payment( string $reversal, string $callback, ?string $refund_flag = null ): void {
		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD ] );
		wp_set_current_user( 0 );
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'virtual'      => true,
				'downloadable' => true,
				'downloads'    => [
					[
						'name' => 'Download',
						'file' => 'https://example.org/download.txt',
					],
				],
			]
		);
		$order   = WC_Helper_Order::create_order( 0, $product, [ 'payment_method' => 'stripe' ] );
		$gateway = new WC_Stripe_UPE_Payment_Gateway();
		$handler = new WC_Stripe_Webhook_Handler();
		$charge  = (object) [
			'id'                     => 'ch_replay',
			'object'                 => 'charge',
			'captured'               => true,
			'status'                 => 'succeeded',
			'amount'                 => 5000,
			'amount_refunded'        => 0,
			'refunded'               => false,
			'disputed'               => false,
			'payment_method_details' => (object) [ 'type' => 'card' ],
		];
		$intent  = (object) [
			'id'                 => 'pi_replay',
			'object'             => 'payment_intent',
			'status'             => 'succeeded',
			'amount'             => 5000,
			'currency'           => strtolower( $order->get_currency() ),
			'latest_charge'      => $charge->id,
			'last_payment_error' => null,
			'error'              => null,
		];
		$order->update_meta_data( '_stripe_intent_id', $intent->id );
		if ( null !== $refund_flag ) {
			$order->update_meta_data( '_stripe_refunded_after_cancellation', $refund_flag );
		}
		$order->save();

		$refunds = 0;
		$api     = function ( $preempt, $args, $url ) use ( $intent, $charge, &$refunds ) {
			if ( false !== strpos( $url, 'payment_intents/pi_replay' ) ) {
				$body = $intent;
			} elseif ( false !== strpos( $url, 'charges/ch_replay' ) ) {
				$body = $charge;
			} elseif ( false !== strpos( $url, '/refunds' ) ) {
				$this->assertSame( 'POST', $args['method'] );
				$this->assertSame( 5000, $args['body']['amount'] );
				++$refunds;
				$charge->refunded        = true;
				$charge->amount_refunded = $charge->amount;
				$body                    = (object) [
					'id'     => 're_replay',
					'amount' => 5000,
					'status' => 'succeeded',
				];
			} else {
				return new WP_Error( 'unexpected_request', 'Unexpected HTTP request in payment replay test.' );
			}
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( $body ),
			];
		};
		add_filter( 'pre_http_request', $api, 10, 3 );

		$controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_gateway' ] )
			->getMock();
		$controller->method( 'get_gateway' )->willReturn( $gateway );
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		Ajax_Test_Helper::init_hooks();
		// JSON responses must terminate the request, as they do in WordPress.
		add_filter( 'wp_die_ajax_handler', [ $this, 'get_wp_die_handler' ], 11 );
		try {
			if ( 'refund' === $reversal ) {
				$order->update_status( 'cancelled' );
				$handler->process_deferred_webhook(
					'payment_intent.succeeded',
					[
						'order_id'  => $order->get_id(),
						'intent_id' => $intent->id,
					],
					(object) [
						'type' => 'payment_intent.succeeded',
						'data' => (object) [ 'object' => $intent ],
					]
				);
				$order = wc_get_order( $order->get_id() );
				$this->assertSame( 1, $refunds );
				$this->assertSame( 'cancelled', $order->get_status() );
				$this->assertSame( 'yes', $order->get_meta( '_stripe_refunded_after_cancellation' ) );
				$this->assertTrue( $charge->refunded );
			} elseif ( 'none' !== $reversal ) {
				$gateway->process_response( $charge, $order );
				$this->assertTrue( $order->is_download_permitted() );
				$charge->disputed = true;
				$dispute          = (object) [
					'data' => (object) [
						'object' => (object) [
							'id'     => 'dp_replay',
							'charge' => $charge->id,
							'status' => 'needs_response',
						],
					],
				];
				$handler->process_webhook_dispute( $dispute );
				if ( 'lost_dispute' === $reversal ) {
					$dispute->data->object->status = 'lost';
					$handler->process_webhook_dispute_closed( $dispute );
				}
				$order = wc_get_order( $order->get_id() );
				$this->assertSame( 'lost_dispute' === $reversal ? 'failed' : 'on-hold', $order->get_status() );
			}

			$original_status = $order->get_status();
			$original_notes  = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );
			$paid_events     = did_action( 'woocommerce_payment_complete' );
			$this->assertFalse( $order->is_download_permitted() );
			$_POST                   = [
				'order_id'  => $order->get_id(),
				'intent_id' => $intent->id,
			];
			$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'update_order_status_ajax' === $callback ? 'wc_stripe_update_order_status_nonce' : 'wc_stripe_update_failed_order_nonce' );
			ob_start();
			try {
				if ( 'return_url' === $callback ) {
					$stop_redirect = static function () {
						throw new RuntimeException( 'redirect_intercepted' );
					};
					add_filter( 'wp_redirect', $stop_redirect );
					try {
						$gateway->process_upe_redirect_payment( $order->get_id(), $intent->id, false );
					} catch ( RuntimeException $e ) {
						$this->assertSame( 'redirect_intercepted', $e->getMessage() );
					} finally {
						remove_filter( 'wp_redirect', $stop_redirect );
					}
					$response = [ 'success' => false ];
				} else {
					try {
						$controller->$callback();
						$this->fail( 'The AJAX response must terminate the request.' );
					} catch ( WPDieException $e ) {
						$response = json_decode( ob_get_contents(), true );
					}
				}
			} finally {
				ob_end_clean();
			}

			$order = wc_get_order( $order->get_id() );
			if ( 'none' === $reversal ) {
				$this->assertTrue( $response['success'] );
				$this->assertSame( 'completed', $order->get_status() );
				$this->assertNotNull( $order->get_date_paid() );
				$this->assertTrue( $order->is_download_permitted() );
				$this->assertFalse( $charge->refunded );
			} else {
				$this->assertSame( $original_status, $order->get_status() );
				$this->assertFalse( $order->is_paid() );
				$this->assertFalse( $order->is_download_permitted() );
				if ( 'refund' === $reversal || 'update_failed_order_ajax' === $callback ) {
					$this->assertFalse( $response['success'] );
					$this->assertSame( $original_notes, count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) ) );
				}
				$this->assertSame( $paid_events, did_action( 'woocommerce_payment_complete' ) );
				if ( 'refund' === $reversal ) {
					$this->assertNull( $order->get_date_paid() );
				}
			}
		} finally {
			remove_filter( 'pre_http_request', $api );
			remove_filter( 'wp_die_ajax_handler', [ $this, 'get_wp_die_handler' ], 11 );
			Ajax_Test_Helper::remove_hooks();
			$_POST    = $old_post;
			$_REQUEST = $old_request;
		}
	}

	/**
	 * Provider for `test_checkout_callback_cannot_reinstate_reversed_payment`
	 *
	 * @since x.x.x
	 * @return array
	 */
	public function provide_callback_replays(): array {
		return [
			'refund, confirmation callback'   => [ 'refund', 'update_order_status_ajax' ],
			'refund, failure callback'        => [ 'refund', 'update_failed_order_ajax' ],
			'lost dispute, confirmation'      => [ 'lost_dispute', 'update_order_status_ajax' ],
			'lost dispute, failure'           => [ 'lost_dispute', 'update_failed_order_ajax' ],
			'lost dispute, return URL'        => [ 'lost_dispute', 'return_url' ],
			'open dispute, failure'           => [ 'open_dispute', 'update_failed_order_ajax' ],
			'legitimate confirmation'         => [ 'none', 'update_order_status_ajax' ],
			'legitimate failure callback'     => [ 'none', 'update_failed_order_ajax' ],
			'false refund flag, confirmation' => [ 'none', 'update_order_status_ajax', 'no' ],
			'false refund flag, failure'      => [ 'none', 'update_failed_order_ajax', 'no' ],
		];
	}
}
