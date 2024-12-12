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
