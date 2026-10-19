<?php

/**
 * Tests for scheduling Stripe pending order reconciliation checks.
 *
 * @package WooCommerce/Stripe
 */
class WC_Stripe_Order_Reconciliation_Scheduler_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Order_Reconciliation
	 */
	private $reconciliation;

	/**
	 * @var WC_Stripe_Payment_Gateway
	 */
	private $gateway;

	/**
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $webhook_handler;

	/**
	 * @var mixed
	 */
	private $original_hpos_setting;

	public function set_up() {
		parent::set_up();
		$this->original_hpos_setting = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );

		$this->gateway         = $this->getMockBuilder( WC_Gateway_Stripe::class )->disableOriginalConstructor()->getMock();
		$this->webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )->disableOriginalConstructor()->getMock();
		$this->reconciliation  = new WC_Stripe_Order_Reconciliation( $this->gateway, $this->webhook_handler );

		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
	}

	public function tear_down() {
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		update_option( 'woocommerce_custom_orders_table_enabled', $this->original_hpos_setting );
		remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		remove_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ $this->reconciliation, 'reconcile_order_payment' ] );
		remove_filter( 'woocommerce_payment_successful_result', [ $this->reconciliation, 'schedule_after_classic_payment' ], 10 );
		remove_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this->reconciliation, 'schedule_after_store_api_payment' ], 10001 );
		remove_action( 'wc_gateway_stripe_process_payment_subscription_charge_attempt_delayed', [ $this->reconciliation, 'schedule_after_delayed_renewal_charge' ], 10 );
		parent::tear_down();
	}

	public function test_unschedule_cancels_only_reconciliation_actions_in_its_group() {
		as_schedule_single_action(
			time() + MINUTE_IN_SECONDS,
			WC_Stripe_Order_Reconciliation::ORDER_HOOK,
			[ 'order_id' => 123 ],
			WC_Stripe_Order_Reconciliation::GROUP
		);
		as_schedule_single_action( time() + MINUTE_IN_SECONDS, 'wc_stripe_unrelated_action', [], WC_Stripe_Order_Reconciliation::GROUP );

		WC_Stripe_Order_Reconciliation::unschedule();

		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP ) );
		$this->assertNotFalse( as_has_scheduled_action( 'wc_stripe_unrelated_action', null, WC_Stripe_Order_Reconciliation::GROUP ) );
	}

	/**
	 * @dataProvider provide_order_storage_modes
	 */
	public function test_schedules_a_delayed_check_only_for_stripe_orders( string $storage_mode ) {
		$this->set_storage_mode( $storage_mode );
		$stripe_order = $this->create_order( 'stripe', 'pi_scheduled', 0 );
		$cod_order    = $this->create_order( 'cod', 'pi_cod', 0 );

		$this->reconciliation->schedule_order_check( $stripe_order );
		$this->reconciliation->schedule_order_check( $cod_order );

		$this->assertSame( [ $stripe_order->get_id() ], $this->queued_order_ids() );
		$next = as_next_scheduled_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ 'order_id' => $stripe_order->get_id() ], WC_Stripe_Order_Reconciliation::GROUP );
		$this->assertEqualsWithDelta( time() + WC_Stripe_Order_Reconciliation::FIRST_CHECK_DELAY, $next, 5 );
	}

	public function test_scheduled_check_accepts_an_order_id_and_is_unique_per_order() {
		$order = $this->create_order( 'stripe', 'pi_unique_check', 0 );

		$this->reconciliation->schedule_order_check( $order->get_id() );
		$this->reconciliation->schedule_order_check( $order );

		$this->assertSame( [ $order->get_id() ], $this->queued_order_ids() );
	}

	/** @dataProvider provide_invalid_order_references */
	public function test_invalid_order_references_never_fall_back_to_the_current_admin_order( $reference ) {
		global $theorder;
		$order    = $this->create_order( 'stripe', 'pi_global', 0 );
		$theorder = $order; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the order edit screen.

		try {
			$this->reconciliation->schedule_order_check( $reference );
			$this->assertFalse( $this->reconciliation->enqueue_order( $reference ) );
		} finally {
			$theorder = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the global set above.
		}

		$this->assertSame( [], $this->queued_order_ids() );
	}

	public static function provide_invalid_order_references(): array {
		return [
			'false'        => [ false ],
			'zero'         => [ 0 ],
			'empty string' => [ '' ],
			'array'        => [ [ 'order_id' => 1 ] ],
		];
	}

	public function test_hook_registration_schedules_checks_after_payment_processing() {
		$this->reconciliation->register_hooks();

		$this->assertSame( 10, has_filter( 'woocommerce_payment_successful_result', [ $this->reconciliation, 'schedule_after_classic_payment' ] ) );
		$this->assertSame( 10001, has_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this->reconciliation, 'schedule_after_store_api_payment' ] ) );
		$this->assertSame( 10, has_action( 'wc_gateway_stripe_process_payment_subscription_charge_attempt_delayed', [ $this->reconciliation, 'schedule_after_delayed_renewal_charge' ] ) );
	}

	/** @dataProvider provide_known_payment_types */
	public function test_first_check_waits_a_day_when_the_payment_method_confirms_days_later( string $payment_type, int $expected_delay ) {
		$order = $this->create_order_in_state( 'pending_intent' );
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $payment_type );
		$order->save_meta_data();

		$this->reconciliation->schedule_order_check( $order );

		$this->assertEqualsWithDelta( time() + $expected_delay, $this->next_check_for( $order ), 5 );
	}

	public static function provide_known_payment_types(): array {
		return [
			'card'   => [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Order_Reconciliation::FIRST_CHECK_DELAY ],
			'Boleto' => [ WC_Stripe_Payment_Methods::BOLETO, WC_Stripe_Order_Reconciliation::DELAYED_CHECK_INTERVAL ],
			'ACH'    => [ WC_Stripe_Payment_Methods::ACH, WC_Stripe_Order_Reconciliation::DELAYED_CHECK_INTERVAL ],
		];
	}

	public function test_delayed_renewal_is_checked_after_stripe_attempts_the_charge() {
		$order        = $this->create_order_in_state( 'pending_intent' );
		$completes_at = time() + 2 * DAY_IN_SECONDS;
		$response     = (object) [
			'status'     => WC_Stripe_Intent_Status::PROCESSING,
			'processing' => (object) [
				'card' => (object) [
					'customer_notification' => (object) [ 'completes_at' => $completes_at ],
				],
			],
		];

		$this->reconciliation->schedule_after_delayed_renewal_charge( $response, $order );

		$this->assertSame( (string) $completes_at, wc_get_order( $order->get_id() )->get_meta( WC_Stripe_Order_Reconciliation::STARTED_META, true ) );
		$this->assertEqualsWithDelta( $completes_at + WC_Stripe_Order_Reconciliation::FIRST_CHECK_DELAY, $this->next_check_for( $order ), 5 );
	}

	public function test_delayed_renewal_without_a_charge_date_starts_now() {
		$order = $this->create_order_in_state( 'pending_intent' );

		$this->reconciliation->schedule_after_delayed_renewal_charge( (object) [], $order );

		$this->assertEqualsWithDelta( time() + WC_Stripe_Order_Reconciliation::FIRST_CHECK_DELAY, $this->next_check_for( $order ), 5 );
	}

	public function test_delayed_renewal_that_is_not_waiting_for_stripe_is_not_scheduled() {
		$order = $this->create_order_in_state( 'pending_no_ids' );

		$this->reconciliation->schedule_after_delayed_renewal_charge( (object) [], $order );
		$this->reconciliation->schedule_after_delayed_renewal_charge( (object) [], null );

		$this->assertSame( [], $this->queued_order_ids() );
	}

	/** @dataProvider provide_orders_after_payment */
	public function test_classic_payment_schedules_a_check_only_for_orders_waiting_for_stripe( string $scenario, bool $expect_check ) {
		$order = $this->create_order_in_state( $scenario );
		$input = [
			'result'   => 'success',
			'redirect' => 'https://example.org/checkout/order-received/',
		];

		$output = $this->reconciliation->schedule_after_classic_payment( $input, $order->get_id() );

		$this->assertSame( $input, $output );
		$this->assertSame( $expect_check ? [ $order->get_id() ] : [], $this->queued_order_ids() );
	}

	/** @dataProvider provide_orders_after_payment */
	public function test_store_api_payment_schedules_a_check_only_for_orders_waiting_for_stripe( string $scenario, bool $expect_check ) {
		$order   = $this->create_order_in_state( $scenario );
		$context = new \Automattic\WooCommerce\StoreApi\Payments\PaymentContext();
		$context->set_order( $order );

		$this->reconciliation->schedule_after_store_api_payment( $context, new \Automattic\WooCommerce\StoreApi\Payments\PaymentResult( 'success' ) );

		$this->assertSame( $expect_check ? [ $order->get_id() ] : [], $this->queued_order_ids() );
	}

	public function test_store_api_payment_reads_the_order_the_gateway_updated() {
		$order = $this->create_order( 'stripe', '', 0 );
		// The Store API context holds the order as loaded before payment; the gateway updates its own copy.
		$context_copy = wc_get_order( $order->get_id() );
		$gateway_copy = wc_get_order( $order->get_id() );
		$gateway_copy->update_meta_data( '_stripe_intent_id', 'pi_saved_by_gateway' );
		$gateway_copy->save();
		$context = new \Automattic\WooCommerce\StoreApi\Payments\PaymentContext();
		$context->set_order( $context_copy );

		$this->reconciliation->schedule_after_store_api_payment( $context, new \Automattic\WooCommerce\StoreApi\Payments\PaymentResult( 'success' ) );

		$this->assertSame( [ $order->get_id() ], $this->queued_order_ids() );
	}

	public function test_store_api_payment_skips_an_order_the_gateway_already_paid() {
		$order        = $this->create_order( 'stripe', 'pi_paid_by_gateway', 0 );
		$context_copy = wc_get_order( $order->get_id() );
		$gateway_copy = wc_get_order( $order->get_id() );
		$gateway_copy->set_status( 'processing' );
		$gateway_copy->save();
		$context = new \Automattic\WooCommerce\StoreApi\Payments\PaymentContext();
		$context->set_order( $context_copy );

		$this->reconciliation->schedule_after_store_api_payment( $context, new \Automattic\WooCommerce\StoreApi\Payments\PaymentResult( 'success' ) );

		$this->assertSame( [], $this->queued_order_ids() );
	}

	public static function provide_orders_after_payment(): array {
		return [
			'pending with an intent (redirect or 3DS)' => [ 'pending_intent', true ],
			'pending with a Checkout Session'          => [ 'pending_session', true ],
			'paid during the request'                  => [ 'processing', false ],
			'on hold'                                  => [ 'on_hold', false ],
			'pending but already paid'                 => [ 'pending_paid', false ],
			'pending without Stripe IDs'               => [ 'pending_no_ids', false ],
			'paid with another gateway'                => [ 'other_gateway', false ],
		];
	}

	/** @dataProvider provide_unsuccessful_payment_results */
	public function test_unsuccessful_payments_schedule_nothing( string $status ) {
		$order   = $this->create_order_in_state( 'pending_intent' );
		$context = new \Automattic\WooCommerce\StoreApi\Payments\PaymentContext();
		$context->set_order( $order );

		$this->reconciliation->schedule_after_classic_payment( [ 'result' => $status ], $order->get_id() );
		$this->reconciliation->schedule_after_store_api_payment( $context, new \Automattic\WooCommerce\StoreApi\Payments\PaymentResult( $status ) );

		$this->assertSame( [], $this->queued_order_ids() );
	}

	public static function provide_unsuccessful_payment_results(): array {
		return [
			'failure' => [ 'failure' ],
			'error'   => [ 'error' ],
			'pending' => [ 'pending' ],
			'empty'   => [ '' ],
		];
	}

	public function test_unexpected_hook_arguments_are_ignored() {
		$this->assertNull( $this->reconciliation->schedule_after_classic_payment( null, null ) );
		$this->reconciliation->schedule_after_store_api_payment( null, 'success' );

		$this->assertSame( [], $this->queued_order_ids() );
	}

	public function test_scheduling_failure_never_breaks_checkout() {
		$order  = $this->create_order_in_state( 'pending_intent' );
		$filter = static function () {
			throw new RuntimeException( 'Action Scheduler is unavailable.' );
		};
		add_filter( 'pre_as_schedule_single_action', $filter );

		try {
			$result = $this->reconciliation->schedule_after_classic_payment( [ 'result' => 'success' ], $order->get_id() );
		} finally {
			remove_filter( 'pre_as_schedule_single_action', $filter );
		}

		$this->assertSame( [ 'result' => 'success' ], $result );
		$this->assertSame( [], $this->queued_order_ids() );
	}

	public function test_subscriptions_are_not_checked() {
		$subscription = new class() extends WC_Order {
			public function get_type() {
				return 'shop_subscription';
			}
		};
		$subscription->set_payment_method( 'stripe' );
		$subscription->update_meta_data( '_stripe_intent_id', 'pi_subscription' );

		$this->assertFalse( $this->reconciliation->is_eligible_order( $subscription ) );
	}

	public function test_checks_of_different_orders_never_rely_on_action_schedulers_unique_flag() {
		$first  = $this->create_order( 'stripe', 'pi_first', 0 );
		$second = $this->create_order( 'stripe', 'pi_second', 0 );
		$unique = [];
		$record = static function ( $pre, ...$args ) use ( &$unique ) {
			$unique[] = end( $args );
			return $pre;
		};
		add_filter( 'pre_as_schedule_single_action', $record, 10, 7 );
		add_filter( 'pre_as_enqueue_async_action', $record, 10, 6 );

		try {
			$this->reconciliation->schedule_order_check( $first );
			$this->reconciliation->schedule_order_check( $second );
			$this->reconciliation->enqueue_order( $first );
			$this->reconciliation->enqueue_order( $second );
		} finally {
			remove_filter( 'pre_as_schedule_single_action', $record, 10 );
			remove_filter( 'pre_as_enqueue_async_action', $record, 10 );
		}

		// Before Action Scheduler 4.0.0, a `unique` action was unique per hook and group only.
		$this->assertSame( [ false, false, false, false ], $unique );
		$this->assertSame( [ $first->get_id(), $first->get_id(), $second->get_id(), $second->get_id() ], $this->queued_order_ids() );
	}

	public function test_enqueue_is_unique_per_order() {
		$order = $this->create_order( 'stripe', 'pi_unique', 20 * MINUTE_IN_SECONDS );

		$this->assertTrue( $this->reconciliation->enqueue_order( $order->get_id() ) );
		$this->assertFalse( $this->reconciliation->enqueue_order( $order->get_id() ) );
		$this->assertSame( [ $order->get_id() ], $this->queued_order_ids() );
	}

	public function test_manual_check_is_queued_while_a_scheduled_check_is_pending() {
		$order = $this->create_order( 'stripe', 'pi_manual', 0 );
		$this->reconciliation->schedule_order_check( $order );

		$this->assertTrue( $this->reconciliation->enqueue_order( $order->get_id() ) );
		$this->assertNotFalse(
			as_next_scheduled_action(
				WC_Stripe_Order_Reconciliation::ORDER_HOOK,
				[
					'order_id' => $order->get_id(),
					'manual'   => true,
				],
				WC_Stripe_Order_Reconciliation::GROUP
			)
		);
		$this->assertNotFalse( as_next_scheduled_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ 'order_id' => $order->get_id() ], WC_Stripe_Order_Reconciliation::GROUP ) );
	}

	public function test_new_payment_attempt_starts_the_checks_over() {
		$order = $this->create_order( 'stripe', 'pi_reset', 0 );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::STARTED_META, (string) ( time() - 3 * DAY_IN_SECONDS ) );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::DELAYED_META, 'yes' );
		$order->save_meta_data();

		$this->reconciliation->schedule_order_check( $order->get_id() );

		$reloaded = wc_get_order( $order->get_id() );
		$this->assertEqualsWithDelta( time(), (int) $reloaded->get_meta( WC_Stripe_Order_Reconciliation::STARTED_META, true ), 5 );
		$this->assertSame( '', $reloaded->get_meta( WC_Stripe_Order_Reconciliation::DELAYED_META, true ) );
	}

	public function test_nothing_is_scheduled_or_queued_while_the_feature_is_off() {
		$order = $this->create_order( 'stripe', 'pi_flag_off', 0 );
		update_option( WC_Stripe_Feature_Flags::ORDER_RECONCILIATION_FEATURE_FLAG_NAME, 'no' );

		try {
			$this->reconciliation->schedule_order_check( $order );
			$this->assertFalse( $this->reconciliation->enqueue_order( $order ) );
		} finally {
			delete_option( WC_Stripe_Feature_Flags::ORDER_RECONCILIATION_FEATURE_FLAG_NAME );
		}

		$this->assertSame( [], $this->queued_order_ids() );
	}

	public function test_queued_check_completes_without_errors_after_the_feature_is_turned_off() {
		$order = $this->create_order( 'stripe', 'pi_flag_off_queue', 0 );
		$this->reconciliation->schedule_order_check( $order );
		$action_ids = as_get_scheduled_actions(
			[
				'hook'   => WC_Stripe_Order_Reconciliation::ORDER_HOOK,
				'args'   => [ 'order_id' => $order->get_id() ],
				'group'  => WC_Stripe_Order_Reconciliation::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		$this->assertCount( 1, $action_ids );
		$action_id = (int) reset( $action_ids );

		update_option( WC_Stripe_Feature_Flags::ORDER_RECONCILIATION_FEATURE_FLAG_NAME, 'no' );
		try {
			$this->reconciliation->register_hooks();
			$this->assertSame( 10, has_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ $this->reconciliation, 'reconcile_order_payment' ] ) );

			ActionScheduler::runner()->process_action( $action_id, 'PHPUnit' );
		} finally {
			delete_option( WC_Stripe_Feature_Flags::ORDER_RECONCILIATION_FEATURE_FLAG_NAME );
		}

		$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, ActionScheduler::store()->get_status( $action_id ) );
		$this->assertSame( [], $this->queued_order_ids() );
	}

	public function test_hook_registration_adds_no_admin_hooks() {
		$this->reconciliation->register_hooks();

		$this->assertFalse( has_action( 'woocommerce_order_action_wc_stripe_recheck_payment' ) );
		$this->assertFalse( has_filter( 'bulk_actions-edit-shop_order' ) );
		$this->assertFalse( has_filter( 'bulk_actions-woocommerce_page_wc-orders' ) );
	}

	public function provide_order_storage_modes(): array {
		return [
			'legacy CPT' => [ 'no' ],
			'HPOS'       => [ 'yes' ],
		];
	}

	private function set_storage_mode( string $storage_mode ) {
		update_option( 'woocommerce_custom_orders_table_enabled', $storage_mode );
		$this->assertSame( 'yes' === $storage_mode, WC_Stripe_Woo_Compat_Utils::is_custom_orders_table_enabled() );
	}

	private function create_order( string $payment_method, string $intent_id, int $age_seconds ): WC_Order {
		$order = wc_create_order( [ 'status' => 'pending' ] );
		$order->set_payment_method( $payment_method );
		$order->set_date_created( time() - $age_seconds );
		if ( '' !== $intent_id ) {
			$order->update_meta_data( '_stripe_intent_id', $intent_id );
		}
		$order->save();
		return $order;
	}

	private function create_order_in_state( string $scenario ): WC_Order {
		$order = $this->create_order( 'other_gateway' === $scenario ? 'cod' : 'stripe', 'pending_no_ids' === $scenario || 'pending_session' === $scenario ? '' : 'pi_after_payment', 0 );
		switch ( $scenario ) {
			case 'pending_session':
				$order->update_meta_data( '_stripe_checkout_session_id', 'cs_after_payment' );
				break;
			case 'processing':
				$order->set_status( 'processing' );
				break;
			case 'on_hold':
				$order->set_status( 'on-hold' );
				break;
			case 'pending_paid':
				$order->set_date_paid( time() );
				break;
		}
		$order->save();
		return $order;
	}

	private function next_check_for( WC_Order $order ): int {
		$next = as_next_scheduled_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ 'order_id' => $order->get_id() ], WC_Stripe_Order_Reconciliation::GROUP );
		$this->assertIsInt( $next );
		return $next;
	}

	private function queued_order_ids(): array {
		$actions   = as_get_scheduled_actions(
			[
				'hook'     => WC_Stripe_Order_Reconciliation::ORDER_HOOK,
				'group'    => WC_Stripe_Order_Reconciliation::GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => -1,
			],
			'objects'
		);
		$order_ids = [];
		foreach ( $actions as $action ) {
			$args        = $action->get_args();
			$order_ids[] = absint( $args['order_id'] ?? 0 );
		}
		sort( $order_ids );
		return $order_ids;
	}
}
