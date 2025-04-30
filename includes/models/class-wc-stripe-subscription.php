<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Subscription
 *
 * Wrapper for the original WC_Subscription class to allow custom getters and setter with the extension's specific metadata.
 */
class WC_Stripe_Subscription {
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
	 * Meta key for the Stripe refund ID.
	 *
	 * @var string
	 */
	const META_STRIPE_REFUND_ID = '_stripe_refund_id';

	/**
	 * The subscription object.
	 *
	 * @var WC_Subscription
	 */
	private $wc_subscription;

	/**
	 * Constructor.
	 *
	 * @param $subscription WC_Subscription|WC_Stripe_Subscription The subscription object.
	 */
	public function __construct( $subscription ) {
		if ( $subscription instanceof WC_Stripe_Subscription ) {
			$this->wc_subscription = $subscription->wc_subscription;
		} elseif ( class_exists( 'WC_Subscription' ) && $subscription instanceof WC_Subscription ) {
			$this->wc_subscription = $subscription;
		}

		if ( ! $this->wc_subscription ) {
			$type = is_object( $subscription ) ? get_class( $subscription ) : gettype( $subscription );
			throw new InvalidArgumentException( 'WC_Stripe_Subscription requires a valid underlying WC_Subscription; supplied $subscription argument was ' . $type );
		}
	}

	/**
	 * Magic method to call methods on the underlying WC_Subscription object.
	 *
	 * @param string $name The name of the method to call.
	 * @param array $arguments The arguments to pass to the method.
	 */
	public function __call( $name, $arguments ) {
		if ( method_exists( 'WC_Subscription', $name ) ) {
			return call_user_func_array( [ $this->wc_subscription, $name ], $arguments );
		}

		throw new BadMethodCallException( 'Method ' . $name . ' does not exist in WC_Stripe_Subscription' );
	}

	/**
	 * Wrapper to return an order using the extension's custom WC_Stripe_Subscription class.
	 *
	 * @param $subscription_id int Subscription ID.
	 * @return bool|WC_Stripe_Subscription
	 */
	public static function get_by_id( $subscription_id ) {
		$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : false;
		return $subscription ? self::to_instance( $subscription ) : false;
	}

	/**
	 * Wrapper to get subscriptions using the extension's custom WC_Stripe_Subscription class.
	 *
	 * @param $args array Arguments to pass to wcs_get_subscriptions.
	 * @return array|WC_Stripe_Subscription[]
	 */
	public static function query( $args ) {
		$subscriptions = function_exists( 'wcs_get_subscriptions' ) ? wcs_get_subscriptions( $args ) : [];
		if ( empty( $subscriptions ) ) {
			return [];
		}

		return array_map(
			function ( $subscription ) {
				return self::to_instance( $subscription );
			},
			$subscriptions
		);
	}

	/**
	 * Wrapper to get subscriptions for an order using the extension's custom WC_Stripe_Subscription class.
	 *
	 * @param $order int|WC_Order Order object or ID.
	 * @return array|WC_Stripe_Subscription[]
	 */
	public static function get_for_order( $order ) {
		$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $order ) : [];
		if ( empty( $subscriptions ) ) {
			return [];
		}

		return array_map(
			function ( $subscription ) {
				return self::to_instance( $subscription );
			},
			$subscriptions
		);
	}

	/**
	 * Wrapper to get subscriptions for a renewal order using the extension's custom WC_Stripe_Subscription class.
	 *
	 * @param $order int|WC_Order Order object or ID.
	 * @return array|WC_Stripe_Subscription[]
	 */
	public static function get_for_renewal_order( $order ) {
		$subscriptions = function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ? wcs_get_subscriptions_for_renewal_order( $order ) : [];
		if ( empty( $subscriptions ) ) {
			return [];
		}

		return array_map(
			function ( $subscription ) {
				return self::to_instance( $subscription );
			},
			$subscriptions
		);
	}

	/**
	 * Converts an order into WC_Stripe_Order if it is not already.
	 *
	 * @param $subscription WC_Stripe_Subscription|WC_Subscription Order object.
	 * @return WC_Stripe_Subscription
	 */
	public static function to_instance( $subscription ) {
		return $subscription instanceof WC_Stripe_Subscription ? $subscription : new self( $subscription );
	}

	/**
	 * Set the Stripe source ID.
	 *
	 * @param $source_id string The Stripe source ID.
	 * @return void
	 */
	public function set_source_id( $source_id ) {
		$this->wc_subscription->update_meta_data( self::META_STRIPE_SOURCE_ID, $source_id );
	}

	/**
	 * Get the Stripe source ID.
	 *
	 * @return string
	 */
	public function get_source_id() {
		return $this->wc_subscription->get_meta( self::META_STRIPE_SOURCE_ID );
	}

	/**
	 * Deletes the Stripe source ID.
	 *
	 * @return void
	 */
	public function delete_source_id() {
		$this->wc_subscription->delete_meta_data( self::META_STRIPE_SOURCE_ID );
	}

	/**
	 * Set the Stripe customer ID.
	 *
	 * @param $customer_id string The Stripe customer ID.
	 * @return void
	 */
	public function set_stripe_customer_id( $customer_id ) {
		$this->wc_subscription->update_meta_data( self::META_STRIPE_CUSTOMER_ID, $customer_id );
	}

	/**
	 * Get the Stripe customer ID.
	 *
	 * @return string
	 */
	public function get_stripe_customer_id() {
		return $this->wc_subscription->get_meta( self::META_STRIPE_CUSTOMER_ID );
	}

	/**
	 * Deletes the Stripe customer ID.
	 *
	 * @return void
	 */
	public function delete_stripe_customer_id() {
		$this->wc_subscription->delete_meta_data( self::META_STRIPE_CUSTOMER_ID );
	}

	/**
	 * Set the Stripe card ID.
	 *
	 * @param $card_id string The Stripe card ID.
	 * @return void
	 */
	public function set_stripe_card_id( $card_id ) {
		$this->wc_subscription->update_meta_data( self::META_STRIPE_CARD_ID, $card_id );
	}

	/**
	 * Get the Stripe card ID.
	 *
	 * @return string
	 */
	public function get_stripe_card_id() {
		return $this->wc_subscription->get_meta( self::META_STRIPE_CARD_ID );
	}

	/**
	 * Deletes the Stripe card ID.
	 *
	 * @return void
	 */
	public function delete_stripe_card_id() {
		$this->wc_subscription->delete_meta_data( self::META_STRIPE_CARD_ID );
	}

	/**
	 * Set the delayed update payment method.
	 *
	 * @param $payment_method string The payment method.
	 * @return void
	 */
	public function set_delayed_update_payment_all( $payment_method ) {
		$this->wc_subscription->update_meta_data( self::META_DELAYED_UPDATE_PAYMENT_METHOD_ALL, $payment_method );
	}

	/**
	 * Get the delayed update payment method.
	 *
	 * @return string
	 */
	public function get_delayed_update_payment_all() {
		return $this->wc_subscription->get_meta( self::META_DELAYED_UPDATE_PAYMENT_METHOD_ALL );
	}

	/**
	 * Set the refund ID.
	 *
	 * @param $refund_id string The refund ID to set.
	 * @return void
	 */
	public function set_refund_id( $refund_id ) {
		$this->wc_subscription->update_meta_data( self::META_STRIPE_REFUND_ID, $refund_id );
	}

	/**
	 * Get the refund ID.
	 *
	 * @return string
	 */
	public function get_refund_id() {
		return $this->wc_subscription->get_meta( self::META_STRIPE_REFUND_ID );
	}

	/**
	 * Deletes the refund ID.
	 *
	 * @return void
	 */
	public function delete_refund_id() {
		$this->wc_subscription->delete_meta_data( self::META_STRIPE_REFUND_ID );
	}
}
