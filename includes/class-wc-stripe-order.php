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
		return $this->get_meta( '_stripe_charge_captured' ) === 'yes';
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
