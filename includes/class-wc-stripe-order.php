<?php
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
		$amount = $this->get_meta( '_stripe_fee' );

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
		$this->update_meta_data( '_stripe_fee', $amount );
	}

	/**
	 * Deletes the Stripe fee for order.
	 */
	public function delete_fee() {
		$this->delete_meta_data( '_stripe_fee' );
		$this->delete_meta_data( 'Stripe Fee' );
	}

	/**
	 * Gets the Stripe net for order. With legacy check.
	 *
	 * @return string $amount
	 */
	public function get_net() {
		$amount = $this->get_meta( '_stripe_net', true );

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
		$this->update_meta_data( '_stripe_net', $amount );
	}

	/**
	 * Deletes the Stripe net for order.
	 */
	public function delete_net() {
		$this->delete_meta_data( '_stripe_net' );
		$this->delete_meta_data( 'Net Revenue From Stripe' );
	}

	/**
	 * Gets the Stripe currency for order.
	 *
	 * @return string $currency
	 */
	public function get_stripe_currency() {
		return $this->get_meta( '_stripe_currency' );
	}

	/**
	 * Updates the Stripe currency for order.
	 *
	 * @param string $currency
	 */
	public function set_stripe_currency( $currency ) {
		$this->update_meta_data( '_stripe_currency', $currency );
	}

	/**
	 * Adds metadata to the order to indicate that the payment is awaiting action.
	 *
	 * This meta is primarily used to prevent orders from being cancelled by WooCommerce's hold stock settings.
	 *
	 * @return void
	 */
	public function set_payment_awaiting_action( $save = true ) {
		$this->update_meta_data( '_stripe_payment_awaiting_action', wc_bool_to_string( true ) );

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Gets the metadata that indicates that the payment is awaiting action.
	 *
	 * @return bool Whether the payment is awaiting action.
	 */
	public function payment_awaiting_action() {
		return wc_string_to_bool( $this->get_meta( '_stripe_payment_awaiting_action' ) );
	}

	/**
	 * Removes the metadata from the order that was used to indicate that the payment was awaiting action.
	 *
	 * @param bool     $save  Whether to save the order after removing the metadata.
	 *
	 * @return void
	 */
	public function remove_payment_awaiting_action( $save = true ) {
		$this->delete_meta_data( '_stripe_payment_awaiting_action' );

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
		$this->update_meta_data( '_stripe_card_brand', $brand );
	}

	/**
	 * Get the preferred card brand.
	 *
	 * @return string
	 */
	public function get_card_brand() {
		return $this->get_meta( '_stripe_card_brand' );
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

		$new_lock = time() + 5 * MINUTE_IN_SECONDS;

		$this->set_lock_refund( $new_lock );
		$this->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing refund.
	 */
	public function unlock_refund() {
		$this->delete_meta_data( '_stripe_lock_refund' );
		$this->save_meta_data();
	}

	/**
	 * Set the lock refund time.
	 *
	 * @param $time int The time to set.
	 * @return void
	 */
	public function set_lock_refund( $time ) {
		$this->update_meta_data( '_stripe_lock_refund', $time );
	}

	/**
	 * Get the lock refund time.
	 *
	 * @return int
	 */
	public function get_lock_refund() {
		return $this->get_meta( '_stripe_lock_refund' );
	}

	/**
	 * Set the setup intent.
	 *
	 * @param $value string The value to set.
	 * @return void
	 */
	public function set_setup_intent( $value ) {
		$this->update_meta_data( '_stripe_setup_intent', $value );
	}

	/**
	 * Get the setup intent.
	 *
	 * @return string
	 */
	public function get_setup_intent() {
		return $this->get_meta( '_stripe_setup_intent' );
	}

	/**
	 * Set the UPE redirect processed flag.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_upe_redirect_processed( $value ) {
		$this->update_meta_data( '_stripe_upe_redirect_processed', $value );
	}

	/**
	 * Whether the UPE redirect has been processed.
	 *
	 * @return bool The value of the flag.
	 */
	public function upe_redirect_processed() {
		return (bool) $this->get_meta( '_stripe_upe_redirect_processed' );
	}

	/**
	 * Stores the status of the order before being put on hold in metadata.
	 *
	 * @param string $status The order status to store. Accepts 'default_payment_complete' which will fetch the default status for payment complete orders.
	 * @return void
	 */
	public function set_status_before_hold( $status ) {
		if ( 'default_payment_complete' === $status ) {
			$payment_complete_status = $this->needs_processing() ? 'processing' : 'completed';
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
		$before_hold_status = $this->get_meta( '_stripe_status_before_hold' );

		if ( ! empty( $before_hold_status ) ) {
			return $before_hold_status;
		}

		$default_before_hold_status = $this->needs_processing() ? 'processing' : 'completed';
		return apply_filters( 'woocommerce_payment_complete_order_status', $default_before_hold_status, $this->get_id(), $this );
	}

	/**
	 * Set the UPE waiting for redirect flag.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_upe_waiting_for_redirect( $value ) {
		$this->update_meta_data( '_stripe_upe_waiting_for_redirect', $value );
	}

	/**
	 * Whether the UPE payment is waiting for redirect.
	 *
	 * @return bool
	 */
	public function upe_waiting_for_redirect() {
		return (bool) $this->get_meta( '_stripe_upe_waiting_for_redirect' );
	}

	/**
	 * Set the mandate ID.
	 *
	 * @param $mandate_id string The mandate ID to set.
	 * @return void
	 */
	public function set_mandate_id( $mandate_id ) {
		$this->update_meta_data( '_stripe_mandate_id', $mandate_id );
	}

	/**
	 * Get the mandate ID.
	 *
	 * @return string
	 */
	public function get_mandate_id() {
		return $this->get_meta( '_stripe_mandate_id' );
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

		$new_lock = ( time() + 5 * MINUTE_IN_SECONDS ) . ( isset( $intent->id ) ? '|' . $intent->id : '' );

		$this->set_lock_payment( $new_lock );
		$this->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 */
	public function unlock_payment() {
		$this->delete_meta_data( '_stripe_lock_payment' );
		$this->save_meta_data();
	}

	/**
	 * Set the lock payment time.
	 *
	 * @param $time int The time to set.
	 * @return void
	 */
	public function set_lock_payment( $time ) {
		$this->update_meta_data( '_stripe_lock_payment', $time );
	}

	/**
	 * Get the lock payment time.
	 *
	 * @return int
	 */
	public function get_lock_payment() {
		return $this->get_meta( '_stripe_lock_payment' );
	}

	/**
	 * Set the refund ID.
	 *
	 * @param $refund_id string The refund ID to set.
	 * @return void
	 */
	public function set_refund_id( $refund_id ) {
		$this->update_meta_data( '_stripe_refund_id', $refund_id );
	}

	/**
	 * Get the refund ID.
	 *
	 * @return string
	 */
	public function get_refund_id() {
		return $this->get_meta( '_stripe_refund_id' );
	}

	/**
	 * Set the Multibanco data.
	 *
	 * @param $data array The Multibanco data to set.
	 * @return void
	 */
	public function set_multibanco_data( $data ) {
		$this->update_meta_data( '_stripe_multibanco', $data );
	}

	/**
	 * Get the Multibanco data.
	 *
	 * @return array
	 */
	public function get_multibanco_data() {
		return $this->get_meta( '_stripe_multibanco' );
	}

	/**
	 * Set the Stripe intent ID.
	 *
	 * @param $intent_id string The intent ID to set.
	 * @return void
	 */
	public function set_intent_id( $intent_id ) {
		$this->update_meta_data( '_stripe_intent_id', $intent_id );
	}

	/**
	 * Get the Stripe intent ID.
	 *
	 * @return string
	 */
	public function get_intent_id() {
		return $this->get_meta( '_stripe_intent_id' );
	}

	/**
	 * Set the UPE payment type.
	 *
	 * @param $payment_type string The payment type to set.
	 * @return void
	 */
	public function set_upe_payment_type( $payment_type ) {
		$this->update_meta_data( '_stripe_upe_payment_type', $payment_type );
	}

	/**
	 * Get the UPE payment type.
	 *
	 * @return string
	 */
	public function get_upe_payment_type() {
		return $this->get_meta( '_stripe_upe_payment_type' );
	}

	/**
	 * Set the Stripe source ID.
	 *
	 * @param $source_id string The Stripe source ID.
	 * @return void
	 */
	public function set_source_id( $source_id ) {
		$this->update_meta_data( '_stripe_source_id', $source_id );
	}

	/**
	 * Get the Stripe source ID.
	 *
	 * @return string
	 */
	public function get_source_id() {
		return $this->get_meta( '_stripe_source_id' );
	}

	/**
	 * Set the Stripe customer ID.
	 *
	 * @param $customer_id string The Stripe customer ID.
	 * @return void
	 */
	public function set_stripe_customer_id( $customer_id ) {
		$this->update_meta_data( '_stripe_customer_id', $customer_id );
	}

	/**
	 * Get the Stripe customer ID.
	 *
	 * @return string
	 */
	public function get_stripe_customer_id() {
		return $this->get_meta( '_stripe_customer_id' );
	}

	/**
	 * Set the charge captured flag.
	 *
	 * @param $value string The value to set.
	 * @return void
	 */
	public function set_charge_captured( $value ) {
		$this->update_meta_data( '_stripe_charge_captured', $value );
	}
	/**
	 * Whether the charge has been captured.
	 *
	 * @return bool
	 */
	public function charge_captured() {
		return wc_string_to_bool( $this->get_meta( '_stripe_charge_captured' ) );
	}

	/**
	 * Set the status final value.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_status_final( $value ) {
		$this->update_meta_data( '_stripe_status_final', $value );
	}

	/**
	 * Whether the current order status is final.
	 *
	 * @return bool
	 */
	public function status_final() {
		return (bool) $this->get_meta( '_stripe_status_final' );
	}
}
