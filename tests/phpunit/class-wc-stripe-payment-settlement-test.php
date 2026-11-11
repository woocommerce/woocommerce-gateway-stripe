<?php

/**
 * Exercises settlement with a real order whose total changes after Stripe takes payment.
 */
class WC_Stripe_Payment_Settlement_Test extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		WC_Stripe_Order_Helper::set_instance( null );
	}

	/**
	 * @since x.x.x
	 * @dataProvider provide_settlement_paths
	 * @param string $path Settlement entry point.
	 * @param string $change Change made after the intent was created.
	 * @param bool $has_presentment_details Whether the shopper paid in another currency.
	 */
	public function test_settlement_checks_current_order( string $path, string $change, bool $has_presentment_details = false ): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		WC_Tax::_insert_tax_rate(
			[
				'tax_rate_country' => 'DE',
				'tax_rate'         => '20',
				'tax_rate_name'    => 'VAT',
			]
		);

		$order = wc_create_order(
			[
				'customer_id' => 0,
				'created_via' => 'checkout',
			]
		);
		$order->set_currency( 'USD' );
		$order->set_payment_method( 'stripe' );
		$order->set_shipping_country( 'US' );
		$order->add_product( WC_Helper_Product::create_simple_product(), 1 );
		$order->calculate_totals();
		$order->save();
		$this->assertEquals( 10, $order->get_total() );

		$charge       = (object) [
			'id'                     => 'ch_settlement_' . $order->get_id(),
			'object'                 => 'charge',
			'amount'                 => 1000,
			'currency'               => 'usd',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => (object) [ 'type' => 'sepa_debit' ],
		];
		$intent       = (object) [
			'id'                   => 'pi_settlement_' . $order->get_id(),
			'object'               => 'payment_intent',
			'amount'               => 1000,
			'currency'             => 'usd',
			'status'               => 'succeeded',
			'payment_method_types' => [ 'card' ],
			'last_payment_error'   => null,
			'error'                => null,
			'metadata'             => (object) [ 'order_id' => $order->get_id() ],
		];
		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->add_payment_intent_to_order( $intent->id, $order );

		if ( $has_presentment_details ) {
			$intent->presentment_details = (object) [
				'presentment_amount'   => 920,
				'presentment_currency' => 'eur',
			];
		}

		if ( 'address' === $change ) {
			$order->set_shipping_country( 'DE' );
			$order->calculate_totals();
			$this->assertEquals( 12, $order->get_total() );
		} elseif ( 'lower_total' === $change ) {
			$order->set_total( 6 );
		} elseif ( 'currency' === $change ) {
			$order->set_currency( 'EUR' );
		}
		$order->save();
		$matches = 'unchanged' === $change;

		if ( 'sweep' === $path ) {
			$handler = $this->createPartialMock( WC_Stripe_Order_Handler::class, [ 'get_intent_from_order', 'get_latest_charge_from_intent' ] );
			$handler->method( 'get_intent_from_order' )->willReturn( $intent );
			$handler->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
			$this->assertFalse( $handler->prevent_cancelling_orders_awaiting_action( true, $order ) );
		} elseif ( 'verify' === $path ) {
			$gateway     = $this->createPartialMock( WC_Stripe_UPE_Payment_Gateway::class, [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'maybe_process_subscription_early_renewal_success' ] );
			$gateway->id = 'stripe';
			$gateway->method( 'get_intent_from_order' )->willReturn( $intent );
			$gateway->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
			$gateway->expects( $matches ? $this->once() : $this->never() )->method( 'maybe_process_subscription_early_renewal_success' );
			// The confirmation path's own amount check holds the order and throws before settlement.
			try {
				$gateway->verify_intent_after_checkout( $order );
				$this->assertTrue( $matches, 'A mismatched intent must not settle on the confirmation path.' );
			} catch ( WC_Stripe_Exception $e ) {
				$this->assertFalse( $matches );
				$this->assertSame( 'intent_order_mismatch', $e->getMessage() );
			}
		} elseif ( 'failed_ajax' === $path ) {
			wc_clear_notices();
			$gateway = $this->createPartialMock( WC_Stripe_UPE_Payment_Gateway::class, [ 'get_latest_charge_from_intent' ] );
			$gateway->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
			$controller = $this->createPartialMock( WC_Stripe_Intent_Controller::class, [ 'get_gateway' ] );
			$controller->method( 'get_gateway' )->willReturn( $gateway );
			$request                 = function ( $preempt, $args, $url ) use ( $intent ) {
				$this->assertStringEndsWith( 'payment_intents/' . $intent->id, $url );
				$this->assertSame( 'GET', $args['method'] );
				return [
					'headers'  => [],
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( $intent ),
				];
			};
			$die_handler             = function () {
				return function () {
					throw new WPAjaxDieContinueException();
				};
			};
			$original_post           = $_POST;
			$original_request        = $_REQUEST;
			$_POST                   = [
				'order_id'  => $order->get_id(),
				'intent_id' => $intent->id,
			];
			$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_failed_order_nonce' );
			add_filter( 'pre_http_request', $request, 10, 3 );
			add_filter( 'wp_doing_ajax', '__return_true' );
			add_filter( 'wp_die_ajax_handler', $die_handler );
			$terminated = false;
			ob_start();
			try {
				$controller->update_failed_order_ajax();
			} catch ( WPAjaxDieContinueException $e ) {
				$terminated = true;
			} finally {
				$result = json_decode( ob_get_clean(), true );
				remove_filter( 'pre_http_request', $request );
				remove_filter( 'wp_doing_ajax', '__return_true' );
				remove_filter( 'wp_die_ajax_handler', $die_handler );
				$_POST    = $original_post;
				$_REQUEST = $original_request;
			}
			$this->assertTrue( $terminated );
			$this->assertTrue( $result['success'] );
			$this->assertSame( $matches ? 0 : 1, wc_notice_count( 'error' ) );
			wc_clear_notices();
		} else {
			$handler = $this->createPartialMock( WC_Stripe_Webhook_Handler::class, [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'is_unreleased_charged_upfront_pre_order' ] );
			$handler->method( 'get_intent_from_order' )->willReturn( $intent );
			$handler->method( 'get_latest_charge_from_intent' )->willReturn( $charge );
			$handler->method( 'is_unreleased_charged_upfront_pre_order' )->willReturn( 'pre_order' === $path );
			$notification = (object) [
				'type' => 'payment_intent.succeeded',
				'data' => (object) [ 'object' => $intent ],
			];
			if ( 'deferred' === $path || 'pre_order' === $path ) {
				$handler->process_deferred_webhook(
					$notification->type,
					[
						'order_id'  => $order->get_id(),
						'intent_id' => $intent->id,
					],
					$notification
				);

			} else {
				$order_helper->update_stripe_upe_payment_type( $order, 'boleto' );
				$order->save();
				if ( 'processing' === $path ) {
					$notification->type = 'payment_intent.processing';
				}
				$handler->process_payment_intent( $notification );
			}
		}

		$order         = wc_get_order( $order->get_id() );
		$paid          = $matches && 'processing' !== $path;
		$unpaid_status = 'failed_ajax' === $path ? 'failed' : 'on-hold';
		$this->assertSame( $paid ? 'processing' : $unpaid_status, $order->get_status() );
		$this->assertSame( $paid, (bool) $order->get_date_paid() );
		$this->assertSame( $matches ? $charge->id : '', $order->get_transaction_id() );
		$this->assertFalse( $order_helper->lock_order_payment( $order ), 'Settlement must release the order lock on a mismatch.' );
		$order_helper->unlock_order_payment( $order );
		if ( ! $matches ) {
			$notes = implode( ' ', wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) );
			if ( 'failed_ajax' !== $path ) {
				$this->assertStringContainsString( 'verify' === $path ? 'does not match the order total' : 'does not match this order', $notes );
				$this->assertStringContainsString( 'USD 10', $notes );
				$this->assertStringContainsString( $order->get_currency() . ' ' . $order->get_total(), $notes );
			}
			$this->assertStringNotContainsString( 'Stripe charge complete', $notes );
		}
	}

	/**
	 * Provides settlement paths and order changes, including payments with presentment details.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	public function provide_settlement_paths(): array {
		$cases = [];
		foreach ( [ 'deferred', 'immediate', 'processing', 'sweep', 'verify', 'failed_ajax', 'pre_order' ] as $path ) {
			foreach ( [ 'unchanged', 'address', 'lower_total', 'currency' ] as $change ) {
				if ( 'pre_order' === $path && 'unchanged' === $change ) {
					continue;
				}
				$cases[ "$path / $change" ] = [ $path, $change ];
			}
		}
		$cases['deferred / converted payment, matching total'] = [
			'deferred',
			'unchanged',
			true,
		];
		$cases['deferred / converted payment, changed total']  = [
			'deferred',
			'address',
			true,
		];
		return $cases;
	}
}
