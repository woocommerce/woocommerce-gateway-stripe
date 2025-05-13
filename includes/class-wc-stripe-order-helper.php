<?php

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Order_Helper class.
 */
class WC_Stripe_Order_Helper {
	/**
	 * Gets the Stripe currency for order.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 * @return string $currency
	 */
	public static function get_stripe_currency( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( WC_Stripe_Order_Metas::META_STRIPE_CURRENCY, true );
	}

	/**
	 * Updates the Stripe currency for order.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 * @param string $currency
	 */
	public static function update_stripe_currency( $order, $currency ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( WC_Stripe_Order_Metas::META_STRIPE_CURRENCY, $currency );
	}

	/**
	 * Gets the Stripe fee for order. With legacy check.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 * @return string $amount
	 */
	public static function get_stripe_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( WC_Stripe_Order_Metas::META_STRIPE_FEE, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( WC_Stripe_Order_Metas::META_STRIPE_FEE, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_stripe_fee( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe fee for order.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 * @param float  $amount
	 */
	public static function update_stripe_fee( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( WC_Stripe_Order_Metas::META_STRIPE_FEE, $amount );
	}

	/**
	 * Deletes the Stripe fee for order.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 */
	public static function delete_stripe_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->delete_meta_data( WC_Stripe_Order_Metas::META_STRIPE_FEE );
		$order->delete_meta_data( WC_Stripe_Order_Metas::LEGACY_META_STRIPE_FEE );
	}

	/**
	 * Gets the Stripe net for order. With legacy check.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 * @return string $amount
	 */
	public static function get_stripe_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( WC_Stripe_Order_Metas::META_STRIPE_NET, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( WC_Stripe_Order_Metas::META_STRIPE_NET, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_stripe_net( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe net for order.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 * @param float  $amount
	 */
	public static function update_stripe_net( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( WC_Stripe_Order_Metas::META_STRIPE_NET, $amount );
	}

	/**
	 * Deletes the Stripe net for order.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order
	 */
	public static function delete_stripe_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->delete_meta_data( WC_Stripe_Order_Metas::META_STRIPE_NET );
		$order->delete_meta_data( WC_Stripe_Order_Metas::LEGACY_META_STRIPE_NET );
	}

	/**
	 * Gets the order by Stripe source ID.
	 *
	 * @since 9.5.0
	 *
	 * @param string $source_id
	 */
	public static function get_order_by_source_id( $source_id ) {
		return self::get_by_meta( WC_Stripe_Order_Metas::META_STRIPE_SOURCE_ID, $source_id );
	}

	/**
	 * Gets the order by Stripe charge ID.
	 *
	 * @since 9.5.0
	 *
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id( $charge_id ) {
		return self::get_by_meta( WC_Stripe_Order_Metas::META_STRIPE_CHARGE_ID, $charge_id );
	}

	/**
	 * Gets the order by Stripe refund ID.
	 *
	 * @since 9.5.0
	 *
	 * @param string $refund_id
	 */
	public static function get_order_by_refund_id( $refund_id ) {
		return self::get_by_meta( WC_Stripe_Order_Metas::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Gets the order by Stripe PaymentIntent ID.
	 *
	 * @since 9.5.0
	 *
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_intent_id( $intent_id ) {
		return self::get_by_meta( WC_Stripe_Order_Metas::META_STRIPE_INTENT_ID, $intent_id );
	}

	/**
	 * Gets the order by Stripe SetupIntent ID.
	 *
	 * @since 9.5.0
	 *
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_setup_intent_id( $intent_id ) {
		return self::get_by_meta( WC_Stripe_Order_Metas::META_STRIPE_SETUP_INTENT, $intent_id );
	}

	/**
	 * Adds payment intent id and order note to order if payment intent is not already saved
	 *
	 * @since 9.5.0
	 *
	 * @param $payment_intent_id
	 * @param $order WC_Order
	 */
	public static function add_payment_intent_to_order( $payment_intent_id, $order ) {
		$old_intent_id = $order->get_meta( WC_Stripe_Order_Metas::META_STRIPE_INTENT_ID );

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

		$order->update_meta_data( WC_Stripe_Order_Metas::META_STRIPE_INTENT_ID, $payment_intent_id );
		$order->save();
	}

	/**
	 * Adds metadata to the order to indicate that the payment is awaiting action.
	 *
	 * This meta is primarily used to prevent orders from being cancelled by WooCommerce's hold stock settings.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order The order to add the metadata to.
	 * @param bool     $save  Whether to save the order after adding the metadata.
	 *
	 * @return void
	 */
	public static function set_payment_awaiting_action( $order, $save = true ) {
		$order->update_meta_data( WC_Stripe_Order_Metas::META_STRIPE_PAYMENT_AWAITING_ACTION, wc_bool_to_string( true ) );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Removes the metadata from the order that was used to indicate that the payment was awaiting action.
	 *
	 * @since 9.5.0
	 *
	 * @param WC_Order $order The order to remove the metadata from.
	 * @param bool     $save  Whether to save the order after removing the metadata.
	 *
	 * @return void
	 */
	public static function remove_payment_awaiting_action( $order, $save = true ) {
		$order->delete_meta_data( WC_Stripe_Order_Metas::META_STRIPE_PAYMENT_AWAITING_ACTION );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Queries for an order by a specific meta key and value.
	 *
	 * @param $meta_key string The meta key to search for.
	 * @param $meta_value string The meta value to search for.
	 * @return bool|WC_Order
	 */
	private static function get_by_meta( $meta_key, $meta_value ) {
		global $wpdb;

		if ( WC_Stripe_Woo_Compat_Utils::is_custom_orders_table_enabled() ) {
			$params = [ 'limit' => 1 ];
			// Check if the meta key is a transaction ID. If so, use the transaction ID to query the order, instead of the meta when HPOS is enabled.
			if ( WC_Stripe_Order_Metas::META_STRIPE_CHARGE_ID === $meta_key ) {
				$params['transaction_id'] = $meta_value;
			} else {
				$params['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => $meta_key,
						'value' => $meta_value,
					],
				];
			}

			$orders   = wc_get_orders( $params );
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $meta_value, $meta_key ) );
		}

		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! empty( $order ) && $order->get_status() !== OrderStatus::TRASH ) {
			return $order;
		}

		return false;
	}
}
