<?php
/**
 * Settles orders from their completed Stripe Checkout Sessions.
 *
 * @package WooCommerce\Stripe
 */

use Automattic\WooCommerce\Enums\OrderStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Settles an order from its completed Checkout Session (Adaptive Pricing), shared by the webhooks and
 * order reconciliation.
 *
 * @since x.x.x
 * @internal Not part of the public API; it can change without notice.
 */
class WC_Stripe_Checkout_Session_Settlement {
	/**
	 * Action that adds order details and metadata to the PaymentIntent after settlement.
	 *
	 * Same action the webhook handler schedules and handles for the regular payment flow.
	 */
	private const PAYMENT_INTENT_METADATA_ACTION = 'wc_stripe_process_payment_intent_metadata';

	/**
	 * Delay before the PaymentIntent metadata job runs, in seconds.
	 */
	private const PAYMENT_INTENT_METADATA_DELAY = 2 * MINUTE_IN_SECONDS;

	/**
	 * Settlement ran, and retrying it would not change anything.
	 */
	public const RESULT_PROCESSED = 'processed';

	/**
	 * The session doesn't match the order total, so the order was put on hold instead.
	 */
	public const RESULT_ON_HOLD = 'on_hold';

	/**
	 * The PaymentIntent has no charge yet, so the caller can retry later.
	 */
	public const RESULT_NO_CHARGE = 'no_charge';

	/**
	 * Gateway that records the charge on the order and loads the intent and charge when they weren't passed in.
	 *
	 * @var WC_Stripe_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Schedules the PaymentIntent metadata job.
	 *
	 * @var WC_Stripe_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Payment_Gateway               $gateway                  Gateway that records the charge on the order.
	 * @param WC_Stripe_Action_Scheduler_Service|null $action_scheduler_service Schedules the PaymentIntent metadata job.
	 */
	public function __construct( WC_Stripe_Payment_Gateway $gateway, ?WC_Stripe_Action_Scheduler_Service $action_scheduler_service = null ) {
		$this->gateway                  = $gateway;
		$this->action_scheduler_service = $action_scheduler_service ?? new WC_Stripe_Action_Scheduler_Service();
	}

	/**
	 * Settle an order from its completed Checkout Session.
	 *
	 * The caller must hold the order payment lock while this method settles the session.
	 *
	 * @param WC_Order    $order            Order linked to the session.
	 * @param object      $checkout_session Checkout Session from Stripe.
	 * @param string      $api_version      API version of the webhook event, if any.
	 * @param object|null $intent           The session's PaymentIntent, when the caller already fetched it.
	 * @param object|null $charge           The intent's latest charge, when the caller already fetched it.
	 * @return string One of the RESULT_* constants.
	 */
	public function process_payment( WC_Order $order, object $checkout_session, string $api_version = '', ?object $intent = null, ?object $charge = null ): string {
		$session_id   = $checkout_session->id;
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$order_currency = strtolower( $order->get_currency() );
		$order_amount   = WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $order_currency );

		// Add conditional checks to make sure we handle situations where the webhook is sent using an
		// older API version and schema. Before 2025-03-31.basil an Adaptive Pricing Session
		// put the buyer's figures at the top level and the store's under currency_conversion; from Basil
		// on it is the other way round, with the buyer's in presentment_details.
		// When $checkout_session->currency_conversion is present, we assume we have the older schema.
		if ( isset( $checkout_session->currency_conversion ) ) {
			$settlement_currency = isset( $checkout_session->currency_conversion->source_currency ) ? strtolower( (string) $checkout_session->currency_conversion->source_currency ) : '';
			$settlement_amount   = isset( $checkout_session->currency_conversion->amount_total ) ? (int) $checkout_session->currency_conversion->amount_total : null;
		} else {
			$settlement_currency = isset( $checkout_session->currency ) ? strtolower( (string) $checkout_session->currency ) : '';
			$settlement_amount   = isset( $checkout_session->amount_total ) ? (int) $checkout_session->amount_total : null;
		}

		if ( $settlement_amount !== $order_amount || $settlement_currency !== $order_currency ) {
			WC_Stripe_Logger::error(
				'Refusing to settle Checkout Session: settlement amount/currency does not match the order.',
				[
					'checkout_session_id' => $session_id,
					'order_id'            => $order->get_id(),
					'settlement_amount'   => $settlement_amount,
					'order_amount'        => $order_amount,
					'settlement_currency' => $settlement_currency,
					'order_currency'      => $order_currency,
					'api_version'         => $api_version,
				]
			);

			if ( null === $settlement_amount ) {
				$session_display = __( 'unknown', 'woocommerce-gateway-stripe' );
			} elseif ( ! $settlement_currency ) {
				/* translators: 1) Stripe numeric amount, e.g. '1500'. */
				$session_display = sprintf( __( 'unknown currency; Stripe amount: %1$d', 'woocommerce-gateway-stripe' ), $settlement_amount );
			} else {
				$session_display = strtoupper( $settlement_currency ) . ' ' . WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( $settlement_amount, $settlement_currency );
			}

			// The identifiers are the only handle on the payment this order gets: settlement stops here,
			// so neither the intent nor the charge is ever recorded on the order for the admin to follow.
			$session_intent_id = isset( $checkout_session->payment_intent ) ? (string) $checkout_session->payment_intent : '';
			$intent_reference  = '';
			if ( '' !== $session_intent_id ) {
				$intent_reference = ' ' . sprintf(
					/* translators: 1) opening anchor tag linking to the Stripe dashboard, 2) Stripe PaymentIntent ID, 3) closing anchor tag */
					__( 'PaymentIntent: %1$s%2$s%3$s.', 'woocommerce-gateway-stripe' ),
					'<a href="' . esc_url( WC_Stripe_Helper::get_transaction_url_for_id( $session_intent_id, empty( $checkout_session->livemode ) ) ) . '" target="_blank" rel="noopener noreferrer">',
					esc_html( $session_intent_id ),
					'</a>'
				);
			}

			$order->add_order_note(
				sprintf(
					/* translators: 1) Checkout Session settlement amount and currency 2) expected order total and currency 3) Stripe Checkout Session ID */
					__( 'Stripe payment was not marked complete: the Checkout Session settlement amount (%1$s) does not match the order total (%2$s). The order was placed on hold for manual review. Checkout Session: %3$s.', 'woocommerce-gateway-stripe' ),
					$session_display,
					strtoupper( $order_currency ) . ' ' . $order->get_total(),
					esc_html( $session_id )
				) . $intent_reference
			);

			// Hold the order rather than leaving it pending: wc_cancel_unpaid_orders() cancels pending
			// orders and restores their stock once woocommerce_hold_stock_minutes elapses, which would
			// discard an order whose payment Stripe already captured.
			if ( ! $order->has_status( OrderStatus::ON_HOLD ) ) {
				$order->update_status( OrderStatus::ON_HOLD );
			}

			return self::RESULT_ON_HOLD;
		}

		$intent_id = isset( $checkout_session->payment_intent ) ? $checkout_session->payment_intent : null;

		// Store the payment intent ID on the order.
		if ( ! empty( $intent_id ) ) {
			$order_helper->add_payment_intent_to_order( $intent_id, $order );
		}

		// Add presentment details if available.
		$presentment_details = $checkout_session->presentment_details ?? null;
		if ( $presentment_details && isset( $presentment_details->presentment_currency, $presentment_details->presentment_amount ) ) {
			$order_helper->update_stripe_presentment_currency( $order, $presentment_details->presentment_currency );
			$order_helper->update_stripe_presentment_amount( $order, $presentment_details->presentment_amount );
		}

		$this->maybe_add_order_note_for_adaptive_pricing( $order, $presentment_details );

		$intent = $intent ?? $this->gateway->get_intent_from_order( $order );

		if ( ! $intent ) {
			WC_Stripe_Logger::error( 'Could not find intent for order: ' . $order->get_id() );
			return self::RESULT_PROCESSED;
		}

		$payment_method    = $intent->payment_method;
		$payment_method_id = is_object( $payment_method )
			? ( isset( $payment_method->id ) && is_string( $payment_method->id ) ? $payment_method->id : '' )
			: ( is_string( $payment_method ) ? $payment_method : '' );

		// Update the order with the payment method ID if it's not already set.
		if ( ! $order_helper->get_stripe_source_id( $order ) && ! empty( $payment_method_id ) ) {
			$order_helper->update_stripe_source_id( $order, $payment_method_id );
		}

		$this->maybe_attach_checkout_session_customer( $checkout_session, $order );

		// Fetch the charge once; reused below.
		$is_webhook_response = null === $charge;
		$charge              = $charge ?? $this->gateway->get_latest_charge_from_intent( $intent );

		// This must run before saving the payment method: Bancontact and iDEAL/Wero need the charge to
		// save the SEPA mandate, and that step clears the save flag either way. Stripe API responses are
		// decoded to stdClass, which is also the only object type that accepts the dynamic
		// `is_webhook_response` property set below without a deprecation.
		if ( ! $charge instanceof stdClass ) {
			$order->save();
			return self::RESULT_NO_CHARGE;
		}

		// Save payment method to store if the customer requested it during checkout.
		if ( $order_helper->get_should_save_stripe_payment_method( $order ) && ! empty( $payment_method_id ) ) {
			$upe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

			$payment_method_object = is_object( $payment_method ) ? $payment_method : WC_Stripe_API::retrieve( 'payment_methods/' . $payment_method_id );
			if ( $upe_gateway instanceof WC_Stripe_UPE_Payment_Gateway && ! is_wp_error( $payment_method_object ) && empty( $payment_method_object->error ) && ! empty( $payment_method_object ) ) {
				// Get the payment method details that should be saved. That may be different from the
				// original payment method, e.g. for Bancontact and iDEAL/Wero, which are saved as SEPA.
				$payment_method_to_save = $upe_gateway->get_reusable_payment_method_for_saving( $payment_method_object, $charge );

				if ( is_object( $payment_method_to_save ) && ! empty( $payment_method_to_save->type ) ) {
					$upe_gateway->handle_saving_payment_method( $order, $payment_method_to_save, $payment_method_to_save->type );
				}

				// Clear the flag so retries don't re-run this, even when nothing was saved.
				$order_helper->delete_should_save_stripe_payment_method( $order );
			}
		}

		// Set the payment method title on the order based on the actual payment method used.
		$upe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		if ( $upe_gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
			$payment_method_type = $payment_method instanceof stdClass && isset( $payment_method->type ) ? $payment_method->type : '';
			if ( ! empty( $payment_method_type ) ) {
				$upe_gateway->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method );
			}
		}

		$order->save();

		if ( $is_webhook_response ) {
			$charge->is_webhook_response = true;
		}
		$this->gateway->process_response( $charge, $order );

		// The checkout session is created from the cart before the order exists, so the intent starts
		// without order description or metadata. Backfill them here with the same values the standard
		// non-session flow attaches at intent creation, so Adaptive Pricing transactions aren't missing
		// the order/customer identifiers merchants rely on.
		if ( ! empty( $intent_id ) ) {
			$this->action_scheduler_service->schedule_job(
				time() + self::PAYMENT_INTENT_METADATA_DELAY,
				self::PAYMENT_INTENT_METADATA_ACTION,
				[
					'payment_intent_id' => $intent_id,
					'request'           => [
						'description' => WC_Stripe_Helper::get_payment_intent_description( $order ),
						'metadata'    => $this->gateway->get_metadata_from_order( $order ),
					],
				]
			);
		} else {
			WC_Stripe_Logger::error( 'Empty intent ID, so cannot add order details and metadata.' );
		}

		return self::RESULT_PROCESSED;
	}

	/**
	 * Reads the order again from the database, bypassing the request caches.
	 *
	 * @param WC_Order $order The order to read again.
	 * @return WC_Order|null The current order, or null when it no longer exists.
	 */
	public function get_uncached_order( WC_Order $order ): ?WC_Order {
		clean_post_cache( $order->get_id() );
		if ( class_exists( \Automattic\WooCommerce\Caches\OrderCache::class ) ) {
			wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class )->remove( $order->get_id() );
		}
		$fresh_order = wc_get_order( $order->get_id() );

		return $fresh_order instanceof WC_Order ? $fresh_order : null;
	}

	/**
	 * Links the Stripe customer created at session confirmation to the order and, when the order
	 * belongs to a WP user without one, to that user. Existing IDs are never overwritten.
	 *
	 * @param object   $checkout_session The checkout session from the webhook event.
	 * @param WC_Order $order            The order the session settled.
	 */
	private function maybe_attach_checkout_session_customer( object $checkout_session, WC_Order $order ): void {
		$session_customer = $checkout_session->customer ?? null;
		$customer_id      = is_object( $session_customer ) ? ( $session_customer->id ?? null ) : $session_customer;

		// Anything but a non-empty string would fatal in the typed helpers below and abort settlement.
		if ( ! is_string( $customer_id ) || '' === $customer_id ) {
			return;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( ! $order_helper->get_stripe_customer_id( $order ) ) {
			$order_helper->update_stripe_customer_id( $order, $customer_id );
		}

		$user_id = $order->get_user_id();
		if ( $user_id > 0 ) {
			$this->attach_customer_to_user( $user_id, $customer_id );
		}
	}

	/**
	 * Adds the Adaptive Pricing local-currency order note, once per order.
	 *
	 * Checkout Session settlement can run more than once for the same order (for example while
	 * waiting for the PaymentIntent charge), so a meta flag keeps the note from repeating. The
	 * flag is saved immediately so it stays in step with the note on every early return.
	 *
	 * @since x.x.x
	 *
	 * @param WC_Order $order               The order the session settled.
	 * @param mixed    $presentment_details The Checkout Session's presentment_details, if any.
	 */
	private function maybe_add_order_note_for_adaptive_pricing( WC_Order $order, $presentment_details ): void {
		if ( ! is_object( $presentment_details ) || ! isset( $presentment_details->presentment_currency, $presentment_details->presentment_amount ) ) {
			return;
		}

		$currency = strtoupper( $presentment_details->presentment_currency );
		$amount   = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount(
			$presentment_details->presentment_amount,
			$presentment_details->presentment_currency
		);

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		if ( $order_helper->is_stripe_adaptive_pricing_note_recorded( $order, $currency, $amount ) ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1) presentment currency 2) presentment amount */
				__( 'Local currency purchase via Adaptive Pricing. Amount paid was: %1$s %2$s', 'woocommerce-gateway-stripe' ),
				$currency,
				$amount
			)
		);

		$order_helper->add_stripe_adaptive_pricing_note_record( $order, $currency, $amount );
		$order->save_meta_data();
	}

	/**
	 * Stores the Stripe customer on the user unless one is already set.
	 *
	 * Two sessions for the same user settle under different order locks, so the check-then-write
	 * on user meta needs its own lock or the later webhook could replace the earlier customer.
	 * When the lock is held by another request the attachment is skipped; that request is
	 * already writing a customer for this user.
	 *
	 * @param int    $user_id     The WP user.
	 * @param string $customer_id The Stripe customer ID.
	 */
	private function attach_customer_to_user( int $user_id, string $customer_id ): void {
		$lock_option = 'wc_stripe_user_customer_lock_' . $user_id;
		$lock_owner  = WC_Stripe_Option_Lock::acquire( $lock_option, MINUTE_IN_SECONDS );

		if ( null === $lock_owner ) {
			WC_Stripe_Logger::info( 'Skipping user customer attachment: another request holds the lock.', [ 'user_id' => $user_id ] );
			return;
		}

		try {
			// Re-read under the lock: a customer written by a concurrent request may sit behind a cached value.
			wp_cache_delete( $user_id, 'user_meta' );
			$user_customer = new WC_Stripe_Customer( $user_id );
			if ( ! $user_customer->get_id() ) {
				$user_customer->update_id_in_meta( $customer_id );
			}
		} finally {
			WC_Stripe_Option_Lock::release( $lock_option, $lock_owner );
		}
	}
}
