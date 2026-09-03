<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Order_Helper class.
 */
class WC_Stripe_Order_Helper {
	/**
	 * Meta key for Stripe currency.
	 *
	 * @var string
	 */
	private const META_STRIPE_CURRENCY = '_stripe_currency';

	/**
	 * Meta key for Stripe fee.
	 *
	 * @var string
	 */
	private const META_STRIPE_FEE = '_stripe_fee';

	/**
	 * Meta key for Stripe fee (legacy version).
	 *
	 * @var string
	 */
	private const LEGACY_META_STRIPE_FEE = 'Stripe Fee';

	/**
	 * Meta key for Stripe net.
	 *
	 * @var string
	 */
	private const META_STRIPE_NET = '_stripe_net';

	/**
	 * Meta key for Stripe net (legacy version).
	 *
	 * @var string
	 */
	private const LEGACY_META_STRIPE_NET = 'Net Revenue From Stripe';

	/**
	 * Meta key for Stripe source ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_SOURCE_ID = '_stripe_source_id';

	/**
	 * Meta key for Stripe refund ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_REFUND_ID = '_stripe_refund_id';

	/**
	 * Meta key for Stripe intent ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_INTENT_ID = '_stripe_intent_id';

	/**
	 * Meta key for Stripe setup intent ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_SETUP_INTENT = '_stripe_setup_intent';

	/**
	 * Meta key for Stripe checkout session ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_CHECKOUT_SESSION_ID = '_stripe_checkout_session_id';

	/**
	 * Meta key for whether the payment method should be saved to the store after checkout session payment.
	 *
	 * @var string
	 */
	private const META_STRIPE_SHOULD_SAVE_PAYMENT_METHOD = '_stripe_should_save_payment_method_to_store';

	/**
	 * Meta key for Stripe presentment currency.
	 *
	 * @var string
	 */
	private const META_STRIPE_PRESENTMENT_CURRENCY = '_stripe_presentment_currency';

	/**
	 * Meta key for Stripe presentment amount.
	 *
	 * @var string
	 */
	private const META_STRIPE_PRESENTMENT_AMOUNT = '_stripe_presentment_amount';

	/**
	 * Meta key for Stripe customer ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_CUSTOMER_ID = '_stripe_customer_id';

	/**
	 * Meta key for Stripe card ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_CARD_ID = '_stripe_card_id';

	/**
	 * Meta key for Stripe mandate ID.
	 *
	 * @var string
	 */
	private const META_STRIPE_MANDATE_ID = '_stripe_mandate_id';

	/**
	 * Meta key for the Stripe Multibanco data.
	 *
	 * @var string
	 */
	private const META_STRIPE_MULTIBANCO_DATA = '_stripe_multibanco';

	/**
	 * Meta key for the Stripe refund status.
	 *
	 * @var string
	 */
	private const META_STRIPE_REFUND_STATUS = '_stripe_refund_status';

	/**
	 * Meta key for the Stripe card brand.
	 *
	 * @var string
	 */
	private const META_STRIPE_CARD_BRAND = '_stripe_card_brand';

	/**
	 * Meta key for Stripe charge captured.
	 *
	 * @var string
	 */
	private const META_STRIPE_CHARGE_CAPTURED = '_stripe_charge_captured';

	/**
	 * Meta key for Stripe status final.
	 *
	 * @var string
	 */
	private const META_STRIPE_STATUS_FINAL = '_stripe_status_final';

	/**
	 * Meta key for Stripe refund failure reason.
	 *
	 * @var string
	 */
	private const META_STRIPE_REFUND_FAILURE_REASON = '_stripe_refund_failure_reason';

	/**
	 * Meta key for Stripe UPE payment type.
	 *
	 * @var string
	 */
	private const META_STRIPE_UPE_PAYMENT_TYPE = '_stripe_upe_payment_type';

	/**
	 * Meta key for Stripe UPE waiting for redirect.
	 *
	 * @var string
	 */
	private const META_STRIPE_UPE_WAITING_FOR_REDIRECT = '_stripe_upe_waiting_for_redirect';

	/**
	 * Meta key for Stripe UPE redirect processed.
	 *
	 * @var string
	 */
	private const META_STRIPE_UPE_REDIRECT_PROCESSED = '_stripe_upe_redirect_processed';

	/**
	 * Meta key for payment awaiting action.
	 *
	 * @var string
	 */
	private const META_STRIPE_PAYMENT_AWAITING_ACTION = '_stripe_payment_awaiting_action';

	/**
	 * Meta key for lock payment to prevent multiple simultaneous payment attempts.
	 *
	 * @var string
	 */
	private const META_STRIPE_LOCK_PAYMENT = '_stripe_lock_payment';

	/**
	 * Option name prefix for the row that decides who owns an order's payment lock.
	 *
	 * Order meta writes are not atomic; the unique-keyed option row is.
	 *
	 * @var string
	 */
	private const OPTION_STRIPE_LOCK_PAYMENT_OWNER_PREFIX = 'wc_stripe_payment_lock_owner_';

	/**
	 * Action Scheduler hook for the daily sweep of expired payment-lock owner rows.
	 *
	 * @since 11.0.0
	 */
	public const PAYMENT_LOCK_OWNER_SWEEP_ACTION = 'wc_stripe_payment_lock_owner_sweep';

	private const PAYMENT_LOCK_OWNER_SWEEP_BATCH_SIZE = 500;

	private const PAYMENT_LOCK_OWNER_SWEEP_MAX_BATCHES = 20;

	/**
	 * Meta key for lock refund to prevent multiple simultaneous refund attempts.
	 *
	 * @var string
	 */
	private const META_STRIPE_LOCK_REFUND = '_stripe_lock_refund';

	/**
	 * Meta key for the In-Person Payments channel.
	 *
	 * Stores the ipp_channel value from Stripe PaymentIntent metadata.
	 * Used to identify POS terminal payments (e.g. 'mobile_pos', 'mobile_store_management').
	 *
	 * @var string
	 */
	private const META_STRIPE_IPP_CHANNEL = '_stripe_ipp_channel';

	/**
	 * Singleton instance of the class.
	 *
	 * @var null|WC_Stripe_Order_Helper
	 */
	private static ?WC_Stripe_Order_Helper $instance = null;

	/**
	 * Payment locks this instance acquired, keyed by site and order.
	 *
	 * @var array<string, string>
	 */
	private array $owned_payment_locks = [];

	/**
	 * Gets the singleton instance of the class.
	 *
	 * @return WC_Stripe_Order_Helper
	 */
	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Sets the singleton instance of the class.
	 *
	 * @param WC_Stripe_Order_Helper|null $instance
	 * @return void
	 */
	public static function set_instance( ?self $instance ) {
		self::$instance = $instance;
	}

	/**
	 * Gets the Stripe currency for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order
	 * @return string $currency
	 */
	public function get_stripe_currency( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_CURRENCY );
	}

	/**
	 * Updates the Stripe currency for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order
	 * @param string $currency
	 */
	public function update_stripe_currency( WC_Order $order, string $currency ) {
		return $this->update_order_meta( $order, self::META_STRIPE_CURRENCY, $currency );
	}

	/**
	 * Gets the Stripe fee for order. With legacy check.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return string $amount
	 */
	public function get_stripe_fee( ?WC_Order $order = null ) {
		$amount = $this->get_order_meta( $order, self::META_STRIPE_FEE );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $this->get_order_meta( $order, self::LEGACY_META_STRIPE_FEE );

			// If found update to new name.
			if ( $amount ) {
				$this->update_stripe_fee( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe fee for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param float $amount
	 */
	public function update_stripe_fee( ?WC_Order $order = null, float $amount = 0.0 ) {
		return $this->update_order_meta( $order, self::META_STRIPE_FEE, $amount );
	}

	/**
	 * Deletes the Stripe fee for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 */
	public function delete_stripe_fee( ?WC_Order $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->delete_meta_data( self::META_STRIPE_FEE );
		$order->delete_meta_data( self::LEGACY_META_STRIPE_FEE );
	}

	/**
	 * Gets the Stripe net for order. With legacy check.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return string $amount
	 */
	public function get_stripe_net( ?WC_Order $order = null ) {
		$amount = $this->get_order_meta( $order, self::META_STRIPE_NET );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $this->get_order_meta( $order, self::LEGACY_META_STRIPE_NET );

			// If found update to new name.
			if ( $amount ) {
				$this->update_stripe_net( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe net for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param float $amount
	 */
	public function update_stripe_net( ?WC_Order $order = null, float $amount = 0.0 ) {
		return $this->update_order_meta( $order, self::META_STRIPE_NET, $amount );
	}

	/**
	 * Deletes the Stripe net for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 */
	public function delete_stripe_net( ?WC_Order $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->delete_meta_data( self::META_STRIPE_NET );
		$order->delete_meta_data( self::LEGACY_META_STRIPE_NET );
	}

	/**
	 * Gets the Stripe source for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_source_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_SOURCE_ID );
	}

	/**
	 * Updates the Stripe source for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param string $source_id
	 * @return false|void
	 */
	public function update_stripe_source_id( ?WC_Order $order = null, string $source_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_SOURCE_ID, $source_id );
	}

	/**
	 * Deletes the Stripe source for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_source_id( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_SOURCE_ID );
	}

	/**
	 * Updates the Stripe refund for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_refund_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_REFUND_ID );
	}

	/**
	 * Updates the Stripe refund for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param string $refund_id
	 * @return false|void
	 */
	public function update_stripe_refund_id( ?WC_Order $order = null, string $refund_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Deletes the Stripe refund for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_refund_id( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_REFUND_ID );
	}

	/**
	 * Gets the Stripe refund ID stored on a refund record.
	 *
	 * The parent order's `_stripe_refund_id` only tracks the most recent refund, so
	 * per-refund reconciliation must read the ID from the refund record itself.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order_Refund|null $refund
	 * @return false|string|null
	 */
	public function get_stripe_refund_id_for_refund( ?WC_Order_Refund $refund = null ) {
		if ( null === $refund ) {
			return false;
		}

		return $refund->get_meta( self::META_STRIPE_REFUND_ID, true );
	}

	/**
	 * Stores the Stripe refund ID on a refund record.
	 *
	 * Does not persist; callers must save the refund. `WC_Order_Refund` has no
	 * `transaction_id` in WooCommerce core, so meta is the only portable storage.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order_Refund|null $refund
	 * @param string $refund_id
	 * @return false|void
	 */
	public function update_stripe_refund_id_for_refund( ?WC_Order_Refund $refund = null, string $refund_id = '' ) {
		if ( null === $refund ) {
			return false;
		}

		$refund->update_meta_data( self::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Deletes the Stripe refund ID from a refund record.
	 *
	 * Does not persist; callers must save the refund.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order_Refund|null $refund
	 * @return false|void
	 */
	public function delete_stripe_refund_id_for_refund( ?WC_Order_Refund $refund = null ) {
		if ( null === $refund ) {
			return false;
		}

		$refund->delete_meta_data( self::META_STRIPE_REFUND_ID );
	}

	/**
	 * Returns the order's refund records that carry their own Stripe refund ID.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order $order
	 * @return WC_Order_Refund[]
	 */
	public function get_refunds_with_stripe_refund_ids( WC_Order $order ): array {
		return array_filter(
			$order->get_refunds(),
			function ( $refund ) {
				return ! empty( $this->get_stripe_refund_id_for_refund( $refund ) );
			}
		);
	}

	/**
	 * Deletes the Stripe refund ID from each of the order's refund records.
	 *
	 * Unlike the single-record methods, this persists each deletion — as a bulk
	 * operation over records it looked up itself, callers have nothing to save.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function delete_stripe_refund_ids_from_refunds( WC_Order $order ): void {
		foreach ( $this->get_refunds_with_stripe_refund_ids( $order ) as $refund ) {
			$this->delete_stripe_refund_id_for_refund( $refund );
			$refund->save_meta_data();
		}
	}

	/**
	 * Gets the Stripe intent for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_intent_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_INTENT_ID );
	}

	/**
	 * Updates the Stripe intent for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param string $intent_id
	 * @return false|void
	 */
	public function update_stripe_intent_id( ?WC_Order $order = null, string $intent_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_INTENT_ID, $intent_id );
	}

	/**
	 * Deletes the Stripe intent for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_intent_id( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_INTENT_ID );
	}

	/**
	 * Gets the Stripe setup intent for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_setup_intent_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_SETUP_INTENT );
	}

	/**
	 * Updates the Stripe setup intent for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param string $intent_id
	 * @return false|void
	 */
	public function update_stripe_setup_intent_id( ?WC_Order $order = null, string $intent_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_SETUP_INTENT, $intent_id );
	}

	/**
	 * Gets the Stripe checkout session ID for order.
	 *
	 * @since 10.5.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_checkout_session_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_CHECKOUT_SESSION_ID );
	}

	/**
	 * Updates the Stripe checkout session ID for order.
	 *
	 * @since 10.5.0
	 *
	 * @param WC_Order|null $order
	 * @param string $checkout_session_id
	 * @return false|void
	 */
	public function update_stripe_checkout_session_id( ?WC_Order $order = null, string $checkout_session_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_CHECKOUT_SESSION_ID, $checkout_session_id );
	}

	/**
	 * Gets whether the payment method should be saved to the store after a checkout session payment.
	 *
	 * @param WC_Order|null $order
	 * @return bool
	 */
	public function get_should_save_stripe_payment_method( ?WC_Order $order = null ): bool {
		return wc_string_to_bool( $this->get_order_meta( $order, self::META_STRIPE_SHOULD_SAVE_PAYMENT_METHOD ) );
	}

	/**
	 * Sets the flag indicating the payment method should be saved to the store after a checkout session payment.
	 *
	 * @param WC_Order|null $order
	 */
	public function update_should_save_stripe_payment_method( ?WC_Order $order = null, bool $value = false ): void {
		$this->update_order_meta( $order, self::META_STRIPE_SHOULD_SAVE_PAYMENT_METHOD, wc_bool_to_string( $value ) );
	}

	/**
	 * Clears the flag indicating the payment method should be saved to the store after a checkout session payment.
	 *
	 * @param WC_Order|null $order
	 */
	public function delete_should_save_stripe_payment_method( ?WC_Order $order = null ): void {
		$this->delete_order_meta( $order, self::META_STRIPE_SHOULD_SAVE_PAYMENT_METHOD );
	}

	/**
	 * Gets the Stripe presentment currency for order.
	 *
	 * @since 10.5.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_presentment_currency( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_PRESENTMENT_CURRENCY );
	}

	/**
	 * Updates the Stripe presentment currency for order.
	 *
	 * @since 10.5.0
	 *
	 * @param WC_Order|null $order
	 * @param string $presentment_currency
	 * @return false|void
	 */
	public function update_stripe_presentment_currency( ?WC_Order $order = null, string $presentment_currency = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_PRESENTMENT_CURRENCY, strtolower( $presentment_currency ) );
	}

	/**
	 * Gets the Stripe presentment amount for order.
	 *
	 * @since 10.5.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_presentment_amount( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_PRESENTMENT_AMOUNT );
	}

	/**
	 * Updates the Stripe presentment amount for order.
	 *
	 * @since 10.5.0
	 *
	 * @param WC_Order|null $order
	 * @param int           $presentment_amount Stripe minor-unit integer (e.g. 7800 for $78.00)
	 * @return false|void
	 */
	public function update_stripe_presentment_amount( ?WC_Order $order = null, int $presentment_amount = 0 ) {
		return $this->update_order_meta( $order, self::META_STRIPE_PRESENTMENT_AMOUNT, $presentment_amount );
	}

	/**
	 * Gets the Stripe customer ID for an order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_customer_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_CUSTOMER_ID );
	}

	/**
	 * Updates the Stripe customer ID for an order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param string $customer_id
	 * @return false|void
	 */
	public function update_stripe_customer_id( ?WC_Order $order = null, string $customer_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_CUSTOMER_ID, $customer_id );
	}

	/**
	 * Deletes the Stripe customer ID for an order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_customer_id( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_CUSTOMER_ID );
	}

	/**
	 * Gets the Stripe card for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_card_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_CARD_ID );
	}

	/**
	 * Deletes the Stripe card for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_card_id( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_CARD_ID );
	}

	/**
	 * Gets the Stripe mandate ID for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_mandate_id( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_MANDATE_ID );
	}

	/**
	 * Updates the Stripe mandate ID for an order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @param string $mandate_id
	 * @return false|void
	 */
	public function update_stripe_mandate_id( ?WC_Order $order = null, string $mandate_id = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_MANDATE_ID, $mandate_id );
	}

	/**
	 * Gets the Stripe Multibanco data for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_multibanco_data( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_MULTIBANCO_DATA );
	}

	/**
	 * Updates the Stripe Multibanco data for an order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @param array $multibanco_data
	 * @return false|void
	 */
	public function update_stripe_multibanco_data( ?WC_Order $order = null, array $multibanco_data = [] ) {
		return $this->update_order_meta( $order, self::META_STRIPE_MULTIBANCO_DATA, $multibanco_data );
	}

	/**
	 * Updates the Stripe refund status for an order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @param string $status
	 * @return false|void
	 */
	public function update_stripe_refund_status( ?WC_Order $order = null, string $status = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_REFUND_STATUS, $status );
	}

	/**
	 * Updates the Stripe card brand for an order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @param string $brand
	 * @return false|void
	 */
	public function update_stripe_card_brand( ?WC_Order $order = null, string $brand = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_CARD_BRAND, $brand );
	}

	/**
	 * Gets the Stripe refund failure reason for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_refund_failure_reason( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_REFUND_FAILURE_REASON );
	}

	/**
	 * Updates the Stripe mandate ID for an order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @param string $failure_reason
	 * @return false|void
	 */
	public function update_stripe_refund_failure_reason( ?WC_Order $order = null, string $failure_reason = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_REFUND_FAILURE_REASON, $failure_reason );
	}

	/**
	 * Deletes the Stripe refund failure reason for an order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_refund_failure_reason( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_REFUND_FAILURE_REASON );
	}

	/**
	 * Gets whether charge was captured for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order The order to get the meta from.
	 * @return false|string|null
	 */
	public function get_stripe_charge_captured( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_CHARGE_CAPTURED );
	}

	/**
	 * Checks if charge was captured for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @return bool
	 */
	public function is_stripe_charge_captured( ?WC_Order $order = null ): bool {
		return wc_string_to_bool( $this->get_stripe_charge_captured( $order ) );
	}

	/**
	 * Checks whether the order's charge was explicitly recorded as authorize-only ('no').
	 *
	 * Deliberately false when the flag was never recorded (''): capture and void flows act on
	 * this state by moving or releasing money, so an unknown state must not qualify — only a
	 * charge Stripe was seen to leave uncaptured. Contrast is_stripe_charge_captured(), which
	 * folds missing into false.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order|null $order The order to check.
	 * @return bool
	 */
	public function is_stripe_charge_authorized_only( ?WC_Order $order = null ): bool {
		return 'no' === $this->get_stripe_charge_captured( $order );
	}

	/**
	 * Sets whether charge was captured for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order $order The order to add the metadata to.
	 * @param bool $captured  Whether the charge was captured.
	 *
	 * @return void
	 */
	public function set_stripe_charge_captured( WC_Order $order, bool $captured = true ): void {
		$this->update_order_meta( $order, self::META_STRIPE_CHARGE_CAPTURED, wc_bool_to_string( $captured ) );
	}

	/**
	 * Records the captured state carried by a Stripe charge on the order.
	 *
	 * This is the single writer of the captured flag for every code path that receives a
	 * charge object — a checkout response (process_response()), a webhook payload
	 * (payment_intent.processing, charge.succeeded, charge.captured, charge.refunded), or
	 * an on-demand API fetch (refund-time resolution, charge ID recovery). Refund and
	 * capture flows read the flag to tell a refundable charge from a voidable
	 * pre-authorization.
	 *
	 * Some payment methods create their charge only after checkout, so a webhook payload or
	 * an API fetch may be the first — and only — chance to record the flag.
	 *
	 * Does not persist the order; callers decide when to save.
	 *
	 * @since 10.9.0
	 *
	 * @param WC_Order $order The order to record the captured state on.
	 * @param object|string|null $charge The received Stripe charge (or charge-shaped webhook payload).
	 * @return bool|null The recorded captured state, or null when the charge carries none.
	 */
	public function sync_stripe_charge_captured( WC_Order $order, $charge ): ?bool {
		if ( ! is_object( $charge ) || ! isset( $charge->captured ) ) {
			return null;
		}

		$captured = (bool) $charge->captured;
		$this->set_stripe_charge_captured( $order, $captured );

		return $captured;
	}

	/**
	 * Checks if stripe status is final for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order|null $order
	 * @return bool
	 */
	public function is_stripe_status_final( ?WC_Order $order = null ): bool {
		return wc_string_to_bool( $this->get_order_meta( $order, self::META_STRIPE_STATUS_FINAL ) );
	}

	/**
	 * Sets whether stripe status is final for order.
	 *
	 * @since 10.1.0
	 *
	 * @param WC_Order $order The order to add the metadata to.
	 * @param bool $is_final  Whether the stripe status is final.
	 *
	 * @return void
	 */
	public function set_stripe_status_final( WC_Order $order, bool $is_final = true ): void {
		$this->update_order_meta( $order, self::META_STRIPE_STATUS_FINAL, wc_bool_to_string( $is_final ) );
	}

	/**
	 * Gets the Stripe UPE payment type for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_upe_payment_type( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_UPE_PAYMENT_TYPE );
	}

	/**
	 * Updates the Stripe UPE payment type for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param string $payment_type
	 * @return false|void
	 */
	public function update_stripe_upe_payment_type( ?WC_Order $order = null, string $payment_type = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_UPE_PAYMENT_TYPE, $payment_type );
	}

	/**
	 * Gets the Stripe UPE waiting for redirect for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return bool|null
	 */
	public function get_stripe_upe_waiting_for_redirect( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_UPE_WAITING_FOR_REDIRECT );
	}

	/**
	 * Updates the Stripe UPE waiting for redirect for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param bool $waiting_for_redirect
	 * @return false|void
	 */
	public function update_stripe_upe_waiting_for_redirect( ?WC_Order $order = null, bool $waiting_for_redirect = false ) {
		return $this->update_order_meta( $order, self::META_STRIPE_UPE_WAITING_FOR_REDIRECT, $waiting_for_redirect );
	}

	/**
	 * Deletes the Stripe UPE waiting for redirect for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|void
	 */
	public function delete_stripe_upe_waiting_for_redirect( ?WC_Order $order = null ) {
		return $this->delete_order_meta( $order, self::META_STRIPE_UPE_WAITING_FOR_REDIRECT );
	}

	/**
	 * Gets the Stripe UPE redirect processed for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return bool|null
	 */
	public function get_stripe_upe_redirect_processed( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_UPE_REDIRECT_PROCESSED );
	}

	/**
	 * Updates the Stripe UPE redirect processed for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @param bool $redirect_processed
	 * @return false|void
	 */
	public function update_stripe_upe_redirect_processed( ?WC_Order $order = null, bool $redirect_processed = false ) {
		return $this->update_order_meta( $order, self::META_STRIPE_UPE_REDIRECT_PROCESSED, $redirect_processed );
	}

	/**
	 * Gets the In-Person Payments channel for the order.
	 *
	 * @since 10.6.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_ipp_channel( ?WC_Order $order = null ) {
		return $this->get_order_meta( $order, self::META_STRIPE_IPP_CHANNEL );
	}

	/**
	 * Updates the In-Person Payments channel for the order.
	 *
	 * @since 10.6.0
	 *
	 * @param WC_Order|null $order
	 * @param string $channel The IPP channel value (e.g. 'mobile_pos', 'mobile_store_management').
	 * @return false|void
	 */
	public function update_stripe_ipp_channel( ?WC_Order $order = null, string $channel = '' ) {
		return $this->update_order_meta( $order, self::META_STRIPE_IPP_CHANNEL, $channel );
	}

	/**
	 * Adds payment intent id and order note to order if payment intent is not already saved
	 *
	 * @since 10.0.0
	 *
	 * @param string $payment_intent_id The payment intent ID to add to the order.
	 * @param $order WC_Order
	 */
	public function add_payment_intent_to_order( string $payment_intent_id, WC_Order $order ): void {
		$old_intent_id = $order->get_meta( self::META_STRIPE_INTENT_ID );

		if ( $old_intent_id === $payment_intent_id ) {
			return;
		}

		$order->add_order_note(
			sprintf(
			/* translators: $1%s payment intent ID */
				__( 'Stripe payment intent created (Payment Intent ID: %1$s)', 'woocommerce-gateway-stripe' ),
				$payment_intent_id
			)
		);

		$order->update_meta_data( self::META_STRIPE_INTENT_ID, $payment_intent_id );
		$order->save();
	}

	/**
	 * Adds metadata to the order to indicate that the payment is awaiting action.
	 *
	 * This meta is primarily used to prevent orders from being cancelled by WooCommerce's hold stock settings.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to add the metadata to.
	 * @param bool     $save  Whether to save the order after adding the metadata.
	 *
	 * @return void
	 */
	public function set_payment_awaiting_action( WC_Order $order, bool $save = true ): void {
		$this->update_order_meta( $order, self::META_STRIPE_PAYMENT_AWAITING_ACTION, wc_bool_to_string( true ) );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Checks if the order is awaiting action for payment.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to check.
	 * @return bool
	 */
	public function is_payment_awaiting_action( WC_Order $order ): bool {
		return wc_string_to_bool( $order->get_meta( self::META_STRIPE_PAYMENT_AWAITING_ACTION, true ) );
	}

	/**
	 * Removes the metadata from the order that was used to indicate that the payment was awaiting action.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to remove the metadata from.
	 * @param bool     $save  Whether to save the order after removing the metadata.
	 *
	 * @return void
	 */
	public function remove_payment_awaiting_action( WC_Order $order, bool $save = true ): void {
		$order->delete_meta_data( self::META_STRIPE_PAYMENT_AWAITING_ACTION );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Returns the payment intent or setup intent ID from a given order object.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to fetch the Stripe intent from.
	 *
	 * @return string|bool  The intent ID if found, false otherwise.
	 */
	public function get_intent_id_from_order( WC_Order $order ) {
		$intent_id = $order->get_meta( self::META_STRIPE_INTENT_ID );

		if ( ! $intent_id ) {
			$intent_id = $order->get_meta( self::META_STRIPE_SETUP_INTENT );
		}

		return $intent_id ?? false;
	}

	/**
	 * Get owner details.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order
	 * @return object $details
	 */
	public function get_owner_details( WC_Order $order ): object {
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		$details = [];

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = $order->get_billing_address_1();
		$details['address']['line2']       = $order->get_billing_address_2();
		$details['address']['state']       = $order->get_billing_state();
		$details['address']['city']        = $order->get_billing_city();
		$details['address']['postal_code'] = $order->get_billing_postcode();
		$details['address']['country']     = $order->get_billing_country();

		/** This filter is documented in includes/abstracts/abstract-wc-stripe-payment-gateway.php. */
		return (object) apply_filters( 'wc_stripe_owner_details', $details, $order );
	}

	/**
	 * Checks if the given payment intent is valid for the order.
	 * This checks the currency, amount, and payment method types.
	 * The function will log a critical error if there is a mismatch.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order      $order                 The order to check.
	 * @param object|string $intent                The payment intent to check, can either be an object or an intent ID.
	 * @param string|null   $selected_payment_type The selected payment type, which is generally applicable for updates. If null, we will use the stored payment type for the order.
	 *
	 * @throws Exception Throws an exception if the intent is not valid for the order.
	 */
	public function validate_intent_for_order( WC_Order $order, $intent, ?string $selected_payment_type = null ): void {
		$intent_id = null;
		if ( is_string( $intent ) ) {
			$intent_id       = $intent;
			$is_setup_intent = substr( $intent_id, 0, 4 ) === 'seti';
			if ( $is_setup_intent ) {
				$intent = WC_Stripe_API::retrieve( 'setup_intents/' . $intent_id . '?expand[]=payment_method' );
			} else {
				$intent = WC_Stripe_API::retrieve( 'payment_intents/' . $intent_id . '?expand[]=payment_method' );
			}
		}

		if ( ! is_object( $intent ) ) {
			throw new Exception( __( "We're not able to process this request. Please try again later.", 'woocommerce-gateway-stripe' ) );
		}

		if ( null === $intent_id ) {
			$intent_id = $intent->id ?? null;
		}

		// Make sure we actually fetched the intent.
		if ( ! empty( $intent->error ) ) {
			WC_Stripe_Logger::error(
				'Error: failed to fetch requested Stripe intent',
				[
					'intent_id' => $intent_id,
					'error'     => $intent->error,
				]
			);
			throw new Exception( __( "We're not able to process this request. Please try again later.", 'woocommerce-gateway-stripe' ) );
		}

		if ( null === $selected_payment_type ) {
			$selected_payment_type = $this->get_stripe_upe_payment_type( $order );
		}

		// If we don't have a selected payment type, that implies we have no stored value and a new payment type is permitted.
		$is_valid_payment_type = empty( $selected_payment_type ) || ( ! empty( $intent->payment_method_types ) && in_array( $selected_payment_type, $intent->payment_method_types, true ) );
		$order_currency        = strtolower( $order->get_currency() );
		$order_amount          = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );
		$order_intent_id       = self::get_intent_id_from_order( $order );

		if ( 'payment_intent' === $intent->object ) {
			$is_valid = $order_currency === $intent->currency
				&& $is_valid_payment_type
				&& $order_amount === $intent->amount
				&& ( ! $order_intent_id || $order_intent_id === $intent->id );
		} else {
			// Setup intents don't have an amount or currency.
			$is_valid = $is_valid_payment_type
				&& ( ! $order_intent_id || $order_intent_id === $intent->id );
		}

		// Return early if we have a valid intent.
		if ( $is_valid ) {
			return;
		}

		$permitted_payment_types = implode( '/', $intent->payment_method_types );
		WC_Stripe_Logger::critical(
			"Error: Invalid payment intent for order. Intent: {$intent->currency} {$intent->amount} via {$permitted_payment_types}, Order: {$order_currency} {$order_amount} {$selected_payment_type}",
			[
				'order_id'                    => $order->get_id(),
				'intent_id'                   => $intent->id,
				'intent_currency'             => $intent->currency,
				'intent_amount'               => $intent->amount,
				'intent_payment_method_types' => $intent->payment_method_types,
				'selected_payment_type'       => $selected_payment_type,
				'order_currency'              => $order->get_currency(),
				'order_total'                 => $order->get_total(),
			]
		);

		throw new Exception( __( "We're not able to process this request. Please try again later.", 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Checks if the order is using a Stripe payment method.
	 *
	 * @since 10.0.0
	 *
	 * @param $order WC_Order The order to check.
	 * @return bool
	 */
	public function is_stripe_gateway_order( WC_Order $order ): bool {
		return WC_Stripe_UPE_Payment_Gateway::ID === substr( (string) $order->get_payment_method(), 0, 6 );
	}

	/**
	 * Validates that the order meets the minimum order amount
	 * set by Stripe.
	 *
	 * @since 10.0.0
	 * @param WC_Order $order
	 */
	public function validate_minimum_order_amount( WC_Order $order ): void {
		if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Locks an order for payment intent processing for 5 minutes.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order  The order that is being paid.
	 * @return bool            A flag that indicates whether the order is already locked.
	 */
	public function lock_order_payment( WC_Order $order ): bool {
		$acquired_lock = $this->acquire_order_payment_lock_value( $order );

		if ( false === $acquired_lock ) {
			return true;
		}

		$this->owned_payment_locks[ $this->get_order_payment_lock_owner_key( $order ) ] = $acquired_lock;
		return false;
	}

	/**
	 * Acquires the payment lock and returns the value written.
	 *
	 * @since 11.0.0
	 *
	 * @param WC_Order $order The order that is being paid.
	 * @return string|false The owned lock value, or false when the lock could not be acquired.
	 */
	public function acquire_order_payment_lock( WC_Order $order ) {
		// A clone keeps the caller's unsaved changes intact.
		$payment_lock_order = clone $order;
		if ( $this->lock_order_payment( $payment_lock_order ) ) {
			return false;
		}

		// A subclass override may not record an owner; fail closed.
		$owner_key = $this->get_order_payment_lock_owner_key( $payment_lock_order );
		if ( ! isset( $this->owned_payment_locks[ $owner_key ] ) ) {
			WC_Stripe_Logger::error(
				'Stripe: an order helper reported a successful payment-lock acquisition without recording an owner.',
				[
					'order_id'     => $payment_lock_order->get_id(),
					'helper_class' => get_class( $this ),
				]
			);
			return false;
		}

		return $this->owned_payment_locks[ $owner_key ];
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * Releases a lock this instance acquired; otherwise it only clears an expired lock.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public function unlock_order_payment( WC_Order $order ): void {
		$owner_key = $this->get_order_payment_lock_owner_key( $order );

		if ( isset( $this->owned_payment_locks[ $owner_key ] ) ) {
			$expected_lock = $this->owned_payment_locks[ $owner_key ];
			unset( $this->owned_payment_locks[ $owner_key ] );
			$this->unlock_order_payment_if_owned( $order, $expected_lock );
			return;
		}

		// Without a token, only an expired lock may be cleared. Claim the row first.
		$unlock_guard = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();
		if ( ! $this->claim_order_payment_lock_owner( $order, $unlock_guard ) ) {
			return;
		}

		$payment_lock_order = clone $order;
		$current_lock       = $this->get_order_existing_payment_lock( $payment_lock_order );
		$lock_parts         = is_scalar( $current_lock ) ? explode( '|', (string) $current_lock, 2 ) : [];
		$lock_expiry        = isset( $lock_parts[0] ) && ctype_digit( $lock_parts[0] ) ? (int) $lock_parts[0] : 0;

		if ( null !== $current_lock && ( ! is_scalar( $current_lock ) || 0 === $lock_expiry || time() <= $lock_expiry ) ) {
			$this->release_order_payment_lock_owner( $payment_lock_order, $unlock_guard );
			return;
		}

		try {
			$payment_lock_order->delete_meta_data( self::META_STRIPE_LOCK_PAYMENT );
			$payment_lock_order->save_meta_data();
		} finally {
			$this->release_order_payment_lock_owner( $payment_lock_order, $unlock_guard );
		}
	}

	/**
	 * Unlocks an order only while the lock still holds the caller's value.
	 *
	 * @since 11.0.0
	 *
	 * @param WC_Order $order         The order that is being unlocked.
	 * @param string   $expected_lock Exact lock value returned by acquire_order_payment_lock().
	 */
	public function unlock_order_payment_if_owned( WC_Order $order, string $expected_lock ): void {
		if ( '' === $expected_lock ) {
			return;
		}

		$owner_key = $this->get_order_payment_lock_owner_key( $order );
		if ( isset( $this->owned_payment_locks[ $owner_key ] ) && $this->owned_payment_locks[ $owner_key ] === $expected_lock ) {
			unset( $this->owned_payment_locks[ $owner_key ] );
		}

		// A release guard blocks other workers until the meta is deleted.
		$release_guard = $this->replace_order_payment_lock_owner_if_owned( $order, $expected_lock );
		if ( false === $release_guard ) {
			return;
		}

		$payment_lock_order = clone $order;

		try {
			$current_lock = $this->get_order_existing_payment_lock( $payment_lock_order );

			if ( is_scalar( $current_lock ) && (string) $current_lock === $expected_lock ) {
				$payment_lock_order->delete_meta_data( self::META_STRIPE_LOCK_PAYMENT );
				$payment_lock_order->save_meta_data();
			}
		} finally {
			$this->release_order_payment_lock_owner( $payment_lock_order, $release_guard );
		}
	}

	/**
	 * Checks whether the owner row and the order meta both hold the caller's lock value.
	 *
	 * @since 11.0.0
	 *
	 * @param WC_Order $order         The order whose payment lock is being checked.
	 * @param string   $expected_lock Exact lock value returned by acquire_order_payment_lock().
	 * @return bool
	 */
	public function is_order_payment_lock_owned( WC_Order $order, string $expected_lock ): bool {
		if ( '' === $expected_lock || ! $this->has_order_payment_lock_owner( $order, $expected_lock ) ) {
			return false;
		}

		$current_lock = $this->get_order_existing_payment_lock( clone $order );

		// Re-check the row after the meta read.
		return is_scalar( $current_lock )
			&& (string) $current_lock === $expected_lock
			&& $this->has_order_payment_lock_owner( $order, $expected_lock );
	}

	/**
	 * Extends the caller's payment lock by 5 minutes with no unlocked gap.
	 *
	 * @since 11.0.0
	 *
	 * @param WC_Order $order         The order whose payment lock is being renewed.
	 * @param string   $expected_lock Exact lock value currently owned by the caller.
	 * @return string|false The new lock value, or false when the caller no longer owns the lock.
	 */
	public function renew_order_payment_lock_if_owned( WC_Order $order, string $expected_lock ) {
		if ( '' === $expected_lock || 0 >= $order->get_id() ) {
			return false;
		}

		$renewed_lock_expiry = time() + 5 * MINUTE_IN_SECONDS;
		$renewed_lock        = $renewed_lock_expiry . '|' . wp_generate_uuid4();

		// The owner row moves first; the meta follows.
		if ( ! $this->replace_order_payment_lock_owner_value_if_owned( $order, $expected_lock, $renewed_lock ) ) {
			return false;
		}

		$payment_lock_order = clone $order;
		$renewed            = false;

		try {
			$current_lock = $this->get_order_existing_payment_lock( $payment_lock_order );

			if ( ! is_scalar( $current_lock ) || (string) $current_lock !== $expected_lock ) {
				return false;
			}

			$payment_lock_order->update_meta_data( self::META_STRIPE_LOCK_PAYMENT, $renewed_lock );
			$payment_lock_order->save_meta_data();

			$persisted_lock = $this->get_order_existing_payment_lock( $payment_lock_order );
			if ( time() > $renewed_lock_expiry || ! is_scalar( $persisted_lock ) || (string) $persisted_lock !== $renewed_lock || ! $this->has_order_payment_lock_owner( $payment_lock_order, $renewed_lock ) ) {
				return false;
			}

			$this->owned_payment_locks[ $this->get_order_payment_lock_owner_key( $payment_lock_order ) ] = $renewed_lock;
			$renewed = true;

			return $renewed_lock;
		} finally {
			if ( ! $renewed ) {
				$this->release_order_payment_lock_owner( $payment_lock_order, $renewed_lock );
			}
		}
	}

	/**
	 * Claims the owner row, then writes the matching lock meta.
	 *
	 * @param WC_Order $order The order whose payment lock is being acquired.
	 * @return string|false The lock value written, or false when another worker holds it.
	 */
	private function acquire_order_payment_lock_value( WC_Order $order ) {
		$new_lock_expiry = time() + 5 * MINUTE_IN_SECONDS;
		$new_lock        = $new_lock_expiry . '|' . wp_generate_uuid4();

		if ( ! $this->claim_order_payment_lock_owner( $order, $new_lock ) ) {
			return false;
		}

		$acquired = false;

		try {
			// Legacy locks have no owner row.
			if ( $this->is_order_payment_locked( $order ) ) {
				return false;
			}

			$order->update_meta_data( self::META_STRIPE_LOCK_PAYMENT, $new_lock );
			$order->save_meta_data();

			$persisted_lock = $this->get_order_existing_payment_lock( $order );
			if ( time() > $new_lock_expiry || ! is_scalar( $persisted_lock ) || (string) $persisted_lock !== $new_lock || ! $this->has_order_payment_lock_owner( $order, $new_lock ) ) {
				return false;
			}

			$acquired = true;
			return $new_lock;
		} finally {
			if ( ! $acquired ) {
				$this->release_order_payment_lock_owner( $order, $new_lock );
			}
		}
	}

	/**
	 * Claims the owner row when it is missing or expired.
	 *
	 * @param WC_Order $order    The order whose payment lock is being acquired.
	 * @param string   $new_lock Owner value to claim.
	 * @return bool
	 */
	private function claim_order_payment_lock_owner( WC_Order $order, string $new_lock ): bool {
		global $wpdb;

		if ( 0 >= $order->get_id() ) {
			return false;
		}

		$option_name = $this->get_order_payment_lock_owner_option_name( $order );

		// INSERT IGNORE on the unique key picks one worker; add_option() may update instead.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)',
				$wpdb->options,
				$option_name,
				$new_lock,
				'no'
			)
		);

		if ( 1 === $inserted ) {
			return true;
		}

		$existing_owner = $this->get_order_payment_lock_owner( $order );
		$owner_parts    = is_string( $existing_owner ) ? explode( '|', $existing_owner, 2 ) : [];
		$is_valid_owner = 2 === count( $owner_parts ) && ctype_digit( $owner_parts[0] ) && 0 < (int) $owner_parts[0] && '' !== $owner_parts[1];

		// An unreadable row cannot be proven expired.
		if ( ! $is_valid_owner ) {
			WC_Stripe_Logger::error(
				'Stripe: cannot acquire an order payment lock because its owner value is invalid.',
				[
					'order_id'           => $order->get_id(),
					'payment_lock_owner' => $existing_owner,
				]
			);
			return false;
		}

		if ( time() <= (int) $owner_parts[0] ) {
			return false;
		}

		// Take over the expired row unless another worker already did.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET option_value = %s, autoload = %s WHERE option_name = %s AND CAST( option_value AS BINARY ) = CAST( %s AS BINARY )',
				$wpdb->options,
				$new_lock,
				'no',
				$option_name,
				(string) $existing_owner
			)
		);

		if ( 1 === $updated ) {
			return true;
		}

		return false;
	}

	/**
	 * Swaps an owned owner row for a temporary release guard.
	 *
	 * @param WC_Order $order         The order whose payment lock is being released.
	 * @param string   $expected_lock Owner value being released.
	 * @return string|false The guard value, or false when the row is no longer owned.
	 */
	private function replace_order_payment_lock_owner_if_owned( WC_Order $order, string $expected_lock ) {
		if ( 0 >= $order->get_id() || '' === $expected_lock ) {
			return false;
		}

		$release_guard = ( time() + 5 * MINUTE_IN_SECONDS ) . '|' . wp_generate_uuid4();

		return $this->replace_order_payment_lock_owner_value_if_owned( $order, $expected_lock, $release_guard )
			? $release_guard
			: false;
	}

	/**
	 * Replaces the owner row value only while it still holds the expected value.
	 *
	 * @param WC_Order $order            The order whose owner row is being replaced.
	 * @param string   $expected_lock    Current owner value.
	 * @param string   $replacement_lock New owner value.
	 * @return bool
	 */
	private function replace_order_payment_lock_owner_value_if_owned( WC_Order $order, string $expected_lock, string $replacement_lock ): bool {
		global $wpdb;

		if ( 0 >= $order->get_id() || '' === $expected_lock || '' === $replacement_lock ) {
			return false;
		}

		$option_name = $this->get_order_payment_lock_owner_option_name( $order );
		$updated     = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET option_value = %s, autoload = %s WHERE option_name = %s AND CAST( option_value AS BINARY ) = CAST( %s AS BINARY )',
				$wpdb->options,
				$replacement_lock,
				'no',
				$option_name,
				$expected_lock
			)
		);

		if ( 1 !== $updated ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether the owner row holds the expected value.
	 *
	 * @param WC_Order $order         The order whose owner row is being checked.
	 * @param string   $expected_lock Owner value.
	 * @return bool
	 * @phpstan-impure Reads mutable database state.
	 */
	private function has_order_payment_lock_owner( WC_Order $order, string $expected_lock ): bool {
		$current_owner = $this->get_order_payment_lock_owner( $order );

		return is_string( $current_owner ) && $current_owner === $expected_lock;
	}

	/**
	 * Reads the owner row, bypassing the options cache.
	 *
	 * @param WC_Order $order The order whose owner row is being read.
	 * @return string|null
	 */
	private function get_order_payment_lock_owner( WC_Order $order ): ?string {
		global $wpdb;

		if ( 0 >= $order->get_id() ) {
			return null;
		}

		$owner = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM %i WHERE option_name = %s',
				$wpdb->options,
				$this->get_order_payment_lock_owner_option_name( $order )
			)
		);

		return is_string( $owner ) ? $owner : null;
	}

	/**
	 * Deletes the owner row only while it still holds the expected value.
	 *
	 * @param WC_Order $order         The order whose owner row is being released.
	 * @param string   $expected_lock Owner value.
	 * @return void
	 */
	private function release_order_payment_lock_owner( WC_Order $order, string $expected_lock ): void {
		global $wpdb;

		$option_name = $this->get_order_payment_lock_owner_option_name( $order );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name = %s AND CAST( option_value AS BINARY ) = CAST( %s AS BINARY )',
				$wpdb->options,
				$option_name,
				$expected_lock
			)
		);
	}

	/**
	 * Deletes expired payment-lock owner rows that no later lock reclaimed.
	 *
	 * Rows are read in batches by option_id. A run stops after a fixed number
	 * of batches so a large backlog cannot hold the queue.
	 *
	 * @since 11.0.0
	 *
	 * @return void
	 */
	public static function sweep_expired_payment_lock_owners(): void {
		global $wpdb;

		$deleted        = 0;
		$now            = time();
		$last_option_id = 0;

		for ( $batch = 0; $batch < self::PAYMENT_LOCK_OWNER_SWEEP_MAX_BATCHES; $batch++ ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT option_id, option_name, option_value FROM %i WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id ASC LIMIT %d',
					$wpdb->options,
					$wpdb->esc_like( self::OPTION_STRIPE_LOCK_PAYMENT_OWNER_PREFIX ) . '%',
					$last_option_id,
					self::PAYMENT_LOCK_OWNER_SWEEP_BATCH_SIZE
				),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$last_option_id = (int) $row['option_id'];
				$parts          = explode( '|', (string) $row['option_value'], 2 );

				// A row without a leading timestamp cannot be proven expired.
				if ( ! ctype_digit( $parts[0] ) || $now <= (int) $parts[0] ) {
					continue;
				}

				// Owner-checked, so a row a worker just reclaimed is left alone.
				$deleted += (int) $wpdb->query(
					$wpdb->prepare(
						'DELETE FROM %i WHERE option_name = %s AND CAST( option_value AS BINARY ) = CAST( %s AS BINARY )',
						$wpdb->options,
						$row['option_name'],
						$row['option_value']
					)
				);
			}

			if ( count( $rows ) < self::PAYMENT_LOCK_OWNER_SWEEP_BATCH_SIZE ) {
				break;
			}
		}

		if ( 0 < $deleted ) {
			WC_Stripe_Logger::debug( 'Deleted expired payment lock owner rows.', [ 'deleted' => $deleted ] );
		}
	}

	/**
	 * Schedules the daily sweep of expired payment-lock owner rows.
	 *
	 * @since 11.0.0
	 *
	 * @return void
	 */
	public static function maybe_schedule_payment_lock_owner_sweep(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::PAYMENT_LOCK_OWNER_SWEEP_ACTION, null ) ) {
			return;
		}

		// An hour after the database cache cleanup.
		as_schedule_recurring_action( strtotime( 'tomorrow 02:00' ), DAY_IN_SECONDS, self::PAYMENT_LOCK_OWNER_SWEEP_ACTION, [], 'woocommerce-gateway-stripe' );
	}

	/**
	 * Unschedules the daily sweep of expired payment-lock owner rows.
	 *
	 * @since 11.0.0
	 *
	 * @return void
	 */
	public static function unschedule_payment_lock_owner_sweep(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::PAYMENT_LOCK_OWNER_SWEEP_ACTION, [], 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns the owner row option name for an order.
	 *
	 * @param WC_Order $order The order whose owner row is addressed.
	 * @return string
	 */
	private function get_order_payment_lock_owner_option_name( WC_Order $order ): string {
		return self::OPTION_STRIPE_LOCK_PAYMENT_OWNER_PREFIX . $order->get_id();
	}

	/**
	 * Returns the in-memory key for an order's owned lock, unique per site.
	 *
	 * @param WC_Order $order The order whose owned lock is addressed.
	 * @return string
	 */
	private function get_order_payment_lock_owner_key( WC_Order $order ): string {
		global $wpdb;

		return $wpdb->options . ':' . $order->get_id();
	}

	/**
	 * Retrieves the existing lock for an order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to retrieve the lock for
	 * @return mixed
	 */
	public function get_order_existing_payment_lock( WC_Order $order ) {
		$order->read_meta_data( true );
		return $order->get_meta( self::META_STRIPE_LOCK_PAYMENT, true );
	}

	/**
	 * Locks an order for refund processing for 5 minutes.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order  The order that is being refunded.
	 * @return bool            A flag that indicates whether the order is already locked.
	 */
	public function lock_order_refund( WC_Order $order ): bool {
		if ( $this->is_order_refund_locked( $order ) ) {
			// If the order is already locked, return true.
			return true;
		}

		$new_lock = time() + 5 * MINUTE_IN_SECONDS;

		$order->update_meta_data( self::META_STRIPE_LOCK_REFUND, $new_lock );
		$order->save_meta_data();

		return false;
	}

	/**
	 * Retrieves the existing refund lock for an order.
	 *
	 * @since 10.0.0
	 *
	 * @param $order WC_Order The order to retrieve the lock for
	 * @return mixed
	 */
	public function get_order_existing_refund_lock( WC_Order $order ) {
		$order->read_meta_data( true );
		return $order->get_meta( self::META_STRIPE_LOCK_REFUND, true );
	}

	/**
	 * Unlocks an order for processing refund.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public function unlock_order_refund( WC_Order $order ): void {
		$order->delete_meta_data( self::META_STRIPE_LOCK_REFUND );
		$order->save_meta_data();
	}

	/**
	 * Checks if an order is locked for payment processing.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to check the lock for
	 * @return bool
	 */
	protected function is_order_payment_locked( WC_Order $order ): bool {
		$existing_lock = $this->get_order_existing_payment_lock( $order );

		// An unparseable lock cannot be proven expired.
		if ( null !== $existing_lock && ! is_scalar( $existing_lock ) ) {
			return true;
		}

		if ( $existing_lock ) {
			$parts      = explode( '|', (string) $existing_lock ); // Format is: "{expiry_timestamp}" or "{expiry_timestamp}|{owner_token}".
			$expiration = (int) $parts[0];

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if an order is locked for refund.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order to check the lock for
	 * @return bool
	 */
	protected function is_order_refund_locked( WC_Order $order ): bool {
		$existing_lock = $this->get_order_existing_refund_lock( $order );
		if ( $existing_lock ) {
			$expiration = (int) $existing_lock;

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Helper function to get order meta data. The goal of the function is to reduce boilerplate in the helper due to `null` checks everywhere.
	 *
	 * @param WC_Order|null $order The order to get meta for.
	 * @param string $key The meta key to get.
	 * @return false|string|null
	 */
	protected function get_order_meta( ?WC_Order $order, string $key ) {
		if ( null === $order ) {
			return false;
		}

		return $order->get_meta( $key, true );
	}

	/**
	 * Helper function to update order meta data. The goal of the function is to reduce boilerplate in the helper due to `null` checks everywhere.
	 *
	 * @param WC_Order|null $order The order to update meta for.
	 * @param string $key The meta key to update.
	 * @param mixed $value The meta value to set.
	 * @return false|void
	 */
	protected function update_order_meta( ?WC_Order $order, string $key, $value ) {
		if ( null === $order ) {
			return false;
		}

		$order->update_meta_data( $key, $value );
	}

	/**
	 * Helper function to delete an order meta data. The goal of the function is to reduce boilerplate in the helper due to `null` checks everywhere.
	 *
	 * @param WC_Order|null $order The order to delete meta for.
	 * @param string $key The meta key to delete.
	 * @return false|void
	 */
	protected function delete_order_meta( ?WC_Order $order, string $key ) {
		if ( null === $order ) {
			return false;
		}

		$order->delete_meta_data( $key );
	}
}
