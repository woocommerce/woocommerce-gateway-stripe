<?php
/**
 * Tests for WC_Stripe_Checkout_Session_Settlement.
 *
 * @package WooCommerce_Stripe/Tests
 */

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Tests settling an order from its completed Checkout Session.
 */
class WC_Stripe_Checkout_Session_Settlement_Test extends WP_UnitTestCase {
	/**
	 * The session's PaymentIntent, as the gateway loads it for the order.
	 */
	private const MOCK_PAYMENT_INTENT = [
		'amount'               => 5000,
		'currency'             => 'usd',
		'payment_method_types' => [ 'card' ],
		'id'                   => 'pi_mock',
		'object'               => 'payment_intent',
		'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
	];

	/**
	 * The PaymentIntent's latest charge.
	 */
	private const MOCK_CHARGE = [
		'id'                     => 'ch_mock',
		'captured'               => true,
		'payment_method_details' => [],
		'status'                 => 'succeeded',
	];

	/**
	 * Create a pending order linked to a Checkout Session.
	 *
	 * @param string $session_id Checkout Session ID.
	 * @param int    $user_id    Customer of the order, or 0 for a guest.
	 * @return WC_Order
	 */
	private function create_session_order( string $session_id, int $user_id = 0 ): WC_Order {
		$order = WC_Helper_Order::create_order( $user_id );
		$order->set_status( OrderStatus::PENDING );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $session_id );
		$order->save_meta_data();

		return $order;
	}

	/**
	 * Build a completed Checkout Session that matches the order total.
	 *
	 * @param string   $session_id Checkout Session ID.
	 * @param WC_Order $order      Order the session pays for.
	 * @param array    $overrides  Session fields to add or replace.
	 * @return object
	 */
	private function build_session( string $session_id, WC_Order $order, array $overrides = [] ): object {
		return (object) array_merge(
			[
				'id'             => $session_id,
				'payment_intent' => 'pi_test_abc',
				'amount_total'   => WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
				'currency'       => strtolower( $order->get_currency() ),
			],
			$overrides
		);
	}

	/**
	 * Build a gateway that loads the given intent and charge and records the charge without calling Stripe.
	 *
	 * @param object|null $payment_method The intent's payment method, or null.
	 * @param object|null $charge         The latest charge; defaults to a captured card charge.
	 * @return WC_Stripe_Payment_Gateway&PHPUnit\Framework\MockObject\MockObject
	 */
	private function build_gateway( ?object $payment_method = null, ?object $charge = null ) {
		$gateway = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->onlyMethods( [ 'get_intent_from_order', 'get_latest_charge_from_intent', 'process_response' ] )
			->getMock();
		$gateway->method( 'get_intent_from_order' )
			->willReturn( (object) array_merge( self::MOCK_PAYMENT_INTENT, [ 'payment_method' => $payment_method ] ) );
		$gateway->method( 'get_latest_charge_from_intent' )
			->willReturn( $charge ?? (object) self::MOCK_CHARGE );

		return $gateway;
	}

	/**
	 * The PaymentIntent metadata job must be scheduled with the order's description and metadata.
	 */
	public function test_schedules_payment_intent_metadata_job(): void {
		$session_id = 'cs_test_schedule123';
		$order      = $this->create_session_order( $session_id );

		// Capture a fixed baseline before scheduling so the timestamp assertion can't race a 1-second rollover.
		$test_start_time = time();

		// The metadata is built through get_metadata_from_order(), which runs the wc_stripe_intent_metadata
		// filter. Register a sentinel to prove the filter is applied here too.
		$filter = function ( $metadata ) {
			$metadata['sentinel'] = 'applied';
			return $metadata;
		};
		add_filter( 'wc_stripe_intent_metadata', $filter );

		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$scheduled_time = null;
		$scheduled_args = null;
		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->callback(
					function ( $timestamp ) use ( &$scheduled_time ) {
						$scheduled_time = $timestamp;
						return true;
					}
				),
				'wc_stripe_process_payment_intent_metadata',
				$this->callback(
					function ( $args ) use ( &$scheduled_args ) {
						$scheduled_args = $args;
						return true;
					}
				)
			);

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $this->build_gateway(), $mock_scheduler );
		try {
			$settlement->process_payment( $order, $this->build_session( $session_id, $order ) );
		} finally {
			remove_filter( 'wc_stripe_intent_metadata', $filter );
		}

		// The job runs 2 minutes after settlement.
		$this->assertIsInt( $scheduled_time );
		$this->assertGreaterThanOrEqual( $test_start_time + 2 * MINUTE_IN_SECONDS, $scheduled_time );
		$this->assertLessThanOrEqual( time() + 2 * MINUTE_IN_SECONDS, $scheduled_time );

		$this->assertSame( 'pi_test_abc', $scheduled_args['payment_intent_id'] );
		$this->assertSame( sprintf( '%1$s - Order %2$s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ), $scheduled_args['request']['description'] );
		$this->assertEquals( $order->get_order_number(), $scheduled_args['request']['metadata']['order_id'] );
		$this->assertSame( $order->get_order_key(), $scheduled_args['request']['metadata']['order_key'] );
		$this->assertNotEmpty( $scheduled_args['request']['metadata']['signature'] );
		$this->assertIsInt( $scheduled_args['request']['metadata']['tax_amount'] );

		// Adaptive Pricing transactions must carry the same order/customer identifiers as the standard flow.
		$this->assertSame(
			trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			$scheduled_args['request']['metadata']['customer_name']
		);
		$this->assertSame( $order->get_billing_email(), $scheduled_args['request']['metadata']['customer_email'] );
		$this->assertSame( esc_url( get_site_url() ), $scheduled_args['request']['metadata']['site_url'] );
		$this->assertSame( 'single', $scheduled_args['request']['metadata']['payment_type'] );
		$this->assertSame( 'applied', $scheduled_args['request']['metadata']['sentinel'] );
	}

	/**
	 * Settling throws when recording the charge fails, and the metadata job must not be scheduled then.
	 */
	public function test_does_not_schedule_metadata_job_when_settlement_throws(): void {
		$session_id = 'cs_test_exception123';
		$order      = $this->create_session_order( $session_id );

		$mock_scheduler = $this->createMock( WC_Stripe_Action_Scheduler_Service::class );
		$mock_scheduler->expects( $this->never() )->method( 'schedule_job' );

		$gateway = $this->build_gateway();
		$gateway->method( 'process_response' )->willThrowException( new Exception( 'Test processing exception' ) );

		$this->expectExceptionMessage( 'Test processing exception' );
		( new WC_Stripe_Checkout_Session_Settlement( $gateway, $mock_scheduler ) )->process_payment( $order, $this->build_session( $session_id, $order ) );
	}

	/**
	 * A completed Checkout Session must not mark an order paid when the session's settlement amount or
	 * currency no longer matches the order total, while legitimate payments (including Adaptive Pricing
	 * local-currency purchases, where only presentment_details differ) still settle.
	 *
	 * @dataProvider provide_settlement_verification_cases
	 *
	 * @param string      $order_total          Order total.
	 * @param string      $order_currency       Order currency.
	 * @param int|null    $session_amount_total Session amount_total, or null to leave it out.
	 * @param string|null $session_currency     Session currency, or null to leave it out.
	 * @param array|null  $presentment_details  Session presentment_details, if any.
	 * @param bool        $expect_settled       Whether the order is settled.
	 * @param string      $initial_status       Order status before settling.
	 * @param array|null  $currency_conversion  Legacy session currency_conversion, if any.
	 */
	public function test_verifies_settlement_amount_and_currency(
		string $order_total,
		string $order_currency,
		$session_amount_total,
		?string $session_currency,
		?array $presentment_details,
		bool $expect_settled,
		string $initial_status = OrderStatus::PENDING,
		?array $currency_conversion = null
	): void {
		$session_id = 'cs_test_settle';

		$order = WC_Helper_Order::create_order();
		$order->set_currency( $order_currency );
		$order->set_total( $order_total );
		$order->set_status( $initial_status );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $session_id );
		$order->save_meta_data();

		$effective_session_amount   = $session_amount_total;
		$effective_session_currency = $session_currency;

		$session = [
			'id'             => $session_id,
			'payment_intent' => 'pi_test_settle',
		];
		if ( null !== $session_amount_total ) {
			$session['amount_total'] = $session_amount_total;
		}
		if ( null !== $session_currency ) {
			$session['currency'] = $session_currency;
		}
		if ( null !== $presentment_details ) {
			$session['presentment_details'] = (object) $presentment_details;
		}
		if ( null !== $currency_conversion ) {
			$session['currency_conversion'] = (object) $currency_conversion;
			if ( array_key_exists( 'amount_total', $currency_conversion ) ) {
				$effective_session_amount = $currency_conversion['amount_total'];
			}
			if ( array_key_exists( 'source_currency', $currency_conversion ) ) {
				$effective_session_currency = $currency_conversion['source_currency'];
			}
		}

		// process_response is the call that runs payment_complete(). It must fire only when the
		// settlement figures match; a mismatch has to return before reaching it.
		$gateway = $this->build_gateway();
		$gateway->expects( $expect_settled ? $this->once() : $this->never() )->method( 'process_response' );

		$settlement      = new WC_Stripe_Checkout_Session_Settlement( $gateway, $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );
		$expected_result = $expect_settled ? WC_Stripe_Checkout_Session_Settlement::RESULT_PROCESSED : WC_Stripe_Checkout_Session_Settlement::RESULT_ON_HOLD;
		$this->assertSame( $expected_result, $settlement->process_payment( $order, (object) $session ) );

		$notes          = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$mismatch_notes = array_filter(
			$notes,
			static function ( $note ) {
				return str_contains( $note->content, 'does not match the order total' );
			}
		);

		if ( $expect_settled ) {
			$this->assertEmpty( $mismatch_notes, 'A matching settlement must not add the mismatch note.' );
			return;
		}

		$this->assertNotEmpty( $mismatch_notes, 'A mismatched settlement must add a note explaining why the order was not marked paid.' );
		// On hold rather than pending: wc_cancel_unpaid_orders() would otherwise cancel the order
		// and restore its stock even though Stripe captured the payment.
		$this->assertTrue(
			wc_get_order( $order->get_id() )->has_status( OrderStatus::ON_HOLD ),
			'A mismatched settlement must leave the order on hold so it is not auto-cancelled as unpaid.'
		);

		// Settlement stops before the intent and charge are recorded on the order, so the note is
		// the only place an admin can pick up the identifiers needed to reconcile in Stripe.
		$mismatch_note = current( $mismatch_notes )->content;
		$this->assertStringContainsString( $session_id, $mismatch_note, 'The note must name the Checkout Session to reconcile against.' );
		$this->assertStringContainsString( 'pi_test_settle', $mismatch_note, 'The note must name the PaymentIntent to reconcile against.' );
		$this->assertStringContainsString(
			'href="https://dashboard.stripe.com/test/payments/pi_test_settle"',
			$mismatch_note,
			'The PaymentIntent must link to the Stripe dashboard.'
		);
		$this->assertStringContainsString( ' target="_blank"', $mismatch_note, 'The dashboard link must open in a new tab.' );
		$this->assertStringContainsString( ' rel="noopener noreferrer"', $mismatch_note, 'The dashboard link must specify that no opener or referrer details should be included.' );

		if ( null === $effective_session_amount ) {
			$this->assertStringContainsString( 'the Checkout Session settlement amount (unknown)', $mismatch_note, 'The note must mention that the Session amount is unknown.' );
		} elseif ( ! $effective_session_currency ) {
			$this->assertStringContainsString( sprintf( 'the Checkout Session settlement amount (unknown currency; Stripe amount: %1$d)', $effective_session_amount ), $mismatch_note, 'The note must mention that the Session currency is unknown and include the Stripe amount.' );
		} else {
			$this->assertStringContainsString( 'the Checkout Session settlement amount (' . strtoupper( $effective_session_currency ) . ' ' . WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( $effective_session_amount, $effective_session_currency ) . ')', $mismatch_note, 'The note must mention the Session amount and currency.' );
		}
	}

	/**
	 * Data provider for test_verifies_settlement_amount_and_currency.
	 *
	 * @return array
	 */
	public function provide_settlement_verification_cases(): array {
		return [
			// Session amount and currency match the order — settle normally.
			'amount and currency match'    => [ '50.00', 'USD', 5000, 'usd', null, true ],
			// The Session amount is higher than the order total.
			'session amount higher'        => [ '50.00', 'USD', 6000, 'usd', null, false ],
			// The Session amount is lower than the order total.
			'session amount lower'         => [ '50.00', 'USD', 100, 'usd', null, false ],
			// Same settlement amount but the wrong currency must not settle.
			'currency mismatch'            => [ '50.00', 'USD', 5000, 'eur', null, false ],
			// Valid Adaptive Pricing: amount_total stays in the store currency; only presentment differs.
			'adaptive pricing presentment' => [
				'20.00',
				'USD',
				2000,
				'usd',
				[
					'presentment_currency' => 'eur',
					'presentment_amount'   => 1500,
				],
				true,
			],
			// An unverifiable Session (no amount_total) must not settle.
			'missing session amount_total' => [ '50.00', 'USD', null, 'usd', null, false ],
			// An unverifiable Session (no currency) must not settle.
			'missing session currency'     => [ '50.00', 'USD', 5000, '', null, false ],
			// An order already on hold (async payment flow) still has to record why it was refused;
			// update_status() alone would drop the note because the status does not change.
			'mismatch on an on-hold order' => [ '50.00', 'USD', 100, 'usd', null, false, OrderStatus::ON_HOLD ],
			// Legacy (pre-2025-03-31.basil) Adaptive Pricing shape: the buyer's currency is at the top
			// level and the store's total sits in currency_conversion, which must still settle.
			'legacy adaptive pricing'      => [
				'50.00',
				'USD',
				4300,
				'eur',
				null,
				true,
				OrderStatus::PENDING,
				[
					'source_currency' => 'usd',
					'amount_total'    => 5000,
				],
			],
			// The legacy shape must not settle a Session that collected more than the order.
			'legacy amount higher'         => [
				'50.00',
				'USD',
				6450,
				'eur',
				null,
				false,
				OrderStatus::PENDING,
				[
					'source_currency' => 'usd',
					'amount_total'    => 7500,
				],
			],
			// The legacy shape must not settle a Session that collected less than the order.
			'legacy amount lower'          => [
				'50.00',
				'USD',
				86,
				'eur',
				null,
				false,
				OrderStatus::PENDING,
				[
					'source_currency' => 'usd',
					'amount_total'    => 100,
				],
			],
			'legacy no amount_total'       => [
				'50.00',
				'USD',
				4300,
				'eur',
				null,
				false,
				OrderStatus::PENDING,
				[
					'source_currency' => 'usd',
					'amount_total'    => null,
				],
			],
			'legacy no currency'           => [
				'50.00',
				'USD',
				4300,
				'eur',
				null,
				false,
				OrderStatus::PENDING,
				[
					'source_currency' => '',
					'amount_total'    => 5000,
				],
			],
		];
	}

	/**
	 * The amount the customer paid in their own currency must be stored on the order, while the order keeps the store currency.
	 */
	public function test_records_the_presentment_currency_and_amount(): void {
		$session_id = 'cs_test_presentment';
		$order      = $this->create_session_order( $session_id );
		$session    = $this->build_session(
			$session_id,
			$order,
			[
				'presentment_details' => (object) [
					'presentment_currency' => 'eur',
					'presentment_amount'   => 1500,
				],
			]
		);

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $this->build_gateway(), $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );
		$settlement->process_payment( $order, $session );

		$settled = wc_get_order( $order->get_id() );
		$this->assertSame( strtolower( get_woocommerce_currency() ), strtolower( $settled->get_currency() ) );
		$this->assertSame( 'eur', WC_Stripe_Order_Helper::get_instance()->get_stripe_presentment_currency( $settled ) );
		$this->assertSame( 1500, (int) WC_Stripe_Order_Helper::get_instance()->get_stripe_presentment_amount( $settled ) );
	}

	/**
	 * The session's Stripe customer must be linked to the order and to the order's WP user,
	 * without overwriting an ID the user already has.
	 *
	 * @dataProvider provide_customer_attachment_cases
	 *
	 * @param bool        $order_has_user            Whether the order belongs to a WP user.
	 * @param string|null $existing_user_customer_id A Stripe customer ID already stored on that user, if any.
	 * @param string|null $expected_user_customer_id The user's Stripe customer ID after settling.
	 * @param bool        $user_lock_held            Whether another request holds the user attachment lock.
	 */
	public function test_attaches_session_customer( bool $order_has_user, ?string $existing_user_customer_id, ?string $expected_user_customer_id, bool $user_lock_held = false ): void {
		$session_id          = 'cs_test_customer123';
		$session_customer_id = 'cus_session_123';

		$user_id = 0;
		if ( $order_has_user ) {
			$user_id = self::factory()->user->create();
			if ( null !== $existing_user_customer_id ) {
				update_user_option( $user_id, '_stripe_customer_id', $existing_user_customer_id, false );
			}
			if ( $user_lock_held ) {
				add_option( 'wc_stripe_user_customer_lock_' . $user_id, time() . ':other-request', '', false );
			}
		}

		$order   = $this->create_session_order( $session_id, $user_id );
		$session = $this->build_session( $session_id, $order, [ 'customer' => $session_customer_id ] );

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $this->build_gateway(), $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );
		$settlement->process_payment( $order, $session );

		$this->assertSame(
			$session_customer_id,
			WC_Stripe_Order_Helper::get_instance()->get_stripe_customer_id( wc_get_order( $order->get_id() ) )
		);

		if ( $order_has_user ) {
			delete_option( 'wc_stripe_user_customer_lock_' . $user_id );
			$this->assertSame( $expected_user_customer_id, ( new WC_Stripe_Customer( $user_id ) )->get_id() );
		}
	}

	/**
	 * Data provider for test_attaches_session_customer.
	 *
	 * @return array
	 */
	public function provide_customer_attachment_cases(): array {
		return [
			'guest order'                           => [
				'order_has_user'            => false,
				'existing_user_customer_id' => null,
				'expected_user_customer_id' => null,
			],
			'user without a Stripe customer'        => [
				'order_has_user'            => true,
				'existing_user_customer_id' => null,
				'expected_user_customer_id' => 'cus_session_123',
			],
			'user with an existing Stripe customer' => [
				'order_has_user'            => true,
				'existing_user_customer_id' => 'cus_existing_456',
				'expected_user_customer_id' => 'cus_existing_456',
			],
			'user attachment lock held elsewhere'   => [
				'order_has_user'            => true,
				'existing_user_customer_id' => null,
				'expected_user_customer_id' => '',
				'user_lock_held'            => true,
			],
		];
	}

	/**
	 * A malformed session customer must be ignored, not abort settlement.
	 *
	 * @dataProvider provide_malformed_session_customers
	 *
	 * @param mixed $session_customer The `customer` value on the Checkout Session.
	 */
	public function test_ignores_malformed_session_customer( $session_customer ): void {
		$session_id = 'cs_test_malformed_customer';
		$order      = $this->create_session_order( $session_id );
		$session    = $this->build_session( $session_id, $order, [ 'customer' => $session_customer ] );

		$gateway = $this->build_gateway();
		$gateway->expects( $this->once() )->method( 'process_response' );

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $gateway, $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );
		$settlement->process_payment( $order, $session );

		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_stripe_customer_id( wc_get_order( $order->get_id() ) ) );
	}

	/**
	 * Data provider for test_ignores_malformed_session_customer.
	 *
	 * @return array
	 */
	public function provide_malformed_session_customers(): array {
		return [
			'object with array id' => [ (object) [ 'id' => [ 'cus_123' ] ] ],
			'object without id'    => [ (object) [ 'object' => 'customer' ] ],
			'array'                => [ [ 'cus_123' ] ],
			'integer'              => [ 123 ],
			'empty string'         => [ '' ],
		];
	}

	/**
	 * A lock abandoned by a crashed request must not block the attachment forever.
	 */
	public function test_attach_customer_to_user_reclaims_an_abandoned_lock(): void {
		$user_id     = self::factory()->user->create();
		$lock_option = 'wc_stripe_user_customer_lock_' . $user_id;
		add_option( $lock_option, ( time() - 2 * MINUTE_IN_SECONDS ) . ':crashed-request', '', false );

		$this->invoke_attach_customer_to_user( $user_id, 'cus_reclaimed' );

		$this->assertSame( 'cus_reclaimed', ( new WC_Stripe_Customer( $user_id ) )->get_id() );
		$this->assertNull( $this->read_user_customer_lock( $lock_option ), 'The reclaimed lock must be released afterwards.' );
	}

	/**
	 * Two requests can read the same expired lock. Only the one whose compare-and-swap lands
	 * may write the customer, and the loser's release must leave the winner's lock in place.
	 */
	public function test_attach_customer_to_user_loses_a_contested_stale_lock_reclaim(): void {
		global $wpdb;

		$user_id     = self::factory()->user->create();
		$lock_option = 'wc_stripe_user_customer_lock_' . $user_id;
		$rival_owner = time() . ':rival-request';
		add_option( $lock_option, ( time() - 2 * MINUTE_IN_SECONDS ) . ':crashed-request', '', false );

		// The rival reclaims the stale lock after this request has read it but before its swap runs.
		$rival_reclaims = static function ( $query ) use ( &$rival_reclaims, $wpdb, $lock_option, $rival_owner ) {
			if ( is_string( $query ) && 0 === stripos( ltrim( $query ), 'UPDATE' ) && false !== strpos( $query, $lock_option ) ) {
				remove_filter( 'query', $rival_reclaims );
				$wpdb->update( $wpdb->options, [ 'option_value' => $rival_owner ], [ 'option_name' => $lock_option ] );
			}
			return $query;
		};
		add_filter( 'query', $rival_reclaims );

		try {
			$this->invoke_attach_customer_to_user( $user_id, 'cus_loser' );
		} finally {
			remove_filter( 'query', $rival_reclaims );
		}

		$this->assertSame( '', ( new WC_Stripe_Customer( $user_id ) )->get_id(), 'The loser of the reclaim must not write the customer.' );
		$this->assertSame( $rival_owner, $this->read_user_customer_lock( $lock_option ), 'The loser must not release the rival\'s lock.' );

		delete_option( $lock_option );
	}

	/**
	 * Invokes the private user attachment routine on a real settlement instance.
	 *
	 * @param int    $user_id     The WP user.
	 * @param string $customer_id The Stripe customer ID to attach.
	 */
	private function invoke_attach_customer_to_user( int $user_id, string $customer_id ): void {
		$method = new ReflectionMethod( WC_Stripe_Checkout_Session_Settlement::class, 'attach_customer_to_user' );
		$method->setAccessible( true );
		$method->invoke( new WC_Stripe_Checkout_Session_Settlement( new WC_Stripe_Webhook_Handler() ), $user_id, $customer_id );
	}

	/**
	 * Reads the lock row straight from the database; the options cache may hold a stale seed value.
	 *
	 * @param string $lock_option The lock option name.
	 * @return string|null The stored owner value, or null when no lock row exists.
	 */
	private function read_user_customer_lock( string $lock_option ): ?string {
		global $wpdb;

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock_option ) );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Adaptive Pricing orders must get the title of the method the customer actually used, not the gateway's
	 * default "Stripe".
	 *
	 * @dataProvider provide_payment_method_titles
	 *
	 * @param string $payment_method_type Stripe payment method type (e.g. 'card', 'ideal').
	 * @param string $expected_title      Expected WooCommerce payment method title on the order.
	 */
	public function test_sets_payment_method_title( string $payment_method_type, string $expected_title ): void {
		$session_id = 'cs_test_title_' . $payment_method_type;
		$order      = $this->create_session_order( $session_id );

		// A payment method object that mimics a Stripe expanded payment_method field.
		$payment_method = (object) [
			'id'   => 'pm_mock_' . $payment_method_type,
			'type' => $payment_method_type,
		];

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $this->build_gateway( $payment_method ), $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );
		$settlement->process_payment( $order, $this->build_session( $session_id, $order ) );

		$this->assertSame( $expected_title, wc_get_order( $order->get_id() )->get_payment_method_title() );
	}

	/**
	 * Data provider for test_sets_payment_method_title.
	 *
	 * @return array[]
	 */
	public function provide_payment_method_titles(): array {
		return [
			'card payment'   => [ WC_Stripe_Payment_Methods::CARD, 'Credit / Debit Card' ],
			'klarna payment' => [ WC_Stripe_Payment_Methods::KLARNA, 'Klarna' ],
			'ideal payment'  => [ WC_Stripe_Payment_Methods::IDEAL, 'iDEAL | Wero' ],
		];
	}

	/**
	 * Redirect-based APMs that are saved as SEPA tokens (Bancontact, iDEAL, Sofort) must not fatal when the
	 * save-payment-method flag is set.
	 *
	 * Stripe returns the APM PaymentMethod (no `sepa_debit` child). Settlement must convert it to the
	 * charge's `generated_sepa_debit` PaymentMethod before saving, and skip saving entirely when Stripe
	 * did not generate a reusable mandate, instead of passing a non-SEPA object to set_fingerprint().
	 *
	 * @dataProvider provide_apm_sepa_save_cases
	 *
	 * @param string      $payment_method_type  Redirect APM type (e.g. 'bancontact', 'ideal', 'sofort').
	 * @param string|null $generated_sepa_debit The generated SEPA debit PM id on the charge, or null.
	 * @param bool        $expect_token_saved   Whether a SEPA token should be saved for the user.
	 */
	public function test_saves_apm_as_sepa_token( string $payment_method_type, ?string $generated_sepa_debit, bool $expect_token_saved ): void {
		$session_id      = 'cs_test_apm_' . $payment_method_type;
		$generated_pm_id = 'pm_generated_sepa_' . $payment_method_type;

		$user_id = self::factory()->user->create();
		$order   = $this->create_session_order( $session_id, $user_id );

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_should_save_stripe_payment_method( $order, true );
		$order->save_meta_data();

		// The APM PaymentMethod as Stripe returns it: its own type, no sepa_debit child.
		$payment_method = (object) [
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

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $this->build_gateway( $payment_method, $charge ), $this->createMock( WC_Stripe_Action_Scheduler_Service::class ) );
		try {
			// Must not fatal.
			$settlement->process_payment( $order, $this->build_session( $session_id, $order, [ 'payment_intent' => 'pi_test_' . $payment_method_type ] ) );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_filter );
		}

		// The save flag is always cleared so retries do not loop.
		$this->assertFalse( $order_helper->get_should_save_stripe_payment_method( wc_get_order( $order->get_id() ) ) );

		$tokens = WC_Payment_Tokens::get_customer_tokens( $user_id );
		if ( ! $expect_token_saved ) {
			$this->assertCount( 0, $tokens );
			return;
		}

		$this->assertCount( 1, $tokens );
		$token = array_shift( $tokens );
		$this->assertInstanceOf( WC_Payment_Token_SEPA::class, $token );
		$this->assertSame( $generated_pm_id, $token->get_token() );
		$this->assertSame( '7061', $token->get_last4() );
	}

	/**
	 * Data provider for test_saves_apm_as_sepa_token.
	 *
	 * @return array[]
	 */
	public function provide_apm_sepa_save_cases(): array {
		return [
			'bancontact with generated mandate' => [ WC_Stripe_Payment_Methods::BANCONTACT, 'pm_generated_sepa_bancontact', true ],
			'ideal with generated mandate'      => [ WC_Stripe_Payment_Methods::IDEAL, 'pm_generated_sepa_ideal', true ],
			'sofort with generated mandate'     => [ WC_Stripe_Payment_Methods::SOFORT, 'pm_generated_sepa_sofort', true ],
			'bancontact without mandate'        => [ WC_Stripe_Payment_Methods::BANCONTACT, null, false ],
		];
	}

	/**
	 * Settle only an order that is still waiting for payment and still points at this session. Anything
	 * else was settled by another process, or a newer payment attempt replaced the session.
	 *
	 * @dataProvider provide_can_settle_cases
	 *
	 * @param string $status     Order status read under the lock.
	 * @param string $session_id Session the order points at.
	 * @param bool   $expected   Whether the order can be settled from `cs_can_settle`.
	 */
	public function test_can_settle( string $status, string $session_id, bool $expected ): void {
		$order = $this->create_session_order( $session_id );
		$order->set_status( $status );
		$order->save();

		$settlement = new WC_Stripe_Checkout_Session_Settlement( $this->build_gateway() );

		$this->assertSame( $expected, $settlement->can_settle( $order, 'cs_can_settle' ) );
	}

	public function provide_can_settle_cases(): array {
		return [
			'pending, same session'     => [ OrderStatus::PENDING, 'cs_can_settle', true ],
			'already settled'           => [ OrderStatus::PROCESSING, 'cs_can_settle', false ],
			'session replaced on order' => [ OrderStatus::PENDING, 'cs_newer_attempt', false ],
		];
	}
}
