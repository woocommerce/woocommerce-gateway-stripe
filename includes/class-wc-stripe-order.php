<?php

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Order
 *
 * Wrapper for the original WC_Order class to allow custom getters and setter with the extension's specific metadata.
 */
class WC_Stripe_Order extends WC_Order {
	/**
	 * Meta key for the Stripe source ID.
	 *
	 * @var string
	 */
	const META_STRIPE_SOURCE_ID = '_stripe_source_id';

	/**
	 * Meta key for the Stripe charge ID.
	 *
	 * @var string
	 */
	const META_STRIPE_CHARGE_ID = '_transaction_id';

	/**
	 * Meta key for the Stripe refund ID.
	 *
	 * @var string
	 */
	const META_STRIPE_REFUND_ID = '_stripe_refund_id';

	/**
	 * Meta key for the Stripe Payment Intent ID.
	 *
	 * @var string
	 */
	const META_STRIPE_INTENT_ID = '_stripe_intent_id';

	/**
	 * Meta key for the Stripe Setup Intent ID.
	 *
	 * @var string
	 */
	const META_STRIPE_SETUP_INTENT = '_stripe_setup_intent';

	/**
	 * Meta key for the Stripe fee amount.
	 */
	const META_STRIPE_FEE = '_stripe_fee';

	/**
	 * Meta key for the Stripe net amount.
	 */
	const META_STRIPE_NET = '_stripe_net';

	/**
	 * Meta key for the Stripe currency.
	 *
	 * @var string
	 */
	const META_STRIPE_CURRENCY = '_stripe_currency';

	/**
	 * Meta key for the payment awaiting action flag.
	 *
	 * @var string
	 */
	const META_STRIPE_PAYMENT_AWAITING_ACTION = '_stripe_payment_awaiting_action';

	/**
	 * Meta key for the Stripe card brand.
	 *
	 * @var string
	 */
	const META_STRIPE_CARD_BRAND = '_stripe_card_brand';

	/**
	 * Meta key for the Stripe lock refund.
	 *
	 * @var string
	 */
	const META_STRIPE_LOCK_REFUND = '_stripe_lock_refund';

	/**
	 * Meta key for the Stripe lock payment.
	 *
	 * @var string
	 */
	const META_STRIPE_LOCK_PAYMENT = '_stripe_lock_payment';

	/**
	 * Meta key for the redirect processed flag.
	 *
	 * @var string
	 */
	const META_STRIPE_UPE_REDIRECT_PROCESSED = '_stripe_upe_redirect_processed';

	/**
	 * Meta key for the status before hold.
	 *
	 * @var string
	 */
	const META_STRIPE_STATUS_BEFORE_HOLD = '_stripe_status_before_hold';

	/**
	 * Meta key for the UPE waiting for redirect flag.
	 *
	 * @var string
	 */
	const META_STRIPE_UPE_WAITING_FOR_REDIRECT = '_stripe_upe_waiting_for_redirect';

	/**
	 * Meta key for the mandate ID.
	 *
	 * @var string
	 */
	const META_STRIPE_MANDATE_ID = '_stripe_mandate_id';

	/**
	 * Meta key for the customer ID.
	 *
	 * @var string
	 */
	const META_STRIPE_CUSTOMER_ID = '_stripe_customer_id';

	/**
	 * Meta key for the charge captured flag.
	 *
	 * @var string
	 */
	const META_STRIPE_CHARGE_CAPTURED = '_stripe_charge_captured';

	/**
	 * Meta key to identify whether the status is final.
	 *
	 * @var string
	 */
	const META_STRIPE_STATUS_FINAL = '_stripe_status_final';

	/**
	 * Meta key for the Multibanco data.
	 *
	 * @var string
	 */
	const META_STRIPE_MULTIBANCO = '_stripe_multibanco';

	/**
	 * Meta key for the UPE payment type.
	 *
	 * @var string
	 */
	const META_STRIPE_UPE_PAYMENT_TYPE = '_stripe_upe_payment_type';

	/**
	 * The lock refund expiration time.
	 *
	 * @var int
	 */
	const REFUND_LOCK_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * The lock payment expiration time.
	 *
	 * @var int
	 */
	const PAYMENT_LOCK_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Converts an order into WC_Stripe_Order if it is not already.
	 *
	 * @param $order WC_Stripe_Order|WC_Order Order object.
	 * @return WC_Stripe_Order
	 */
	public static function to_instance( $order ) {
		return $order instanceof WC_Stripe_Order ? $order : new self( $order );
	}

	/**
	 * Wrapper to create an order using the extension's custom WC_Stripe_Order class.
	 *
	 * @param $order_data array Order data.
	 * @return bool|WC_Stripe_Order
	 */
	public static function create( $order_data ) {
		$order = wc_create_order( $order_data );
		if ( ! $order ) {
			return false;
		}

		return self::to_instance( $order );
	}

	/**
	 * Wrapper to return an order using the extension's custom WC_Stripe_Order class.
	 *
	 * @param $order_id int Order ID.
	 * @return bool|WC_Stripe_Order
	 */
	public static function get_by_id( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return self::to_instance( $order );
	}

	/**
	 * Wrapper to get orders using the extension's custom WC_Stripe_Order class.
	 *
	 * @param $args array Arguments to pass to wc_get_orders.
	 * @return array|WC_Stripe_Order[]
	 */
	public static function query( $args ) {
		$orders = wc_get_orders( $args );
		if ( empty( $orders ) ) {
			return [];
		}

		return array_map(
			function ( $order ) {
				return self::to_instance( $order );
			},
			$orders
		);
	}

	/**
	 * Gets the order by Stripe source ID.
	 *
	 * @param string $source_id
	 */
	public static function get_by_source_id( $source_id ) {
		return self::get_by_meta( self::META_STRIPE_SOURCE_ID, $source_id );
	}

	/**
	 * Gets the order by Stripe charge ID.
	 *
	 * @param string $charge_id
	 */
	public static function get_by_charge_id( $charge_id ) {
		return self::get_by_meta( self::META_STRIPE_CHARGE_ID, $charge_id );
	}

	/**
	 * Gets the order by Stripe refund ID.
	 *
	 * @param string $refund_id
	 */
	public static function get_by_refund_id( $refund_id ) {
		return self::get_by_meta( self::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Gets the order by Stripe PaymentIntent ID.
	 *
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_by_intent_id( $intent_id ) {
		return self::get_by_meta( self::META_STRIPE_INTENT_ID, $intent_id );
	}

	/**
	 * Gets the order by Stripe SetupIntent ID.
	 *
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_by_setup_intent_id( $intent_id ) {
		return self::get_by_meta( self::META_STRIPE_SETUP_INTENT, $intent_id );
	}

	/**
	 * Get owner details.
	 *
	 * @return object $details
	 */
	public function get_owner_details() {
		$billing_first_name = $this->get_billing_first_name();
		$billing_last_name  = $this->get_billing_last_name();

		$details = [];

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = $this->get_billing_email();
		$phone = $this->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = $this->get_billing_address_1();
		$details['address']['line2']       = $this->get_billing_address_2();
		$details['address']['state']       = $this->get_billing_state();
		$details['address']['city']        = $this->get_billing_city();
		$details['address']['postal_code'] = $this->get_billing_postcode();
		$details['address']['country']     = $this->get_billing_country();

		return (object) apply_filters( 'wc_stripe_owner_details', $details, $this );
	}

	/**
	 * Validates that the order meets the minimum order amount
	 * set by Stripe.
	 *
	 * @throws WC_Stripe_Exception If the order does not meet the minimum amount.
	 */
	public function validate_minimum_amount() {
		if ( $this->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Adds payment intent id and order note to order if payment intent is not already saved
	 *
	 * @param $payment_intent_id string The payment intent id to add to the order.
	 */
	public function add_payment_intent_to_order( $payment_intent_id ) {
		$old_intent_id = $this->get_intent_id();
		if ( $old_intent_id === $payment_intent_id ) {
			return;
		}

		$this->add_order_note(
			sprintf(
			/* translators: $1%s payment intent ID */
				__( 'Stripe payment intent created (Payment Intent ID: %1$s)', 'woocommerce-gateway-stripe' ),
				$payment_intent_id
			)
		);

		$this->set_intent_id( $payment_intent_id );
		$this->save();
	}

	/**
	 * Gets the Stripe fee for order. With legacy check.
	 *
	 * @return string $amount
	 */
	public function get_fee() {
		$amount = $this->get_meta( self::META_STRIPE_FEE );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $this->get_meta( 'Stripe Fee' );

			// If found update to new name.
			if ( $amount ) {
				$this->set_fee( $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe fee for order.
	 *
	 * @param float $amount
	 */
	public function set_fee( $amount = 0.0 ) {
		$this->update_meta_data( self::META_STRIPE_FEE, $amount );
	}

	/**
	 * Deletes the Stripe fee for order.
	 */
	public function delete_fee() {
		$this->delete_meta_data( self::META_STRIPE_FEE );
		$this->delete_meta_data( 'Stripe Fee' );
	}

	/**
	 * Gets the Stripe net for order. With legacy check.
	 *
	 * @return string $amount
	 */
	public function get_net() {
		$amount = $this->get_meta( self::META_STRIPE_NET, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $this->get_meta( 'Net Revenue From Stripe', true );

			// If found update to new name.
			if ( $amount ) {
				$this->set_net( $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe net for order.
	 *
	 * @param float  $amount
	 */
	public function set_net( $amount = 0.0 ) {
		$this->update_meta_data( self::META_STRIPE_NET, $amount );
	}

	/**
	 * Deletes the Stripe net for order.
	 */
	public function delete_net() {
		$this->delete_meta_data( self::META_STRIPE_NET );
		$this->delete_meta_data( 'Net Revenue From Stripe' );
	}

	/**
	 * Gets the Stripe currency for order.
	 *
	 * @return string $currency
	 */
	public function get_stripe_currency() {
		return $this->get_meta( self::META_STRIPE_CURRENCY );
	}

	/**
	 * Updates the Stripe currency for order.
	 *
	 * @param string $currency
	 */
	public function set_stripe_currency( $currency ) {
		$this->update_meta_data( self::META_STRIPE_CURRENCY, $currency );
	}

	/**
	 * Adds metadata to the order to indicate that the payment is awaiting action.
	 *
	 * This meta is primarily used to prevent orders from being cancelled by WooCommerce's hold stock settings.
	 *
	 * @return void
	 */
	public function set_payment_awaiting_action( $save = true ) {
		$this->update_meta_data( self::META_STRIPE_PAYMENT_AWAITING_ACTION, wc_bool_to_string( true ) );

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Gets the metadata that indicates that the payment is awaiting action.
	 *
	 * @return bool Whether the payment is awaiting action.
	 */
	public function is_payment_awaiting_action() {
		return wc_string_to_bool( $this->get_meta( self::META_STRIPE_PAYMENT_AWAITING_ACTION ) );
	}

	/**
	 * Removes the metadata from the order that was used to indicate that the payment was awaiting action.
	 *
	 * @param bool     $save  Whether to save the order after removing the metadata.
	 *
	 * @return void
	 */
	public function remove_payment_awaiting_action( $save = true ) {
		$this->delete_meta_data( self::META_STRIPE_PAYMENT_AWAITING_ACTION );

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Set the preferred card brand.
	 *
	 * @param $brand string The brand to set.
	 * @return void
	 */
	public function set_card_brand( $brand ) {
		$this->update_meta_data( self::META_STRIPE_CARD_BRAND, $brand );
	}

	/**
	 * Get the preferred card brand.
	 *
	 * @return string
	 */
	public function get_card_brand() {
		return $this->get_meta( self::META_STRIPE_CARD_BRAND );
	}

	/**
	 * Locks an order for refund processing for 5 minutes.
	 *
	 * @return bool A flag that indicates whether the order is already locked.
	 */
	public function lock_refund() {
		$this->read_meta_data( true );

		$existing_lock = $this->get_lock_refund();

		if ( $existing_lock ) {
			$expiration = (int) $existing_lock;

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		$new_lock = time() + self::REFUND_LOCK_EXPIRATION;

		$this->set_lock_refund( $new_lock );
		$this->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing refund.
	 */
	public function unlock_refund() {
		$this->delete_meta_data( self::META_STRIPE_LOCK_REFUND );
		$this->save_meta_data();
	}

	/**
	 * Set the lock refund time.
	 *
	 * @param $time int The time to set.
	 * @return void
	 */
	public function set_lock_refund( $time ) {
		$this->update_meta_data( self::META_STRIPE_LOCK_REFUND, $time );
	}

	/**
	 * Get the lock refund time.
	 *
	 * @return int
	 */
	public function get_lock_refund() {
		return $this->get_meta( self::META_STRIPE_LOCK_REFUND );
	}

	/**
	 * Set the setup intent.
	 *
	 * @param $value string The value to set.
	 * @return void
	 */
	public function set_setup_intent( $value ) {
		$this->update_meta_data( self::META_STRIPE_SETUP_INTENT, $value );
	}

	/**
	 * Get the setup intent.
	 *
	 * @return string
	 */
	public function get_setup_intent() {
		return $this->get_meta( self::META_STRIPE_SETUP_INTENT );
	}

	/**
	 * Set the UPE redirect processed flag.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_upe_redirect_processed( $value ) {
		$this->update_meta_data( self::META_STRIPE_UPE_REDIRECT_PROCESSED, $value );
	}

	/**
	 * Whether the UPE redirect has been processed.
	 *
	 * @return bool The value of the flag.
	 */
	public function is_upe_redirect_processed() {
		return (bool) $this->get_meta( self::META_STRIPE_UPE_REDIRECT_PROCESSED );
	}

	/**
	 * Stores the status of the order before being put on hold in metadata.
	 *
	 * @param string $status The order status to store. Accepts 'default_payment_complete' which will fetch the default status for payment complete orders.
	 * @return void
	 */
	public function set_status_before_hold( $status ) {
		if ( 'default_payment_complete' === $status ) {
			$payment_complete_status = $this->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED;
			$status                  = apply_filters( 'woocommerce_payment_complete_order_status', $payment_complete_status, $this->get_id(), $this );
		}

		$this->update_meta_data( '_stripe_status_before_hold', $status );
	}

	/**
	 * Helper method to retrieve the status of the order before it was put on hold.
	 *
	 * @return string The status of the order before it was put on hold.
	 */
	public function get_status_before_hold() {
		$before_hold_status = $this->get_meta( self::META_STRIPE_STATUS_BEFORE_HOLD );

		if ( ! empty( $before_hold_status ) ) {
			return $before_hold_status;
		}

		$default_before_hold_status = $this->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED;
		return apply_filters( 'woocommerce_payment_complete_order_status', $default_before_hold_status, $this->get_id(), $this );
	}

	/**
	 * Set the UPE waiting for redirect flag.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_upe_waiting_for_redirect( $value ) {
		$this->update_meta_data( self::META_STRIPE_UPE_WAITING_FOR_REDIRECT, $value );
	}

	/**
	 * Whether the UPE payment is waiting for redirect.
	 *
	 * @return bool
	 */
	public function is_upe_waiting_for_redirect() {
		return (bool) $this->get_meta( self::META_STRIPE_UPE_WAITING_FOR_REDIRECT );
	}

	/**
	 * Set the mandate ID.
	 *
	 * @param $mandate_id string The mandate ID to set.
	 * @return void
	 */
	public function set_mandate_id( $mandate_id ) {
		$this->update_meta_data( self::META_STRIPE_MANDATE_ID, $mandate_id );
	}

	/**
	 * Get the mandate ID.
	 *
	 * @return string
	 */
	public function get_mandate_id() {
		return $this->get_meta( self::META_STRIPE_MANDATE_ID );
	}

	/**
	 * Locks an order for payment intent processing for 5 minutes.
	 *
	 * @param stdClass $intent The intent that is being processed.
	 * @return bool            A flag that indicates whether the order is already locked.
	 */
	public function lock_payment( $intent = null ) {
		$this->read_meta_data( true );

		$existing_lock = $this->get_lock_payment();

		if ( $existing_lock ) {
			$parts         = explode( '|', $existing_lock ); // Format is: "{expiry_timestamp}" or "{expiry_timestamp}|{pi_xxxx}" if an intent is passed.
			$expiration    = (int) $parts[0];
			$locked_intent = ! empty( $parts[1] ) ? $parts[1] : '';

			// If the lock is still active, return true.
			if ( time() <= $expiration && ( empty( $intent ) || empty( $locked_intent ) || ( $intent->id ?? '' ) === $locked_intent ) ) {
				return true;
			}
		}

		$new_lock = ( time() + self::PAYMENT_LOCK_EXPIRATION ) . ( isset( $intent->id ) ? '|' . $intent->id : '' );

		$this->set_lock_payment( $new_lock );
		$this->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 */
	public function unlock_payment() {
		$this->delete_meta_data( self::META_STRIPE_LOCK_PAYMENT );
		$this->save_meta_data();
	}

	/**
	 * Set the lock payment time.
	 *
	 * @param $time int The time to set.
	 * @return void
	 */
	public function set_lock_payment( $time ) {
		$this->update_meta_data( self::META_STRIPE_LOCK_PAYMENT, $time );
	}

	/**
	 * Get the lock payment time.
	 *
	 * @return int
	 */
	public function get_lock_payment() {
		return $this->get_meta( self::META_STRIPE_LOCK_PAYMENT );
	}

	/**
	 * Set the refund ID.
	 *
	 * @param $refund_id string The refund ID to set.
	 * @return void
	 */
	public function set_refund_id( $refund_id ) {
		$this->update_meta_data( self::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Get the refund ID.
	 *
	 * @return string
	 */
	public function get_refund_id() {
		return $this->get_meta( self::META_STRIPE_REFUND_ID );
	}

	/**
	 * Set the Multibanco data.
	 *
	 * @param $data array The Multibanco data to set.
	 * @return void
	 */
	public function set_multibanco_data( $data ) {
		$this->update_meta_data( self::META_STRIPE_MULTIBANCO, $data );
	}

	/**
	 * Get the Multibanco data.
	 *
	 * @return array
	 */
	public function get_multibanco_data() {
		return $this->get_meta( self::META_STRIPE_MULTIBANCO );
	}

	/**
	 * Set the Stripe intent ID.
	 *
	 * @param $intent_id string The intent ID to set.
	 * @return void
	 */
	public function set_intent_id( $intent_id ) {
		$this->update_meta_data( self::META_STRIPE_INTENT_ID, $intent_id );
	}

	/**
	 * Get the Stripe intent ID.
	 *
	 * @return string
	 */
	public function get_intent_id() {
		return $this->get_meta( self::META_STRIPE_INTENT_ID );
	}

	/**
	 * Set the UPE payment type.
	 *
	 * @param $payment_type string The payment type to set.
	 * @return void
	 */
	public function set_upe_payment_type( $payment_type ) {
		$this->update_meta_data( self::META_STRIPE_UPE_PAYMENT_TYPE, $payment_type );
	}

	/**
	 * Get the UPE payment type.
	 *
	 * @return string
	 */
	public function get_upe_payment_type() {
		return $this->get_meta( self::META_STRIPE_UPE_PAYMENT_TYPE );
	}

	/**
	 * Set the Stripe source ID.
	 *
	 * @param $source_id string The Stripe source ID.
	 * @return void
	 */
	public function set_source_id( $source_id ) {
		$this->update_meta_data( self::META_STRIPE_SOURCE_ID, $source_id );
	}

	/**
	 * Get the Stripe source ID.
	 *
	 * @return string
	 */
	public function get_source_id() {
		return $this->get_meta( self::META_STRIPE_SOURCE_ID );
	}

	/**
	 * Set the Stripe customer ID.
	 *
	 * @param $customer_id string The Stripe customer ID.
	 * @return void
	 */
	public function set_stripe_customer_id( $customer_id ) {
		$this->update_meta_data( self::META_STRIPE_CUSTOMER_ID, $customer_id );
	}

	/**
	 * Get the Stripe customer ID.
	 *
	 * @return string
	 */
	public function get_stripe_customer_id() {
		return $this->get_meta( self::META_STRIPE_CUSTOMER_ID );
	}

	/**
	 * Set the charge captured flag.
	 *
	 * @param $value string The value to set.
	 * @return void
	 */
	public function set_charge_captured( $value ) {
		$this->update_meta_data( self::META_STRIPE_CHARGE_CAPTURED, $value );
	}
	/**
	 * Whether the charge has been captured.
	 *
	 * @return bool
	 */
	public function is_charge_captured() {
		return wc_string_to_bool( $this->get_meta( self::META_STRIPE_CHARGE_CAPTURED ) );
	}

	/**
	 * Set the status final value.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_status_final( $value ) {
		$this->update_meta_data( self::META_STRIPE_STATUS_FINAL, $value );
	}

	/**
	 * Whether the current order status is final.
	 *
	 * @return bool
	 */
	public function is_status_final() {
		return (bool) $this->get_meta( self::META_STRIPE_STATUS_FINAL );
	}

	/**
	 * Queries for an order by a specific meta key and value.
	 *
	 * @param $meta_key string The meta key to search for.
	 * @param $meta_value string The meta value to search for.
	 * @return bool|WC_Stripe_Order
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

			$orders   = self::query( $params );
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $meta_value, $meta_key ) );
		}

		if ( ! empty( $order_id ) ) {
			$order = self::get_by_id( $order_id );
		}

		if ( ! empty( $order ) && $order->get_status() !== OrderStatus::TRASH ) {
			return $order;
		}

		return false;
	}
}
