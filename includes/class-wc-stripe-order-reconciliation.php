<?php
/**
 * Reconciles pending Stripe orders against the current Stripe account.
 *
 * @package WooCommerce\Stripe
 */

defined( 'ABSPATH' ) || exit;

class WC_Stripe_Order_Reconciliation {
	public const SCAN_HOOK        = 'wc_stripe_reconcile_pending_orders';
	public const ORDER_HOOK       = 'wc_stripe_reconcile_order_payment';
	public const GROUP            = 'woocommerce_stripe';
	public const PAGE_OPTION      = 'wc_stripe_reconciliation_page';
	public const ATTEMPT_META     = '_stripe_reconciliation_attempt';
	public const PAGE_SIZE        = 20;
	public const COOLDOWN         = 30 * MINUTE_IN_SECONDS;
	public const LOOKBACK         = 30 * DAY_IN_SECONDS;
	public const SETTLEMENT_DELAY = 15 * MINUTE_IN_SECONDS;
	public const ORDER_ACTION     = 'wc_stripe_recheck_payment';
	public const NOTICE_QUERY_ARG = 'stripe_reconciliation_queued';

	public static function unschedule(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::SCAN_HOOK, null, self::GROUP ); // @phpstan-ignore argument.type (null removes matching jobs regardless of their arguments)
		as_unschedule_all_actions( self::ORDER_HOOK, null, self::GROUP ); // @phpstan-ignore argument.type (null removes matching jobs regardless of their arguments)
		delete_option( self::PAGE_OPTION );
	}

	/**
	 * Gateway used to settle charge responses.
	 *
	 * @var WC_Stripe_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Existing webhook settlement routines.
	 *
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $webhook_handler;

	public function __construct( WC_Stripe_Payment_Gateway $gateway, WC_Stripe_Webhook_Handler $webhook_handler ) {
		$this->gateway         = $gateway;
		$this->webhook_handler = $webhook_handler;
	}

	public function register_hooks(): void {
		add_action( 'init', [ $this, 'ensure_scheduled' ], 20 );
		add_action( self::SCAN_HOOK, [ $this, 'scan_pending_orders' ] );
		add_action( self::ORDER_HOOK, [ $this, 'reconcile_order_payment' ] );
		if ( is_admin() ) {
			add_action( 'woocommerce_order_action_' . self::ORDER_ACTION, [ $this, 'handle_order_action' ] );
			add_filter( 'woocommerce_order_actions', [ $this, 'add_order_action' ], 10, 2 );
			add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_action' ] );
			add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_action' ] );
			add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_action' ], 10, 3 );
			add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_action' ], 10, 3 );
			add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
		}
	}

	public function ensure_scheduled(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::SCAN_HOOK, null, self::GROUP ) ) {
			as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, self::SCAN_HOOK, [], self::GROUP, true );
		}
	}

	public function scan_pending_orders(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$page_value = get_option( self::PAGE_OPTION, 1 );
		$page       = max( 1, is_scalar( $page_value ) ? absint( $page_value ) : 1 );
		$after      = time() - self::LOOKBACK;
		$before     = time() - self::SETTLEMENT_DELAY;
		$orders     = wc_get_orders(
			[
				'type'         => 'shop_order',
				'status'       => 'pending',
				'date_created' => $after . '...' . $before,
				'orderby'      => 'ID',
				'order'        => 'ASC',
				'limit'        => self::PAGE_SIZE,
				'page'         => $page,
			]
		);
		$orders     = is_array( $orders ) ? $orders : [];

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || ! $this->is_eligible_order( $order ) || ! $this->is_outside_cooldown( $order ) ) {
				continue;
			}

			$this->enqueue_order( $order->get_id() );
		}

		// Pagination lets later pending orders progress; wrapping revisits skipped or unpaid orders.
		update_option( self::PAGE_OPTION, self::PAGE_SIZE === count( $orders ) ? $page + 1 : 1, false );
	}

	/**
	 * Queue a status check for an eligible order.
	 *
	 * @param mixed $order_id
	 */
	public function enqueue_order( $order_id ): bool {
		$order_id = is_scalar( $order_id ) ? absint( $order_id ) : 0;
		if ( ! $order_id || ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! $this->is_eligible_order( $order ) || ! $this->is_outside_cooldown( $order ) ) {
			return false;
		}

		return (bool) as_enqueue_async_action( self::ORDER_HOOK, [ 'order_id' => $order_id ], self::GROUP, true );
	}

	/**
	 * Process an Action Scheduler job.
	 *
	 * @param mixed $args
	 */
	public function reconcile_order_payment( $args = [] ): void {
		$order_id = is_array( $args )
			? ( is_scalar( $args['order_id'] ?? null ) ? absint( $args['order_id'] ) : 0 )
			: ( is_scalar( $args ) ? absint( $args ) : 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order || ! $this->is_eligible_order( $order ) || ! $this->is_outside_cooldown( $order ) ) {
			return;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		if ( $order_helper->lock_order_payment( $order ) ) {
			return;
		}

		try {
			$order->update_meta_data( self::ATTEMPT_META, (string) time() );
			$order->save_meta_data();
			$session_id       = $this->get_stripe_meta_id( $order, '_stripe_checkout_session_id' );
			$stored_intent_id = $this->get_stripe_meta_id( $order, '_stripe_intent_id' );
			$intent_id        = $stored_intent_id;
			$session          = null;

			if ( $session_id ) {
				$session = $this->request_stripe_object( 'checkout/sessions/' . rawurlencode( $session_id ) );
				if ( ! is_object( $session ) || ! $this->is_valid_checkout_session( $session, $session_id ) ) {
					return;
				}
				$intent_id = $session->payment_intent ?? '';
			}
			if ( ! is_string( $intent_id ) || '' === $intent_id ) {
				return;
			}

			$intent = $this->request_stripe_object( 'payment_intents/' . rawurlencode( $intent_id ) . '?expand[]=payment_method' );
			if ( ! is_object( $intent ) || ! $this->is_valid_payment_intent( $intent, $intent_id ) ) {
				return;
			}

			$charge = $this->gateway->get_latest_charge_from_intent( $intent );
			if ( ! is_object( $charge ) || ! $this->is_valid_charge( $charge, $intent ) ) {
				return;
			}

			$fresh_order = wc_get_order( $order_id );
			if ( ! $fresh_order instanceof WC_Order || ! $this->is_eligible_order( $fresh_order ) || ! $this->stripe_ids_match( $fresh_order, $session_id, $stored_intent_id ) ) {
				return;
			}

			if ( $session ) {
				$this->webhook_handler->process_checkout_session_payment( $fresh_order, $session, '', $intent, $charge );
				return;
			}

			$expected_amount   = WC_Stripe_Helper::get_stripe_amount( $fresh_order->get_total(), $fresh_order->get_currency() );
			$expected_currency = strtolower( $fresh_order->get_currency() );
			if ( (int) ( $intent->amount ?? -1 ) !== (int) $expected_amount || strtolower( (string) ( $intent->currency ?? '' ) ) !== $expected_currency ) {
				$fresh_order->update_status( 'on-hold', __( 'Stripe payment was found, but its amount or currency does not match this order. The order was placed on hold for review.', 'woocommerce-gateway-stripe' ) );
				return;
			}

			$this->webhook_handler->process_reconciled_payment( $fresh_order, $charge );
		} catch ( Exception $exception ) {
			WC_Stripe_Logger::error(
				'Reconciliation failed for order ' . $order_id . ': ' . $exception->getMessage(),
				[ 'order_id' => $order_id ]
			);
		} finally {
			$order_helper->unlock_order_payment( $order );
		}
	}

	/**
	 * Add an order action when the order can be checked.
	 *
	 * @param mixed $actions
	 * @param mixed $order
	 * @return mixed
	 */
	public function add_order_action( $actions, $order ) {
		if ( ! is_array( $actions ) || ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_order', $order->get_id() ) || ! $this->is_eligible_order( $order ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
			return $actions;
		}

		$actions[ self::ORDER_ACTION ] = __( 'Re-check payment status with Stripe', 'woocommerce-gateway-stripe' );
		return $actions;
	}

	/**
	 * Queue the requested order status check.
	 *
	 * @param mixed $order
	 */
	public function handle_order_action( $order ): void {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
			return;
		}

		if ( $this->enqueue_order( $order->get_id() ) ) {
			$order->add_order_note( __( 'A Stripe payment status check has been scheduled.', 'woocommerce-gateway-stripe' ) );
		}
	}

	/**
	 * Add the bulk order action when it is available.
	 *
	 * @param mixed $actions
	 * @return mixed
	 */
	public function add_bulk_action( $actions ) {
		if ( ! is_array( $actions ) || ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this order-list capability.
			return $actions;
		}

		$actions[ self::ORDER_ACTION ] = __( 'Re-check payment status with Stripe', 'woocommerce-gateway-stripe' );
		return $actions;
	}

	/**
	 * Queue payment checks requested from the order list.
	 *
	 * @param mixed $redirect_to
	 * @param mixed $action
	 * @param mixed $order_ids
	 * @return mixed
	 */
	public function handle_bulk_action( $redirect_to, $action, $order_ids ) {
		if ( ! is_string( $redirect_to ) || self::ORDER_ACTION !== $action || ! is_array( $order_ids ) || ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this order-list capability.
			return $redirect_to;
		}

		$queued = 0;
		foreach ( $order_ids as $order_id ) {
			$order_id = is_scalar( $order_id ) ? absint( $order_id ) : 0;
			if ( ! $order_id || ! current_user_can( 'edit_shop_order', $order_id ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
				continue;
			}
			$queued += $this->enqueue_order( $order_id ) ? 1 : 0;
		}

		return add_query_arg( self::NOTICE_QUERY_ARG, $queued, $redirect_to );
	}

	public function render_admin_notice(): void {
		if ( ! is_admin() || ! current_user_can( 'edit_shop_orders' ) || ! isset( $_GET[ self::NOTICE_QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.WP.Capabilities.Unknown -- WooCommerce verifies the bulk-action nonce before redirecting here and registers this capability.
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, [ 'edit-shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
			return;
		}

		$count_value = wp_unslash( $_GET[ self::NOTICE_QUERY_ARG ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Scalar input is sanitized below; WooCommerce verified the action nonce before redirecting here.
		if ( ! is_scalar( $count_value ) ) {
			return;
		}
		$count = absint( sanitize_text_field( (string) $count_value ) );
		/* translators: %d: number of queued Stripe payment status checks. */
		$message = _n( '%d Stripe payment status check scheduled.', '%d Stripe payment status checks scheduled.', $count, 'woocommerce-gateway-stripe' );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( $message, $count ) )
		);
	}

	private function is_eligible_order( WC_Order $order ): bool {
		return 'pending' === $order->get_status()
			&& ! $order->get_date_paid()
			&& WC_Stripe_Order_Helper::get_instance()->is_stripe_gateway_order( $order )
			&& ( $this->get_stripe_meta_id( $order, '_stripe_checkout_session_id' ) || $this->get_stripe_meta_id( $order, '_stripe_intent_id' ) );
	}

	private function get_stripe_meta_id( WC_Order $order, string $key ): string {
		$value = $order->get_meta( $key, true );
		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : '';
	}

	private function is_outside_cooldown( WC_Order $order ): bool {
		$last_attempt = absint( $order->get_meta( self::ATTEMPT_META, true ) );
		return ! $last_attempt || time() - $last_attempt >= self::COOLDOWN;
	}

	private function request_stripe_object( string $endpoint ): ?object {
		$response = WC_Stripe_API::request( [], $endpoint, 'GET' );
		return is_object( $response ) ? $response : null;
	}

	private function is_valid_checkout_session( ?object $session, string $session_id ): bool {
		return null !== $session
			&& 'checkout.session' === ( $session->object ?? '' )
			&& ( $session->id ?? '' ) === $session_id
			&& 'payment' === ( $session->mode ?? '' )
			&& 'complete' === ( $session->status ?? '' )
			&& is_string( $session->payment_intent ?? null )
			&& '' !== $session->payment_intent;
	}

	private function is_valid_payment_intent( ?object $intent, string $intent_id ): bool {
		return null !== $intent
			&& 'payment_intent' === ( $intent->object ?? '' )
			&& ( $intent->id ?? '' ) === $intent_id
			&& in_array( $intent->status ?? '', [ WC_Stripe_Intent_Status::SUCCEEDED, WC_Stripe_Intent_Status::REQUIRES_CAPTURE ], true );
	}

	private function is_valid_charge( object $charge, object $intent ): bool {
		if ( 'charge' !== ( $charge->object ?? '' ) || empty( $charge->id ) || ! is_string( $charge->id ) ) {
			return false;
		}

		if ( ( $charge->payment_intent ?? '' ) !== ( $intent->id ?? '' )
			|| ! in_array( $charge->status ?? '', [ 'succeeded', 'pending' ], true )
			|| ! empty( $charge->refunded )
			|| ! empty( $charge->amount_refunded )
			|| ! empty( $charge->disputed )
			|| (int) ( $charge->amount ?? -1 ) !== (int) ( $intent->amount ?? -2 )
			|| strtolower( (string) ( $charge->currency ?? '' ) ) !== strtolower( (string) ( $intent->currency ?? '' ) )
		) {
			return false;
		}

		if ( 'succeeded' === ( $intent->status ?? '' ) ) {
			return 'succeeded' === $charge->status
				&& true === ( $charge->captured ?? false )
				&& true === ( $charge->paid ?? false )
				&& (int) ( $intent->amount_received ?? -1 ) === (int) ( $intent->amount ?? -2 );
		}

		return WC_Stripe_Intent_Status::REQUIRES_CAPTURE === ( $intent->status ?? '' )
			&& 'succeeded' === $charge->status
			&& false === ( $charge->captured ?? true );
	}

	private function stripe_ids_match( WC_Order $order, string $session_id, string $stored_intent_id ): bool {
		$current_session_id = $this->get_stripe_meta_id( $order, '_stripe_checkout_session_id' );
		$current_intent_id  = $this->get_stripe_meta_id( $order, '_stripe_intent_id' );

		return $session_id === $current_session_id && $stored_intent_id === $current_intent_id;
	}
}
