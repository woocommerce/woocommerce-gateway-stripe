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
		remove_action( 'woocommerce_checkout_order_processed', [ $this->reconciliation, 'schedule_order_check' ] );
		remove_action( 'woocommerce_store_api_checkout_order_processed', [ $this->reconciliation, 'schedule_order_check' ] );
		remove_action( 'woocommerce_before_pay_action', [ $this->reconciliation, 'schedule_order_check' ] );
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
		$stripe_order = $this->create_order( 'stripe', '', 0 );
		$cod_order    = $this->create_order( 'cod', '', 0 );

		$this->reconciliation->schedule_order_check( $stripe_order );
		$this->reconciliation->schedule_order_check( $cod_order );

		$this->assertSame( [ $stripe_order->get_id() ], $this->queued_order_ids() );
		$next = as_next_scheduled_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ 'order_id' => $stripe_order->get_id() ], WC_Stripe_Order_Reconciliation::GROUP );
		$this->assertEqualsWithDelta( time() + WC_Stripe_Order_Reconciliation::CHECK_DELAY, $next, 5 );
	}

	public function test_scheduled_check_accepts_an_order_id_and_is_unique_per_order() {
		$order = $this->create_order( 'stripe', '', 0 );

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

	public function test_hook_registration_schedules_checks_from_checkout_hooks() {
		$this->reconciliation->register_hooks();

		$this->assertSame( 10, has_action( 'woocommerce_checkout_order_processed', [ $this->reconciliation, 'schedule_order_check' ] ) );
		$this->assertSame( 10, has_action( 'woocommerce_store_api_checkout_order_processed', [ $this->reconciliation, 'schedule_order_check' ] ) );
		$this->assertSame( 10, has_action( 'woocommerce_before_pay_action', [ $this->reconciliation, 'schedule_order_check' ] ) );
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

	public function test_new_payment_attempt_resets_the_retry_budget() {
		$order = $this->create_order( 'stripe', 'pi_reset', 0 );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::ATTEMPTS_META, '4' );
		$order->save_meta_data();

		$this->reconciliation->schedule_order_check( $order->get_id() );

		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( WC_Stripe_Order_Reconciliation::ATTEMPTS_META, true ) );
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
