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
	 * Meta key for Stripe currency.
	 *
	 * @string
	 */
	private const META_STRIPE_CURRENCY = '_stripe_currency';

	/**
	 * Meta key for Stripe fee.
	 *
	 * @string
	 */
	private const META_STRIPE_FEE = '_stripe_fee';

	/**
	 * Meta key for Stripe fee (legacy version).
	 *
	 * @string
	 */
	private const LEGACY_META_STRIPE_FEE = 'Stripe Fee';

	/**
	 * Meta key for Stripe net.
	 *
	 * @string
	 */
	private const META_STRIPE_NET = '_stripe_net';

	/**
	 * Meta key for Stripe net (legacy version).
	 *
	 * @string
	 */
	private const LEGACY_META_STRIPE_NET = 'Net Revenue From Stripe';

	/**
	 * Meta key for Stripe source ID.
	 *
	 * @string
	 */
	private const META_STRIPE_SOURCE_ID = '_stripe_source_id';

	/**
	 * Meta key for Stripe charge ID.
	 *
	 * @string
	 */
	private const META_STRIPE_CHARGE_ID = '_stripe_charge_id';

	/**
	 * Meta key for Stripe refund ID.
	 *
	 * @string
	 */
	private const META_STRIPE_REFUND_ID = '_stripe_refund_id';

	/**
	 * Meta key for Stripe intent ID.
	 *
	 * @string
	 */
	private const META_STRIPE_INTENT_ID = '_stripe_intent_id';

	/**
	 * Meta key for Stripe setup intent ID.
	 */
	private const META_STRIPE_SETUP_INTENT = '_stripe_setup_intent';

	/**
	 * Meta key for payment awaiting action.
	 *
	 * @string
	 */
	private const META_STRIPE_PAYMENT_AWAITING_ACTION = '_stripe_payment_awaiting_action';

	/**
	 * Gets the Stripe currency for order.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 * @return string $currency
	 */
	public static function get_stripe_currency( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_STRIPE_CURRENCY, true );
	}

	/**
	 * Updates the Stripe currency for order.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 * @param string $currency
	 */
	public static function update_stripe_currency( $order, $currency ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_STRIPE_CURRENCY, $currency );
	}

	/**
	 * Gets the Stripe fee for order. With legacy check.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 * @return string $amount
	 */
	public static function get_stripe_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_STRIPE_FEE, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::META_STRIPE_FEE, true );

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
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 * @param float  $amount
	 */
	public static function update_stripe_fee( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_STRIPE_FEE, $amount );
	}

	/**
	 * Deletes the Stripe fee for order.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 */
	public static function delete_stripe_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->delete_meta_data( self::META_STRIPE_FEE );
		$order->delete_meta_data( self::LEGACY_META_STRIPE_FEE );
	}

	/**
	 * Gets the Stripe net for order. With legacy check.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 * @return string $amount
	 */
	public static function get_stripe_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_STRIPE_NET, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::META_STRIPE_NET, true );

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
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 * @param float  $amount
	 */
	public static function update_stripe_net( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_STRIPE_NET, $amount );
	}

	/**
	 * Deletes the Stripe net for order.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order
	 */
	public static function delete_stripe_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->delete_meta_data( self::META_STRIPE_NET );
		$order->delete_meta_data( self::LEGACY_META_STRIPE_NET );
	}

	/**
	 * Gets the order by Stripe source ID.
	 *
	 * @since 9.9.0
	 *
	 * @param string $source_id
	 */
	public static function get_order_by_source_id( $source_id ) {
		return self::get_by_meta( self::META_STRIPE_SOURCE_ID, $source_id );
	}

	/**
	 * Gets the order by Stripe charge ID.
	 *
	 * @since 9.9.0
	 *
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id( $charge_id ) {
		return self::get_by_meta( self::META_STRIPE_CHARGE_ID, $charge_id );
	}

	/**
	 * Gets the order by Stripe refund ID.
	 *
	 * @since 9.9.0
	 *
	 * @param string $refund_id
	 */
	public static function get_order_by_refund_id( $refund_id ) {
		return self::get_by_meta( self::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Gets the order by Stripe PaymentIntent ID.
	 *
	 * @since 9.9.0
	 *
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_intent_id( $intent_id ) {
		return self::get_by_meta( self::META_STRIPE_INTENT_ID, $intent_id );
	}

	/**
	 * Gets the order by Stripe SetupIntent ID.
	 *
	 * @since 9.9.0
	 *
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_setup_intent_id( $intent_id ) {
		return self::get_by_meta( self::META_STRIPE_SETUP_INTENT, $intent_id );
	}

	/**
	 * Adds payment intent id and order note to order if payment intent is not already saved
	 *
	 * @since 9.9.0
	 *
	 * @param $payment_intent_id
	 * @param $order WC_Order
	 */
	public static function add_payment_intent_to_order( $payment_intent_id, $order ) {
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
	 * @since 9.9.0
	 *
	 * @param WC_Order $order The order to add the metadata to.
	 * @param bool     $save  Whether to save the order after adding the metadata.
	 *
	 * @return void
	 */
	public static function set_payment_awaiting_action( $order, $save = true ) {
		$order->update_meta_data( self::META_STRIPE_PAYMENT_AWAITING_ACTION, wc_bool_to_string( true ) );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Checks if the order is awaiting action for payment.
	 *
	 * @since 9.9.0
	 *
	 * @param $order
	 * @return bool
	 */
	public static function is_payment_awaiting_action( $order ) {
		return wc_string_to_bool( $order->get_meta( self::META_STRIPE_PAYMENT_AWAITING_ACTION, true ) );
	}

	/**
	 * Removes the metadata from the order that was used to indicate that the payment was awaiting action.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order The order to remove the metadata from.
	 * @param bool     $save  Whether to save the order after removing the metadata.
	 *
	 * @return void
	 */
	public static function remove_payment_awaiting_action( $order, $save = true ) {
		$order->delete_meta_data( self::META_STRIPE_PAYMENT_AWAITING_ACTION );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Returns the payment intent or setup intent ID from a given order object.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order The order to fetch the Stripe intent from.
	 *
	 * @return string|bool  The intent ID if found, false otherwise.
	 */
	public static function get_intent_id_from_order( $order ) {
		$intent_id = $order->get_meta( self::META_STRIPE_INTENT_ID );

		if ( ! $intent_id ) {
			$intent_id = $order->get_meta( self::META_STRIPE_SETUP_INTENT );
		}

		return $intent_id ?? false;
	}

	/**
	 * Get owner details.
	 *
	 * @since 9.9.0
	 *
	 * @param object $order
	 * @return object $details
	 */
	public static function get_owner_details( $order ) {
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
	 * @since 9.9.0
	 *
	 * @param WC_Order      $order                 The order to check.
	 * @param object|string $intent                The payment intent to check, can either be an object or an intent ID.
	 * @param string|null   $selected_payment_type The selected payment type, which is generally applicable for updates. If null, we will use the stored payment type for the order.
	 *
	 * @throws Exception Throws an exception if the intent is not valid for the order.
	 */
	public static function validate_intent_for_order( $order, $intent, ?string $selected_payment_type = null ): void {
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
			$selected_payment_type = $order->get_meta( '_stripe_upe_payment_type', true );
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
	 * @since 9.9.0
	 *
	 * @param $order WC_Order The order to check.
	 * @return bool
	 */
	public static function is_stripe_gateway_order( $order ) {
		return WC_Gateway_Stripe::ID === substr( (string) $order->get_payment_method(), 0, 6 );
	}

	/**
	 * Validates that the order meets the minimum order amount
	 * set by Stripe.
	 *
	 * @since 9.9.0
	 * @param WC_Order $order
	 */
	public static function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Locks an order for payment intent processing for 5 minutes.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order  The order that is being paid.
	 * @return bool            A flag that indicates whether the order is already locked.
	 */
	public static function lock_order_payment( $order ) {
		if ( self::is_order_payment_locked( $order ) ) {
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
	 * @since 9.9.0
	 *
	 * @since 4.2
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public static function unlock_order_payment( $order ) {
		$order->delete_meta_data( '_stripe_lock_payment' );
		$order->save_meta_data();
	}

	/**
	 * Retrieves the existing lock for an order.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order The order to retrieve the lock for
	 * @return mixed
	 */
	public static function get_order_existing_payment_lock( $order ) {
		$order->read_meta_data( true );
		return $order->get_meta( '_stripe_lock_payment', true );
	}

	/**
	 * Checks if an order is locked for payment processing.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order The order to check the lock for
	 * @return bool
	 */
	protected static function is_order_payment_locked( $order ) {
		$existing_lock = self::get_order_existing_payment_lock( $order );
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
	 * Locks an order for refund processing for 5 minutes.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order  The order that is being refunded.
	 * @return bool            A flag that indicates whether the order is already locked.
	 */
	public static function lock_order_refund( $order ) {
		$existing_lock = self::get_order_existing_refund_lock( $order );
		if ( $existing_lock ) {
			$expiration = (int) $existing_lock;

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		$new_lock = time() + 5 * MINUTE_IN_SECONDS;

		$order->update_meta_data( '_stripe_lock_refund', $new_lock );
		$order->save_meta_data();

		return false;
	}

	/**
	 * Retrieves the existing refund lock for an order.
	 *
	 * @since 9.9.0
	 *
	 * @param $order WC_Order The order to retrieve the lock for
	 * @return mixed
	 */
	public static function get_order_existing_refund_lock( $order ) {
		$order->read_meta_data( true );
		return $order->get_meta( '_stripe_lock_refund', true );
	}

	/**
	 * Unlocks an order for processing refund.
	 *
	 * @since 9.9.0
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 */
	public static function unlock_order_refund( $order ) {
		$order->delete_meta_data( '_stripe_lock_refund' );
		$order->save_meta_data();
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
			if ( self::META_STRIPE_CHARGE_ID === $meta_key ) {
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
