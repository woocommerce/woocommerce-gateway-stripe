<?php
/**
 * Reconciles pending Stripe orders against the current Stripe account.
 *
 * @package WooCommerce\Stripe
 */

defined( 'ABSPATH' ) || exit;

class WC_Stripe_Order_Reconciliation {
	public const ORDER_HOOK   = 'wc_stripe_reconcile_order_payment';
	public const GROUP        = 'woocommerce_stripe';
	public const STARTED_META = '_stripe_reconciliation_started';
	public const DELAYED_META = '_stripe_reconciliation_delayed';

	/**
	 * This is a fallback for missed webhooks, so it waits until a normal delivery, and Stripe's first
	 * retries, would have settled the order. The payment method type isn't known yet when this is
	 * scheduled, so every order gets the same first check.
	 */
	public const FIRST_CHECK_DELAY = 30 * MINUTE_IN_SECONDS;

	/**
	 * Methods that confirm while the customer is at checkout (cards, wallets, redirects, BNPL) can't
	 * succeed after their authorization window, the longest being Affirm's 12 hours, or after an
	 * Adaptive Pricing Checkout Session expires (24 hours by default).
	 */
	public const IMMEDIATE_CHECK_INTERVAL = 6 * HOUR_IN_SECONDS;
	public const IMMEDIATE_MAX_AGE        = DAY_IN_SECONDS;

	/**
	 * Bank debits confirm in 3 to 7 business days, plus up to 10 days of microdeposit verification
	 * for ACH and ACSS. Vouchers confirm up to about 15 days after checkout (OXXO's default 5-day
	 * expiry plus Stripe's 10-day confirmation window).
	 */
	public const DELAYED_CHECK_INTERVAL = DAY_IN_SECONDS;
	public const DELAYED_MAX_AGE        = 21 * DAY_IN_SECONDS;

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
		// Classic checkout and classic pay-for-order.
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'schedule_after_classic_payment' ], 10, 2 );
		// Blocks checkout, Blocks pay-for-order and express checkout. WooCommerce calls the gateway at
		// priority 999 and the plugin's own handlers run up to 10000, so the result is final by now.
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'schedule_after_store_api_payment' ], 10001, 2 );
	}

	/**
	 * Schedule a check after a successful classic payment, if the order is still waiting for Stripe.
	 *
	 * @param mixed $result   Result of the gateway's process_payment().
	 * @param mixed $order_id Order that was paid for.
	 * @return mixed The result, unchanged.
	 */
	public function schedule_after_classic_payment( $result, $order_id ) {
		if ( is_array( $result ) && 'success' === ( $result['result'] ?? '' ) ) {
			$this->schedule_order_check_safely( $order_id );
		}

		return $result;
	}

	/**
	 * Schedule a check after a successful Store API payment, if the order is still waiting for Stripe.
	 *
	 * @param mixed $context Payment context, holding the order.
	 * @param mixed $result  Payment result.
	 */
	public function schedule_after_store_api_payment( $context, $result ): void {
		// Both objects expose their fields through __get() only, so isset() and ?? can't be used on them.
		if ( ! $context instanceof \Automattic\WooCommerce\StoreApi\Payments\PaymentContext
			|| ! $result instanceof \Automattic\WooCommerce\StoreApi\Payments\PaymentResult
			|| 'success' !== $result->status // @phpstan-ignore property.protected (readable through PaymentResult::__get())
		) {
			return;
		}

		$this->schedule_order_check_safely( $context->order ); // @phpstan-ignore property.protected (readable through PaymentContext::__get())
	}

	/**
	 * Schedule a delayed status check for an order whose payment was handed over to Stripe.
	 *
	 * Called once payment processing has returned. Orders that were settled during the request, for
	 * example card payments confirmed on the spot, are no longer eligible and get no check.
	 * A new payment attempt on the same order starts the checks over, possibly with another method.
	 *
	 * @param mixed $order Order or order ID.
	 */
	public function schedule_order_check( $order ): void {
		if ( ! WC_Stripe_Feature_Flags::is_order_reconciliation_enabled() || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$order = $this->resolve_order( $order );
		if ( ! $order || ! $this->is_eligible_order( $order ) ) {
			return;
		}

		$order->update_meta_data( self::STARTED_META, (string) time() );
		$order->delete_meta_data( self::DELAYED_META );
		$order->save_meta_data();

		as_schedule_single_action( time() + self::FIRST_CHECK_DELAY, self::ORDER_HOOK, [ 'order_id' => $order->get_id() ], self::GROUP, true );
	}

	/**
	 * Schedule a check from a checkout hook, without letting a failure reach the customer.
	 *
	 * These hooks run while the customer waits for the checkout response, and the Store API turns any
	 * exception thrown there into a checkout error. The payment has already been processed, so a
	 * missing safety-net check must never cost the order.
	 *
	 * @param mixed $order Order or order ID.
	 */
	private function schedule_order_check_safely( $order ): void {
		try {
			$this->schedule_order_check( $order );
		} catch ( Throwable $exception ) {
			WC_Stripe_Logger::error(
				'Could not schedule a Stripe payment status check: ' . $exception->getMessage(),
				[ 'order_id' => $order instanceof WC_Order ? $order->get_id() : ( is_scalar( $order ) ? absint( $order ) : 0 ) ]
			);
		}
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
		if ( ! WC_Stripe_Feature_Flags::is_order_reconciliation_enabled() || ! function_exists( 'as_enqueue_async_action' ) ) {
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
	 * Scheduled checks reschedule themselves while Stripe may still complete the payment, until the
	 * payment method's window has passed. Manual checks run once and leave any scheduled check in place.
	 *
	 * @param mixed $order_id
	 * @param mixed $is_manual
	 */
	public function reconcile_order_payment( $order_id = 0, $is_manual = false ): void {
		// The callback stays registered while the feature is off: Action Scheduler marks actions
		// without a callback as failed, so checks queued before it was turned off end here instead.
		if ( ! WC_Stripe_Feature_Flags::is_order_reconciliation_enabled() ) {
			return;
		}

		$order = $this->resolve_order( $order_id );
		if ( ! $order || ! $this->is_eligible_order( $order ) ) {
			return;
		}
		$order_id = $order->get_id();

		$result = $this->check_order_payment( $order );
		if ( true === $is_manual || self::RESULT_RETRY !== $result ) {
			return;
		}

		$next_check = $this->get_next_check_time( $order );
		if ( null === $next_check ) {
			WC_Stripe_Logger::warning(
				'Gave up on reconciling a pending Stripe order: its payment method can no longer complete.',
				[
					'order_id' => $order_id,
					'started'  => $this->get_checks_started_at( $order ),
					'delayed'  => $this->is_delayed_payment( $order ),
				]
			);
			return;
		}

		// Not unique: this action is still running, and Action Scheduler counts running actions as duplicates.
		as_schedule_single_action( $next_check, self::ORDER_HOOK, [ 'order_id' => $order_id ], self::GROUP );
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
			$this->maybe_mark_delayed_payment( $order, $intent );
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
	public function resolve_order( $order ): ?WC_Order {
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		$order_id = is_scalar( $order ) ? absint( $order ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * When to check the order next, based on how long its payment method can take to confirm.
	 *
	 * The last check lands exactly when the window closes, so a payment confirmed just before
	 * the deadline is still picked up.
	 *
	 * @param WC_Order $order Order that is still pending after a check.
	 * @return int|null Timestamp of the next check, or null when the window has passed.
	 */
	private function get_next_check_time( WC_Order $order ): ?int {
		$is_delayed = $this->is_delayed_payment( $order );
		$deadline   = $this->get_checks_started_at( $order ) + ( $is_delayed ? self::DELAYED_MAX_AGE : self::IMMEDIATE_MAX_AGE );
		$now        = time();
		if ( $now >= $deadline ) {
			return null;
		}

		return min( $now + ( $is_delayed ? self::DELAYED_CHECK_INTERVAL : self::IMMEDIATE_CHECK_INTERVAL ), $deadline );
	}

	/**
	 * When the checks of the current payment attempt started.
	 *
	 * Falls back to the order creation date for orders whose checks weren't scheduled at checkout,
	 * for example ones created before this feature.
	 *
	 * @param WC_Order $order Order being checked.
	 * @return int Timestamp.
	 */
	private function get_checks_started_at( WC_Order $order ): int {
		$started = absint( $order->get_meta( self::STARTED_META, true ) );
		if ( $started ) {
			return $started;
		}

		$created = $order->get_date_created();
		return $created ? $created->getTimestamp() : time();
	}

	/**
	 * Whether the order is paid with a method that confirms days after checkout.
	 *
	 * The Payment Element path stores the payment method type on the order. The Adaptive Pricing path
	 * doesn't, so a check also records what the PaymentIntent reported (see maybe_mark_delayed_payment()).
	 *
	 * @param WC_Order $order Order being checked.
	 * @return bool
	 */
	private function is_delayed_payment( WC_Order $order ): bool {
		return 'yes' === $order->get_meta( self::DELAYED_META, true )
			|| in_array( WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_payment_type( $order ), WC_Stripe_Payment_Methods::DELAYED_NOTIFICATION_PAYMENT_METHODS, true );
	}

	/**
	 * Remember that the PaymentIntent uses a method that confirms days later.
	 *
	 * Stored on the order so a later check that can't reach Stripe still uses the longer window.
	 *
	 * The reconciliation keeps its own flag instead of filling in `_stripe_upe_payment_type` for
	 * Adaptive Pricing orders: the webhook handler, the voucher payment methods and several helpers
	 * read that meta to decide how to process an order, and none of them expect it on Adaptive Pricing
	 * orders. Filling it in from here would change their behaviour for those orders.
	 *
	 * @param WC_Order $order  Order being checked.
	 * @param object   $intent PaymentIntent from Stripe, with its payment method expanded.
	 */
	private function maybe_mark_delayed_payment( WC_Order $order, object $intent ): void {
		$type = is_object( $intent->payment_method ?? null ) ? ( $intent->payment_method->type ?? '' ) : '';
		if ( ! in_array( $type, WC_Stripe_Payment_Methods::DELAYED_NOTIFICATION_PAYMENT_METHODS, true ) || 'yes' === $order->get_meta( self::DELAYED_META, true ) ) {
			return;
		}

		$order->update_meta_data( self::DELAYED_META, 'yes' );
		$order->save_meta_data();
	}

	/**
	 * Whether the order is still waiting for a Stripe payment that can be looked up.
	 *
	 * An order with a paid date was settled once even if its status is still pending, and settling it
	 * again would repeat the payment side effects. An order without a session or intent ID never
	 * reached Stripe, so there is nothing to look up. Subscriptions are WC_Order objects too, but their
	 * payments are taken on renewal orders, so only regular orders are checked.
	 *
	 * @param WC_Order $order Order to check.
	 * @return bool
	 */
	public function is_eligible_order( WC_Order $order ): bool {
		return 'shop_order' === $order->get_type()
			&& 'pending' === $order->get_status()
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
