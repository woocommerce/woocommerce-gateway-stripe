<?php
/**
 * Reconciles pending Stripe orders against the current Stripe account.
 *
 * @package WooCommerce\Stripe
 */

defined( 'ABSPATH' ) || exit;

class WC_Stripe_Order_Reconciliation {
	public const ORDER_HOOK       = 'wc_stripe_reconcile_order_payment';
	public const GROUP            = 'woocommerce_stripe';
	public const ATTEMPTS_META    = '_stripe_reconciliation_attempts';
	public const CHECK_DELAY      = 15 * MINUTE_IN_SECONDS;
	public const MAX_RETRY_DELAY  = 6 * HOUR_IN_SECONDS;
	public const MAX_ATTEMPTS     = 10;
	public const ORDER_ACTION     = 'wc_stripe_recheck_payment';
	public const NOTICE_QUERY_ARG = 'stripe_reconciliation_queued';

	/**
	 * The order needs no further checks: it was settled, put on hold, or Stripe says it can't be paid.
	 */
	private const RESULT_DONE = 'done';

	/**
	 * Stripe may still complete the payment, or the check could not run, so a later check is worthwhile.
	 */
	private const RESULT_RETRY = 'retry';

	public static function unschedule(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::ORDER_HOOK, null, self::GROUP ); // @phpstan-ignore argument.type (null removes matching jobs regardless of their arguments)
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
		add_action( self::ORDER_HOOK, [ $this, 'reconcile_order_payment' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'schedule_order_check' ] );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'schedule_order_check' ] );
		add_action( 'woocommerce_before_pay_action', [ $this, 'schedule_order_check' ] );
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

	/**
	 * Schedule a delayed status check for an order that is about to be paid with Stripe.
	 *
	 * These hooks fire before the payment is processed, so the order has no session or intent ID yet.
	 * Only the payment method is checked here; the job checks whether the order still needs settling.
	 * A new payment attempt on the same order starts a fresh retry budget.
	 *
	 * @param mixed $order Order or order ID, depending on the hook.
	 */
	public function schedule_order_check( $order ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$order = $this->resolve_order( $order );
		if ( ! $order || ! WC_Stripe_Order_Helper::get_instance()->is_stripe_gateway_order( $order ) ) {
			return;
		}

		if ( '' !== $order->get_meta( self::ATTEMPTS_META, true ) ) {
			$order->delete_meta_data( self::ATTEMPTS_META );
			$order->save_meta_data();
		}

		as_schedule_single_action( time() + self::CHECK_DELAY, self::ORDER_HOOK, [ 'order_id' => $order->get_id() ], self::GROUP, true );
	}

	/**
	 * Queue an immediate, one-off status check requested by the merchant.
	 *
	 * The extra argument keeps it apart from the scheduled check of the same order, which would
	 * otherwise make Action Scheduler reject it as a duplicate until the scheduled check runs.
	 *
	 * @param mixed $order Order or order ID.
	 */
	public function enqueue_order( $order ): bool {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		$order = $this->resolve_order( $order );
		if ( ! $order || ! $this->is_eligible_order( $order ) ) {
			return false;
		}

		return (bool) as_enqueue_async_action(
			self::ORDER_HOOK,
			[
				'order_id' => $order->get_id(),
				'manual'   => true,
			],
			self::GROUP,
			true
		);
	}

	/**
	 * Process an Action Scheduler job.
	 *
	 * Scheduled checks reschedule themselves with backoff while Stripe may still complete the payment.
	 * Manual checks run once and leave any scheduled check in place.
	 *
	 * @param mixed $order_id
	 * @param mixed $is_manual
	 */
	public function reconcile_order_payment( $order_id = 0, $is_manual = false ): void {
		$order = $this->resolve_order( $order_id );
		if ( ! $order || ! $this->is_eligible_order( $order ) ) {
			return;
		}
		$order_id = $order->get_id();

		$is_manual = true === $is_manual;
		$attempt   = 0;
		if ( ! $is_manual ) {
			// Counted before the check, so a request that dies mid-check still uses up an attempt.
			$attempt = absint( $order->get_meta( self::ATTEMPTS_META, true ) ) + 1;
			$order->update_meta_data( self::ATTEMPTS_META, (string) $attempt );
			$order->save_meta_data();
		}

		$result = $this->check_order_payment( $order );
		if ( $is_manual || self::RESULT_RETRY !== $result ) {
			return;
		}

		if ( $attempt >= self::MAX_ATTEMPTS ) {
			WC_Stripe_Logger::warning(
				'Gave up on reconciling a pending Stripe order.',
				[
					'order_id' => $order_id,
					'attempts' => $attempt,
				]
			);
			return;
		}

		// Not unique: this action is still running, and Action Scheduler counts running actions as duplicates.
		as_schedule_single_action( time() + $this->get_retry_delay( $attempt ), self::ORDER_HOOK, [ 'order_id' => $order_id ], self::GROUP );
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

		if ( $this->enqueue_order( $order ) ) {
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
			$order = $this->resolve_order( $order_id );
			if ( ! $order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce maps this meta capability against the order ID.
				continue;
			}
			$queued += $this->enqueue_order( $order ) ? 1 : 0;
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

	/**
	 * Check the order against Stripe and settle it when Stripe has a verified payment for it.
	 *
	 * Holds the order payment lock for the whole check, so it never runs alongside a webhook
	 * or the order-received redirect settling the same order.
	 *
	 * @param WC_Order $order Eligible order to check.
	 * @return string One of the RESULT_* constants.
	 */
	private function check_order_payment( WC_Order $order ): string {
		$order_id     = $order->get_id();
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Whoever holds the lock is settling or about to; a later check sees the outcome.
		if ( $order_helper->lock_order_payment( $order ) ) {
			return self::RESULT_RETRY;
		}

		try {
			$session_id       = $this->get_stripe_meta_id( $order, '_stripe_checkout_session_id' );
			$stored_intent_id = $this->get_stripe_meta_id( $order, '_stripe_intent_id' );
			$intent_id        = $stored_intent_id;
			$session          = null;

			if ( $session_id ) {
				$session = $this->request_stripe_object( 'checkout/sessions/' . rawurlencode( $session_id ) );
				if ( ! is_object( $session ) || isset( $session->error ) ) {
					return $this->get_request_error_result( $session, $order_id, $session_id );
				}
				if ( ! $this->is_valid_checkout_session( $session, $session_id ) ) {
					return $this->is_final_checkout_session( $session, $session_id ) ? $this->stop( 'The Checkout Session can no longer be paid.', $order_id, $session_id ) : self::RESULT_RETRY;
				}
				$intent_id = $session->payment_intent ?? '';
			}
			if ( ! is_string( $intent_id ) || '' === $intent_id ) {
				return self::RESULT_DONE;
			}

			$intent = $this->request_stripe_object( 'payment_intents/' . rawurlencode( $intent_id ) . '?expand[]=payment_method' );
			if ( ! is_object( $intent ) || isset( $intent->error ) ) {
				return $this->get_request_error_result( $intent, $order_id, $intent_id );
			}
			if ( ! $this->is_valid_payment_intent( $intent, $intent_id ) ) {
				return $this->is_final_payment_intent( $intent, $intent_id ) ? $this->stop( 'The PaymentIntent can no longer be paid.', $order_id, $intent_id ) : self::RESULT_RETRY;
			}

			$charge = $this->gateway->get_latest_charge_from_intent( $intent );
			if ( ! is_object( $charge ) ) {
				return self::RESULT_RETRY;
			}
			if ( ! $this->is_valid_charge( $charge, $intent ) ) {
				return $this->stop( 'The latest charge cannot settle the order, for example because it was refunded or disputed.', $order_id, $intent_id );
			}

			$fresh_order = $this->resolve_order( $order_id );
			if ( ! $fresh_order || ! $this->is_eligible_order( $fresh_order ) ) {
				return self::RESULT_DONE;
			}
			// A new payment attempt replaced the IDs while Stripe was being queried; check the new ones next time.
			if ( ! $this->stripe_ids_match( $fresh_order, $session_id, $stored_intent_id ) ) {
				return self::RESULT_RETRY;
			}

			if ( $session ) {
				$this->webhook_handler->process_checkout_session_payment( $fresh_order, $session, '', $intent, $charge );
				return self::RESULT_DONE;
			}

			$expected_amount   = WC_Stripe_Helper::get_stripe_amount( $fresh_order->get_total(), $fresh_order->get_currency() );
			$expected_currency = strtolower( $fresh_order->get_currency() );
			if ( (int) ( $intent->amount ?? -1 ) !== (int) $expected_amount || strtolower( (string) ( $intent->currency ?? '' ) ) !== $expected_currency ) {
				$fresh_order->update_status( 'on-hold', __( 'Stripe payment was found, but its amount or currency does not match this order. The order was placed on hold for review.', 'woocommerce-gateway-stripe' ) );
				return self::RESULT_DONE;
			}

			$this->webhook_handler->process_reconciled_payment( $fresh_order, $charge );
			return self::RESULT_DONE;
		} catch ( Exception $exception ) {
			WC_Stripe_Logger::error(
				'Reconciliation failed for order ' . $order_id . ': ' . $exception->getMessage(),
				[ 'order_id' => $order_id ]
			);
			return self::RESULT_RETRY;
		} finally {
			$order_helper->unlock_order_payment( $order );
		}
	}

	/**
	 * Decide whether a failed Stripe lookup is worth retrying.
	 *
	 * A missing object, for example from the other mode or a different account, never appears later.
	 * Any other error may be temporary.
	 *
	 * @param object|null $response  Decoded Stripe response, or null when it wasn't an object.
	 * @param int         $order_id  Order being checked, for the log.
	 * @param string      $stripe_id ID of the Stripe object that was requested, for the log.
	 * @return string One of the RESULT_* constants.
	 */
	private function get_request_error_result( ?object $response, int $order_id, string $stripe_id ): string {
		if ( null !== $response && 'resource_missing' === ( $response->error->code ?? '' ) ) {
			return $this->stop( 'The Stripe object no longer exists for the current account and mode.', $order_id, $stripe_id );
		}

		return self::RESULT_RETRY;
	}

	/**
	 * End the checks of an order that Stripe will not pay, and log why.
	 *
	 * Logged as a warning because the order stays pending, and the merchant may need to follow up.
	 *
	 * @param string $reason    Why the order can't be settled.
	 * @param int    $order_id  Order being checked.
	 * @param string $stripe_id ID of the Stripe object that settled the question.
	 * @return string Always RESULT_DONE, so callers can return it directly.
	 */
	private function stop( string $reason, int $order_id, string $stripe_id ): string {
		WC_Stripe_Logger::warning(
			'Stopped reconciling a pending Stripe order: ' . $reason,
			[
				'order_id'  => $order_id,
				'stripe_id' => $stripe_id,
			]
		);

		return self::RESULT_DONE;
	}

	/**
	 * Hooks, Action Scheduler and the order list pass orders as objects or as IDs of any scalar type.
	 *
	 * IDs are validated before calling wc_get_order(), which falls back to the order on the current
	 * admin screen when given false, and returns refunds as well as orders.
	 *
	 * @param mixed $order Order or order ID.
	 * @return WC_Order|null The order, or null when the reference doesn't point to one.
	 */
	private function resolve_order( $order ): ?WC_Order {
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		$order_id = is_scalar( $order ) ? absint( $order ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Delay before the next scheduled check: CHECK_DELAY, doubled after each attempt, up to MAX_RETRY_DELAY.
	 *
	 * The exponent is capped so a large attempt count can't overflow before the minimum applies.
	 *
	 * @param int $attempt Attempts made so far, including the one that just ran.
	 * @return int Seconds.
	 */
	private function get_retry_delay( int $attempt ): int {
		return (int) min( self::CHECK_DELAY * 2 ** min( $attempt - 1, 10 ), self::MAX_RETRY_DELAY );
	}

	/**
	 * Whether the order is still waiting for a Stripe payment that can be looked up.
	 *
	 * An order with a paid date was settled once even if its status is still pending, and settling it
	 * again would repeat the payment side effects. An order without a session or intent ID never
	 * reached Stripe, so there is nothing to look up.
	 *
	 * @param WC_Order $order Order to check.
	 * @return bool
	 */
	private function is_eligible_order( WC_Order $order ): bool {
		return 'pending' === $order->get_status()
			&& ! $order->get_date_paid()
			&& WC_Stripe_Order_Helper::get_instance()->is_stripe_gateway_order( $order )
			&& ( $this->get_stripe_meta_id( $order, '_stripe_checkout_session_id' ) || $this->get_stripe_meta_id( $order, '_stripe_intent_id' ) );
	}

	/**
	 * Read a Stripe ID from order meta.
	 *
	 * @param WC_Order $order Order to read from.
	 * @param string   $key   Meta key holding the ID.
	 * @return string The trimmed ID, or an empty string when it is missing or not a string.
	 */
	private function get_stripe_meta_id( WC_Order $order, string $key ): string {
		$value = $order->get_meta( $key, true );
		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : '';
	}

	/**
	 * Fetch a Stripe object with the keys of the current mode.
	 *
	 * @param string $endpoint API path, including any query string.
	 * @return object|null The decoded response, which can be a Stripe error object, or null when it isn't an object.
	 * @throws WC_Stripe_Exception When Stripe can't be reached or is unavailable.
	 */
	private function request_stripe_object( string $endpoint ): ?object {
		$response = WC_Stripe_API::request( [], $endpoint, 'GET' );
		return is_object( $response ) ? $response : null;
	}

	/**
	 * Whether the session is this order's completed payment session and names its PaymentIntent.
	 *
	 * A complete session alone never settles the order: its PaymentIntent and charge are verified next.
	 *
	 * @param object|null $session    Checkout Session from Stripe.
	 * @param string      $session_id Session ID stored on the order.
	 * @return bool
	 */
	private function is_valid_checkout_session( ?object $session, string $session_id ): bool {
		return null !== $session
			&& 'checkout.session' === ( $session->object ?? '' )
			&& ( $session->id ?? '' ) === $session_id
			&& 'payment' === ( $session->mode ?? '' )
			&& 'complete' === ( $session->status ?? '' )
			&& is_string( $session->payment_intent ?? null )
			&& '' !== $session->payment_intent;
	}

	/**
	 * An expired session can't be paid, and a session that isn't this order's payment session never will be.
	 * An open session, or a complete one still waiting for its PaymentIntent, may still be paid.
	 *
	 * @param object $session    Checkout Session from Stripe that failed is_valid_checkout_session().
	 * @param string $session_id Session ID stored on the order.
	 * @return bool
	 */
	private function is_final_checkout_session( object $session, string $session_id ): bool {
		return 'checkout.session' !== ( $session->object ?? '' )
			|| ( $session->id ?? '' ) !== $session_id
			|| 'payment' !== ( $session->mode ?? '' )
			|| 'expired' === ( $session->status ?? '' );
	}

	/**
	 * Whether the intent is the one requested and holds the customer's money.
	 *
	 * That means succeeded, or authorized and waiting for a manual capture.
	 *
	 * @param object|null $intent    PaymentIntent from Stripe.
	 * @param string      $intent_id ID that was requested.
	 * @return bool
	 */
	private function is_valid_payment_intent( ?object $intent, string $intent_id ): bool {
		return null !== $intent
			&& 'payment_intent' === ( $intent->object ?? '' )
			&& ( $intent->id ?? '' ) === $intent_id
			&& in_array( $intent->status ?? '', [ WC_Stripe_Intent_Status::SUCCEEDED, WC_Stripe_Intent_Status::REQUIRES_CAPTURE ], true );
	}

	/**
	 * A canceled intent can't be paid. Intents that need an action, a payment method or more time
	 * (async payment methods) may still succeed.
	 *
	 * @param object $intent    PaymentIntent from Stripe that failed is_valid_payment_intent().
	 * @param string $intent_id ID that was requested.
	 * @return bool
	 */
	private function is_final_payment_intent( object $intent, string $intent_id ): bool {
		return 'payment_intent' !== ( $intent->object ?? '' )
			|| ( $intent->id ?? '' ) !== $intent_id
			|| WC_Stripe_Intent_Status::CANCELED === ( $intent->status ?? '' );
	}

	/**
	 * Whether the charge proves the intent was paid in full, so it can settle the order.
	 *
	 * Any refund or dispute rules it out: those orders need the merchant, not an automatic paid status.
	 * The charge must belong to the intent and match its amount and currency. A succeeded intent needs a
	 * captured, paid charge for the full amount; an authorized intent needs an uncaptured charge.
	 *
	 * @param object $charge Latest charge of the intent.
	 * @param object $intent PaymentIntent that passed is_valid_payment_intent().
	 * @return bool
	 */
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

	/**
	 * Whether the order still carries the session and intent IDs the check started with.
	 *
	 * A new payment attempt during the Stripe lookups replaces them, and the fetched objects would then
	 * belong to the previous attempt.
	 *
	 * @param WC_Order $order            Order re-read after the Stripe lookups.
	 * @param string   $session_id       Session ID read at the start of the check.
	 * @param string   $stored_intent_id Intent ID read at the start of the check.
	 * @return bool
	 */
	private function stripe_ids_match( WC_Order $order, string $session_id, string $stored_intent_id ): bool {
		$current_session_id = $this->get_stripe_meta_id( $order, '_stripe_checkout_session_id' );
		$current_intent_id  = $this->get_stripe_meta_id( $order, '_stripe_intent_id' );

		return $session_id === $current_session_id && $stored_intent_id === $current_intent_id;
	}
}
