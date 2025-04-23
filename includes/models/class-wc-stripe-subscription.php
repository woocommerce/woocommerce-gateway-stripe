<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Subscription
 *
 * Wrapper for the original WC_Subscription class to allow custom getters and setter with the extension's specific metadata.
 */
class WC_Stripe_Subscription extends WC_Subscription {
	/**
	 * Meta key for the Stripe source ID.
	 *
	 * @var string
	 */
	const META_STRIPE_SOURCE_ID = '_stripe_source_id';

	/**
	 * Meta key for the customer ID.
	 *
	 * @var string
	 */
	const META_STRIPE_CUSTOMER_ID = '_stripe_customer_id';

	/**
	 * Meta key for the card ID.
	 *
	 * @var string
	 */
	const META_STRIPE_CARD_ID = '_stripe_card_id';

	/**
	 * Meta key for the delayed update payment method.
	 *
	 * @var string
	 */
	const META_DELAYED_UPDATE_PAYMENT_METHOD_ALL = '_delayed_update_payment_method_all';

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
	 * Set the Stripe card ID.
	 *
	 * @param $card_id string The Stripe card ID.
	 * @return void
	 */
	public function set_stripe_card_id( $card_id ) {
		$this->update_meta_data( self::META_STRIPE_CARD_ID, $card_id );
	}

	/**
	 * Get the Stripe card ID.
	 *
	 * @return string
	 */
	public function get_stripe_card_id() {
		return $this->get_meta( self::META_STRIPE_CARD_ID );
	}

	/**
	 * Set the delayed update payment method.
	 *
	 * @param $payment_method string The payment method.
	 * @return void
	 */
	public function set_delayed_update_payment_all( $payment_method ) {
		$this->update_meta_data( self::META_DELAYED_UPDATE_PAYMENT_METHOD_ALL, $payment_method );
	}

	/**
	 * Get the delayed update payment method.
	 *
	 * @return string
	 */
	public function get_delayed_update_payment_all() {
		return $this->get_meta( self::META_DELAYED_UPDATE_PAYMENT_METHOD_ALL );
	}
}
