<?php
/**
 * Lets merchants re-check pending Stripe orders from the order screen and the Orders list.
 *
 * @package WooCommerce\Stripe
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order screen and Orders list actions for re-checking Stripe payments.
 *
 * @since x.x.x
 * @internal Not part of the public API; it can change without notice.
 */
class WC_Stripe_Order_Reconciliation_Admin {
	public const ORDER_ACTION      = 'wc_stripe_recheck_payment';
	public const NOTICE_QUERY_ARG  = 'stripe_reconciliation_queued';
	public const SKIPPED_QUERY_ARG = 'stripe_reconciliation_skipped';

	/**
	 * Queues and runs the checks.
	 *
	 * @var WC_Stripe_Order_Reconciliation
	 */
	private $reconciliation;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Order_Reconciliation $reconciliation Queues and runs the checks.
	 */
	public function __construct( WC_Stripe_Order_Reconciliation $reconciliation ) {
		$this->reconciliation = $reconciliation;
	}

	/**
	 * Register the order screen and Orders list hooks, for both the legacy and HPOS order screens.
	 *
	 * @since x.x.x
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_order_action_' . self::ORDER_ACTION, [ $this, 'handle_order_action' ] );
		add_filter( 'woocommerce_order_actions', [ $this, 'add_order_action' ], 10, 2 );
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_action' ] );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_action' ] );
		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_action' ], 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_action' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
	}

	/**
	 * Add an order action when the order can be checked.
	 *
	 * @since x.x.x
	 * @param mixed $actions
	 * @param mixed $order
	 * @return mixed
	 */
	public function add_order_action( $actions, $order ) {
		if ( ! is_array( $actions ) || ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_order', $order->get_id() ) || ! $this->reconciliation->is_eligible_order( $order ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
			return $actions;
		}

		$actions[ self::ORDER_ACTION ] = __( 'Re-check payment status with Stripe', 'woocommerce-gateway-stripe' );
		return $actions;
	}

	/**
	 * Run the requested order status check right away.
	 *
	 * Unlike the bulk action, this checks a single order, so the merchant sees the result when the order screen reloads.
	 *
	 * @since x.x.x
	 * @param mixed $order
	 */
	public function handle_order_action( $order ): void {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
			return;
		}

		// The action is only offered for eligible orders, but the order can change before it is saved.
		if ( ! $this->reconciliation->is_eligible_order( $order ) ) {
			$order->add_order_note( __( 'No Stripe payment status check was run: the order is no longer pending, or it has no Stripe payment to look up. Check the payment in your Stripe Dashboard.', 'woocommerce-gateway-stripe' ) );
			return;
		}

		$this->reconciliation->reconcile_order_payment( $order->get_id(), true );
	}

	/**
	 * Add the bulk order action when it is available.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
	 * @param mixed $redirect_to
	 * @param mixed $action
	 * @param mixed $order_ids
	 * @return mixed
	 */
	public function handle_bulk_action( $redirect_to, $action, $order_ids ) {
		if ( ! is_string( $redirect_to ) || self::ORDER_ACTION !== $action || ! is_array( $order_ids ) || ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this order-list capability.
			return $redirect_to;
		}

		$queued  = 0;
		$skipped = 0;
		foreach ( $order_ids as $order_id ) {
			$order = $this->reconciliation->resolve_order( $order_id );
			if ( ! $order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
				++$skipped;
				continue;
			}
			if ( $this->reconciliation->enqueue_order( $order ) ) {
				++$queued;
			} else {
				++$skipped;
			}
		}

		return add_query_arg(
			[
				self::NOTICE_QUERY_ARG  => $queued,
				self::SKIPPED_QUERY_ARG => $skipped,
			],
			$redirect_to
		);
	}

	/**
	 * Show how many checks the bulk action queued, after WooCommerce redirects back to the Orders list.
	 *
	 * @since x.x.x
	 */
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
		$count         = absint( sanitize_text_field( (string) $count_value ) );
		$skipped_value = isset( $_GET[ self::SKIPPED_QUERY_ARG ] ) ? wp_unslash( $_GET[ self::SKIPPED_QUERY_ARG ] ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Scalar input is sanitized below; WooCommerce verified the action nonce before redirecting here.
		$skipped       = is_scalar( $skipped_value ) ? absint( sanitize_text_field( (string) $skipped_value ) ) : 0;

		/* translators: %d: number of queued Stripe payment status checks. */
		$message = sprintf( _n( '%d Stripe payment status check scheduled.', '%d Stripe payment status checks scheduled.', $count, 'woocommerce-gateway-stripe' ), $count );
		if ( $skipped ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of orders that were not checked. */
				_n(
					'%d order was skipped: it is no longer pending, has no Stripe payment to look up, or already has a check queued.',
					'%d orders were skipped: they are no longer pending, have no Stripe payment to look up, or already have a check queued.',
					$skipped,
					'woocommerce-gateway-stripe'
				),
				$skipped
			);
		}

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $count ? 'notice-success' : 'notice-warning' ),
			esc_html( $message )
		);
	}
}
