<?php

/**
 * Tests for Stripe pending order reconciliation scheduling and admin actions.
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

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function set_up() {
		parent::set_up();
		$this->original_hpos_setting = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );

		$this->gateway         = $this->getMockBuilder( WC_Gateway_Stripe::class )->disableOriginalConstructor()->getMock();
		$this->webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )->disableOriginalConstructor()->getMock();
		$this->reconciliation  = new WC_Stripe_Order_Reconciliation( $this->gateway, $this->webhook_handler );

		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::SCAN_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		delete_option( WC_Stripe_Order_Reconciliation::PAGE_OPTION );
	}

	public function tear_down() {
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::SCAN_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		delete_option( WC_Stripe_Order_Reconciliation::PAGE_OPTION );
		update_option( 'woocommerce_custom_orders_table_enabled', $this->original_hpos_setting );
		remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		remove_action( 'init', [ $this->reconciliation, 'ensure_scheduled' ], 20 );
		remove_action( WC_Stripe_Order_Reconciliation::SCAN_HOOK, [ $this->reconciliation, 'scan_pending_orders' ] );
		remove_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, [ $this->reconciliation, 'reconcile_order_payment' ] );
		remove_action( 'woocommerce_order_action_' . WC_Stripe_Order_Reconciliation::ORDER_ACTION, [ $this->reconciliation, 'handle_order_action' ] );
		remove_filter( 'woocommerce_order_actions', [ $this->reconciliation, 'add_order_action' ], 10 );
		remove_filter( 'bulk_actions-edit-shop_order', [ $this->reconciliation, 'add_bulk_action' ] );
		remove_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this->reconciliation, 'add_bulk_action' ] );
		remove_filter( 'handle_bulk_actions-edit-shop_order', [ $this->reconciliation, 'handle_bulk_action' ], 10 );
		remove_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this->reconciliation, 'handle_bulk_action' ], 10 );
		remove_action( 'admin_notices', [ $this->reconciliation, 'render_admin_notice' ] );
		parent::tear_down();
	}

	public function test_registers_only_one_recurring_scan() {
		$this->reconciliation->ensure_scheduled();
		$this->reconciliation->ensure_scheduled();

		$actions = as_get_scheduled_actions(
			[
				'hook'   => WC_Stripe_Order_Reconciliation::SCAN_HOOK,
				'group'  => WC_Stripe_Order_Reconciliation::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);

		$this->assertCount( 1, $actions );
		$scheduled_actions = as_get_scheduled_actions(
			[
				'hook'   => WC_Stripe_Order_Reconciliation::SCAN_HOOK,
				'group'  => WC_Stripe_Order_Reconciliation::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'objects'
		);
		$action            = reset( $scheduled_actions );
		$this->assertSame( 5 * MINUTE_IN_SECONDS, $action->get_schedule()->get_recurrence() );
	}

	public function test_unschedule_cancels_only_reconciliation_actions_in_its_group() {
		as_schedule_recurring_action(
			time() + MINUTE_IN_SECONDS,
			5 * MINUTE_IN_SECONDS,
			WC_Stripe_Order_Reconciliation::SCAN_HOOK,
			[],
			WC_Stripe_Order_Reconciliation::GROUP,
			true
		);
		as_schedule_single_action(
			time() + MINUTE_IN_SECONDS,
			WC_Stripe_Order_Reconciliation::ORDER_HOOK,
			[ 'order_id' => 123 ],
			WC_Stripe_Order_Reconciliation::GROUP
		);
		as_schedule_single_action( time() + MINUTE_IN_SECONDS, 'wc_stripe_unrelated_action', [], WC_Stripe_Order_Reconciliation::GROUP );

		WC_Stripe_Order_Reconciliation::unschedule();

		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Order_Reconciliation::SCAN_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP ) );
		$this->assertFalse( as_has_scheduled_action( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP ) );
		$this->assertNotFalse( as_has_scheduled_action( 'wc_stripe_unrelated_action', null, WC_Stripe_Order_Reconciliation::GROUP ) );
	}

	/**
	 * @dataProvider provide_order_storage_modes
	 */
	public function test_scan_enqueues_only_recent_eligible_orders_across_storage_modes( string $storage_mode ) {
		$this->set_storage_mode( $storage_mode );
		$valid        = $this->create_order( 'stripe', 'pi_valid', 20 * MINUTE_IN_SECONDS );
		$not_stripe   = $this->create_order( 'cod', 'pi_cod', 20 * MINUTE_IN_SECONDS );
		$no_ids       = $this->create_order( 'stripe', '', 20 * MINUTE_IN_SECONDS );
		$cooling_down = $this->create_order( 'stripe', 'pi_cooldown', 20 * MINUTE_IN_SECONDS );
		$too_recent   = $this->create_order( 'stripe', 'pi_recent', 5 * MINUTE_IN_SECONDS );
		$too_old      = $this->create_order( 'stripe', 'pi_old', WC_Stripe_Order_Reconciliation::LOOKBACK + DAY_IN_SECONDS );
		$cooling_down->update_meta_data( WC_Stripe_Order_Reconciliation::ATTEMPT_META, time() );
		$cooling_down->save_meta_data();

		$this->reconciliation->scan_pending_orders();

		$queued_ids = $this->queued_order_ids();
		$this->assertSame( [ $valid->get_id() ], $queued_ids );
		$this->assertNotContains( $not_stripe->get_id(), $queued_ids );
		$this->assertNotContains( $no_ids->get_id(), $queued_ids );
		$this->assertNotContains( $cooling_down->get_id(), $queued_ids );
		$this->assertNotContains( $too_recent->get_id(), $queued_ids );
		$this->assertNotContains( $too_old->get_id(), $queued_ids );
	}

	public function test_scan_advances_past_cooling_orders_and_revisits_first_page_after_wrap() {
		$first_page = [];
		for ( $index = 0; $index < WC_Stripe_Order_Reconciliation::PAGE_SIZE; $index++ ) {
			$order = $this->create_order( 'stripe', 'pi_cooldown_' . $index, 20 * MINUTE_IN_SECONDS );
			$order->update_meta_data( WC_Stripe_Order_Reconciliation::ATTEMPT_META, time() );
			$order->save_meta_data();
			$first_page[] = $order;
		}
		$later_order = $this->create_order( 'stripe', 'pi_later_page', 20 * MINUTE_IN_SECONDS );

		$this->reconciliation->scan_pending_orders();
		$this->assertSame( 2, (int) get_option( WC_Stripe_Order_Reconciliation::PAGE_OPTION ) );
		$this->assertSame( [], $this->queued_order_ids() );

		$this->reconciliation->scan_pending_orders();
		$this->assertSame( 1, (int) get_option( WC_Stripe_Order_Reconciliation::PAGE_OPTION ) );
		$this->assertSame( [ $later_order->get_id() ], $this->queued_order_ids() );

		$first_page[0]->delete_meta_data( WC_Stripe_Order_Reconciliation::ATTEMPT_META );
		$first_page[0]->save_meta_data();
		$this->reconciliation->scan_pending_orders();
		$this->assertContains( $first_page[0]->get_id(), $this->queued_order_ids() );
	}

	/**
	 * @dataProvider provide_order_storage_modes
	 */
	public function test_order_action_is_added_only_for_authorized_eligible_orders( string $storage_mode ) {
		$this->set_storage_mode( $storage_mode );
		$order      = $this->create_order( 'stripe', 'pi_admin', 20 * MINUTE_IN_SECONDS );
		$actions    = $this->reconciliation->add_order_action( [], $order );
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->assertSame( 'Re-check payment status with Stripe', $actions[ WC_Stripe_Order_Reconciliation::ORDER_ACTION ] );
		wp_set_current_user( $subscriber );
		$this->assertSame( [], $this->reconciliation->add_order_action( [], $order ) );
		wp_set_current_user( $this->admin_user_id );

		$non_stripe = $this->create_order( 'cod', 'pi_cod_admin', 20 * MINUTE_IN_SECONDS );
		$this->assertSame( [], $this->reconciliation->add_order_action( [], $non_stripe ) );
	}

	/**
	 * @dataProvider provide_order_storage_modes
	 */
	public function test_bulk_action_queues_only_eligible_orders_without_making_http_requests( string $storage_mode ) {
		$this->set_storage_mode( $storage_mode );
		$eligible    = $this->create_order( 'stripe', 'pi_bulk', 20 * MINUTE_IN_SECONDS );
		$forbidden   = $this->create_order( 'stripe', 'pi_bulk_forbidden', 20 * MINUTE_IN_SECONDS );
		$non_stripe  = $this->create_order( 'cod', 'pi_bulk_cod', 20 * MINUTE_IN_SECONDS );
		$http_calls  = 0;
		$map_caps    = static function ( $caps, $cap, $user_id, $args ) use ( $forbidden ) {
			if ( 'edit_post' === $cap && $forbidden->get_id() === absint( $args[0] ?? 0 ) ) {
				return [ 'do_not_allow' ];
			}
			return $caps;
		};
		$http_filter = static function () use ( &$http_calls ) {
			++$http_calls;
			return new WP_Error( 'unexpected_http', 'Bulk scheduling must not make network requests.' );
		};
		add_filter( 'map_meta_cap', $map_caps, 10, 4 );
		add_filter( 'pre_http_request', $http_filter, 10, 3 );

		$redirect = $this->reconciliation->handle_bulk_action(
			'http://example.org/wp-admin/admin.php?page=wc-orders',
			WC_Stripe_Order_Reconciliation::ORDER_ACTION,
			[ $eligible->get_id(), $forbidden->get_id(), $non_stripe->get_id(), 999999 ]
		);

		remove_filter( 'map_meta_cap', $map_caps, 10 );
		remove_filter( 'pre_http_request', $http_filter, 10 );
		$this->assertSame( 0, $http_calls );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query_args );
		$this->assertSame( 1, (int) $query_args[ WC_Stripe_Order_Reconciliation::NOTICE_QUERY_ARG ] );
		$this->assertSame( [ $eligible->get_id() ], $this->queued_order_ids() );

		$duplicate_redirect = $this->reconciliation->handle_bulk_action(
			'http://example.org/wp-admin/admin.php?page=wc-orders',
			WC_Stripe_Order_Reconciliation::ORDER_ACTION,
			[ $eligible->get_id() ]
		);
		parse_str( (string) wp_parse_url( $duplicate_redirect, PHP_URL_QUERY ), $duplicate_query_args );
		$this->assertSame( 0, (int) $duplicate_query_args[ WC_Stripe_Order_Reconciliation::NOTICE_QUERY_ARG ] );
	}

	public function test_scan_enqueues_at_most_one_batch_of_orders() {
		for ( $index = 0; $index < WC_Stripe_Order_Reconciliation::PAGE_SIZE + 1; $index++ ) {
			$this->create_order( 'stripe', 'pi_batch_' . $index, 20 * MINUTE_IN_SECONDS );
		}

		$this->reconciliation->scan_pending_orders();

		$this->assertCount( WC_Stripe_Order_Reconciliation::PAGE_SIZE, $this->queued_order_ids() );
		$this->assertSame( 2, (int) get_option( WC_Stripe_Order_Reconciliation::PAGE_OPTION ) );
	}

	public function test_enqueue_is_unique_per_order() {
		$order = $this->create_order( 'stripe', 'pi_unique', 20 * MINUTE_IN_SECONDS );

		$this->assertTrue( $this->reconciliation->enqueue_order( $order->get_id() ) );
		$this->assertFalse( $this->reconciliation->enqueue_order( $order->get_id() ) );
		$this->assertSame( [ $order->get_id() ], $this->queued_order_ids() );
	}

	/** @dataProvider provide_cooldown_attempt_ages */
	public function test_enqueue_respects_five_minute_per_order_cooldown( int $attempt_age, bool $expected_to_queue ): void {
		$order = $this->create_order( 'stripe', 'pi_cooldown_boundary', 20 * MINUTE_IN_SECONDS );
		$order->update_meta_data( WC_Stripe_Order_Reconciliation::ATTEMPT_META, (string) ( time() - $attempt_age ) );
		$order->save_meta_data();

		$this->assertSame( $expected_to_queue, $this->reconciliation->enqueue_order( $order->get_id() ) );
		$this->assertSame( $expected_to_queue ? [ $order->get_id() ] : [], $this->queued_order_ids() );
	}

	public static function provide_cooldown_attempt_ages(): array {
		return [
			'four minutes ago remains in cooldown' => [ 4 * MINUTE_IN_SECONDS, false ],
			'six minutes ago can be retried'       => [ 6 * MINUTE_IN_SECONDS, true ],
		];
	}

	public function test_single_action_adds_a_note_only_when_an_action_is_queued() {
		$order = $this->create_order( 'stripe', 'pi_single', 20 * MINUTE_IN_SECONDS );
		$this->reconciliation->handle_order_action( $order );
		$notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'type'     => 'internal',
			]
		);
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( 'A Stripe payment status check has been scheduled.', $notes[0]->content );

		$before = count(
			wc_get_order_notes(
				[
					'order_id' => $order->get_id(),
					'type'     => 'internal',
				]
			)
		);
		$filter = static function () {
			return 0;
		};
		add_filter( 'pre_as_enqueue_async_action', $filter, 10, 6 );
		$this->reconciliation->handle_order_action( $order );
		remove_filter( 'pre_as_enqueue_async_action', $filter, 10 );
		$this->assertCount(
			$before,
			wc_get_order_notes(
				[
					'order_id' => $order->get_id(),
					'type'     => 'internal',
				]
			)
		);
	}

	public function test_frontend_hook_registration_does_not_add_admin_actions() {
		$this->reconciliation->register_hooks();
		$this->assertFalse( has_action( 'woocommerce_order_action_' . WC_Stripe_Order_Reconciliation::ORDER_ACTION ) );
		$this->assertFalse( has_filter( 'bulk_actions-edit-shop_order' ) );
		$this->assertFalse( has_filter( 'bulk_actions-woocommerce_page_wc-orders' ) );
	}

	public function test_admin_hook_registration_adds_both_order_list_screens() {
		$original_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		set_current_screen( 'edit-shop_order' );

		try {
			$this->reconciliation->register_hooks();

			$this->assertSame( 10, has_filter( 'bulk_actions-edit-shop_order', [ $this->reconciliation, 'add_bulk_action' ] ) );
			$this->assertSame( 10, has_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this->reconciliation, 'add_bulk_action' ] ) );
			$this->assertSame( 10, has_filter( 'handle_bulk_actions-edit-shop_order', [ $this->reconciliation, 'handle_bulk_action' ] ) );
			$this->assertSame( 10, has_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this->reconciliation, 'handle_bulk_action' ] ) );
		} finally {
			if ( null === $original_screen ) {
				unset( $GLOBALS['current_screen'] );
			} else {
				$GLOBALS['current_screen'] = $original_screen;
			}
		}
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
