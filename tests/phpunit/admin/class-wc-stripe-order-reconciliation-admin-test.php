<?php

/**
 * Tests for the Stripe payment status re-check order actions.
 *
 * @package WooCommerce/Stripe
 */
class WC_Stripe_Order_Reconciliation_Admin_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Order_Reconciliation_Admin
	 */
	private $admin;

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

		$gateway         = $this->getMockBuilder( WC_Gateway_Stripe::class )->disableOriginalConstructor()->getMock();
		$webhook_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )->disableOriginalConstructor()->getMock();
		$this->admin     = new WC_Stripe_Order_Reconciliation_Admin( new WC_Stripe_Order_Reconciliation( $gateway, $webhook_handler ) );

		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
	}

	public function tear_down() {
		as_unschedule_all_actions( WC_Stripe_Order_Reconciliation::ORDER_HOOK, null, WC_Stripe_Order_Reconciliation::GROUP );
		update_option( 'woocommerce_custom_orders_table_enabled', $this->original_hpos_setting );
		remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
		remove_action( 'woocommerce_order_action_' . WC_Stripe_Order_Reconciliation_Admin::ORDER_ACTION, [ $this->admin, 'handle_order_action' ] );
		remove_filter( 'woocommerce_order_actions', [ $this->admin, 'add_order_action' ], 10 );
		remove_filter( 'bulk_actions-edit-shop_order', [ $this->admin, 'add_bulk_action' ] );
		remove_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this->admin, 'add_bulk_action' ] );
		remove_filter( 'handle_bulk_actions-edit-shop_order', [ $this->admin, 'handle_bulk_action' ], 10 );
		remove_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this->admin, 'handle_bulk_action' ], 10 );
		remove_action( 'admin_notices', [ $this->admin, 'render_admin_notice' ] );
		parent::tear_down();
	}

	/**
	 * @dataProvider provide_order_storage_modes
	 */
	public function test_order_action_is_added_only_for_authorized_eligible_orders( string $storage_mode ) {
		$this->set_storage_mode( $storage_mode );
		$order      = $this->create_order( 'stripe', 'pi_admin' );
		$actions    = $this->admin->add_order_action( [], $order );
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->assertSame( 'Re-check payment status with Stripe', $actions[ WC_Stripe_Order_Reconciliation_Admin::ORDER_ACTION ] );
		wp_set_current_user( $subscriber );
		$this->assertSame( [], $this->admin->add_order_action( [], $order ) );
		wp_set_current_user( $this->admin_user_id );

		$non_stripe = $this->create_order( 'cod', 'pi_cod_admin' );
		$this->assertSame( [], $this->admin->add_order_action( [], $non_stripe ) );
	}

	/**
	 * @dataProvider provide_order_storage_modes
	 */
	public function test_bulk_action_queues_only_eligible_orders_without_making_http_requests( string $storage_mode ) {
		$this->set_storage_mode( $storage_mode );
		$eligible    = $this->create_order( 'stripe', 'pi_bulk' );
		$forbidden   = $this->create_order( 'stripe', 'pi_bulk_forbidden' );
		$non_stripe  = $this->create_order( 'cod', 'pi_bulk_cod' );
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

		$redirect = $this->admin->handle_bulk_action(
			'http://example.org/wp-admin/admin.php?page=wc-orders',
			WC_Stripe_Order_Reconciliation_Admin::ORDER_ACTION,
			[ $eligible->get_id(), $forbidden->get_id(), $non_stripe->get_id(), 999999 ]
		);

		remove_filter( 'map_meta_cap', $map_caps, 10 );
		remove_filter( 'pre_http_request', $http_filter, 10 );
		$this->assertSame( 0, $http_calls );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query_args );
		$this->assertSame( 1, (int) $query_args[ WC_Stripe_Order_Reconciliation_Admin::NOTICE_QUERY_ARG ] );
		$this->assertSame( 3, (int) $query_args[ WC_Stripe_Order_Reconciliation_Admin::SKIPPED_QUERY_ARG ] );
		$this->assertSame( [ $eligible->get_id() ], $this->queued_order_ids() );

		$duplicate_redirect = $this->admin->handle_bulk_action(
			'http://example.org/wp-admin/admin.php?page=wc-orders',
			WC_Stripe_Order_Reconciliation_Admin::ORDER_ACTION,
			[ $eligible->get_id() ]
		);
		parse_str( (string) wp_parse_url( $duplicate_redirect, PHP_URL_QUERY ), $duplicate_query_args );
		$this->assertSame( 0, (int) $duplicate_query_args[ WC_Stripe_Order_Reconciliation_Admin::NOTICE_QUERY_ARG ] );
		$this->assertSame( 1, (int) $duplicate_query_args[ WC_Stripe_Order_Reconciliation_Admin::SKIPPED_QUERY_ARG ] );
	}

	public function test_single_action_adds_a_note_when_a_check_is_queued() {
		$order = $this->create_order( 'stripe', 'pi_single' );

		$this->admin->handle_order_action( $order );

		$this->assertSame( [ 'A Stripe payment status check has been scheduled.' ], $this->note_contents( $order ) );
	}

	public function test_single_action_explains_an_already_queued_check() {
		$order = $this->create_order( 'stripe', 'pi_single_duplicate' );
		$this->admin->handle_order_action( $order );

		$this->admin->handle_order_action( $order );

		$notes = $this->note_contents( $order );
		$this->assertCount( 2, $notes );
		$this->assertStringContainsString( 'one is already queued for this order', $notes[0] );
	}

	public function test_single_action_explains_an_order_that_cannot_be_checked() {
		$order = $this->create_order( 'stripe', 'pi_single_paid' );
		$order->set_status( 'processing' );
		$order->save();

		$this->admin->handle_order_action( $order );

		$notes = $this->note_contents( $order );
		$this->assertStringContainsString( 'the order is no longer pending, or it has no Stripe payment to look up', $notes[0] );
		$this->assertSame( [], $this->queued_order_ids() );
	}

	/** @dataProvider provide_bulk_notices */
	public function test_bulk_notice_reports_queued_and_skipped_orders( int $queued, int $skipped, string $expected_class, array $expected_texts ) {
		$original_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		set_current_screen( 'edit-shop_order' );
		$_GET[ WC_Stripe_Order_Reconciliation_Admin::NOTICE_QUERY_ARG ]  = (string) $queued;
		$_GET[ WC_Stripe_Order_Reconciliation_Admin::SKIPPED_QUERY_ARG ] = (string) $skipped;

		try {
			ob_start();
			$this->admin->render_admin_notice();
			$output = (string) ob_get_clean();
		} finally {
			unset( $_GET[ WC_Stripe_Order_Reconciliation_Admin::NOTICE_QUERY_ARG ], $_GET[ WC_Stripe_Order_Reconciliation_Admin::SKIPPED_QUERY_ARG ] );
			if ( null === $original_screen ) {
				unset( $GLOBALS['current_screen'] );
			} else {
				$GLOBALS['current_screen'] = $original_screen;
			}
		}

		$this->assertStringContainsString( $expected_class, $output );
		foreach ( $expected_texts as $text ) {
			$this->assertStringContainsString( $text, $output );
		}
		if ( 0 === $skipped ) {
			$this->assertStringNotContainsString( 'skipped', $output );
		}
	}

	public static function provide_bulk_notices(): array {
		return [
			'all queued'     => [ 2, 0, 'notice-success', [ '2 Stripe payment status checks scheduled.' ] ],
			'some skipped'   => [ 1, 2, 'notice-success', [ '1 Stripe payment status check scheduled.', '2 orders were skipped' ] ],
			'nothing queued' => [ 0, 1, 'notice-warning', [ '0 Stripe payment status checks scheduled.', '1 order was skipped' ] ],
		];
	}

	public function test_hook_registration_adds_the_order_action_and_both_order_list_screens() {
		$this->admin->register_hooks();

		$this->assertSame( 10, has_action( 'woocommerce_order_action_' . WC_Stripe_Order_Reconciliation_Admin::ORDER_ACTION, [ $this->admin, 'handle_order_action' ] ) );
		$this->assertSame( 10, has_filter( 'woocommerce_order_actions', [ $this->admin, 'add_order_action' ] ) );
		$this->assertSame( 10, has_filter( 'bulk_actions-edit-shop_order', [ $this->admin, 'add_bulk_action' ] ) );
		$this->assertSame( 10, has_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this->admin, 'add_bulk_action' ] ) );
		$this->assertSame( 10, has_filter( 'handle_bulk_actions-edit-shop_order', [ $this->admin, 'handle_bulk_action' ] ) );
		$this->assertSame( 10, has_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this->admin, 'handle_bulk_action' ] ) );
		$this->assertSame( 10, has_action( 'admin_notices', [ $this->admin, 'render_admin_notice' ] ) );
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

	private function create_order( string $payment_method, string $intent_id ): WC_Order {
		$order = wc_create_order( [ 'status' => 'pending' ] );
		$order->set_payment_method( $payment_method );
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->save();
		return $order;
	}

	private function note_contents( WC_Order $order ): array {
		return array_map(
			static function ( $note ) {
				return $note->content;
			},
			wc_get_order_notes(
				[
					'order_id' => $order->get_id(),
					'type'     => 'internal',
				]
			)
		);
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
