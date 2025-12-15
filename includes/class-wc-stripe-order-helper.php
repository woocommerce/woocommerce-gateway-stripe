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
	 * Singleton instance of the class.
	 *
	 * @var null|WC_Stripe_Order_Helper
	 */
	private static ?WC_Stripe_Order_Helper $instance = null;

	/**
	 * Gets the singleton instance of the class.
	 *
	 * @return WC_Stripe_Order_Helper
	 */
	public static function get_instance(): ?self {
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
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_CURRENCY, true );
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
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_STRIPE_FEE, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::LEGACY_META_STRIPE_FEE, true );

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
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_STRIPE_NET, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::LEGACY_META_STRIPE_NET, true );

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
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_SOURCE_ID, true );
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
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_REFUND_ID, true );
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
	 * Gets the Stripe intent for order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_intent_id( ?WC_Order $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_INTENT_ID, true );
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
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_SETUP_INTENT, true );
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
	 * Gets the Stripe customer ID for an order.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order|null $order
	 * @return false|string|null
	 */
	public function get_stripe_customer_id( ?WC_Order $order = null ) {
		if ( null === $order ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_CUSTOMER_ID, true );
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
		if ( null === $order ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_CARD_ID, true );
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
		if ( null === $order ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_UPE_PAYMENT_TYPE, true );
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
		if ( null === $order ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_UPE_WAITING_FOR_REDIRECT, true );
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
		if ( null === $order ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_UPE_REDIRECT_PROCESSED, true );
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
			$intent_id = $intent;
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
		if ( $this->is_order_payment_locked( $order ) ) {
			// If the order is already locked, return true.
			return true;
		}

		$new_lock = ( time() + 5 * MINUTE_IN_SECONDS );

		$order->update_meta_data( '_stripe_lock_payment', $new_lock );
		$order->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public function unlock_order_payment( WC_Order $order ): void {
		$order->delete_meta_data( '_stripe_lock_payment' );
		$order->save_meta_data();
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
		return $order->get_meta( '_stripe_lock_payment', true );
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

		$order->update_meta_data( '_stripe_lock_refund', $new_lock );
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
		return $order->get_meta( '_stripe_lock_refund', true );
	}

	/**
	 * Unlocks an order for processing refund.
	 *
	 * @since 10.0.0
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public function unlock_order_refund( WC_Order $order ): void {
		$order->delete_meta_data( '_stripe_lock_refund' );
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
		if ( $existing_lock ) {
			$parts      = explode( '|', $existing_lock ); // Format is: "{expiry_timestamp}"
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
