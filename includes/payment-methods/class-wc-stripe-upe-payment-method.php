<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 */


/**
 * Extendable abstract class for payment methods.
 */
abstract class WC_Stripe_UPE_Payment_Method {

	/**
	 * Stripe key name
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Display title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $is_reusable;

	/**
	 * Instance of WC Payments Token Service to save payment method
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * Create instance of payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Instance of WC_Payments_Token_Service.
	 */
	public function __construct( $token_service ) {
		//      $this->token_service = $token_service;
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->stripe_id;
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		return $this->title;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout() {
		if ( $this->is_subscription_item_in_cart() ) {
			return $this->is_reusable();
		}
		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_reusable() {
		return $this->is_reusable;
	}

	/**
	 * Add payment method to user and return WC payment token
	 *
	 * @param WP_User $user User to get payment token from.
	 * @param string  $payment_method_id Stripe payment method ID string.
	 *
	 * @return WC_Payment_Token_UPE WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $payment_method_id ) {
		return $this->token_service->add_payment_method_to_user( $user, $payment_method_id );
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @return bool
	 */
	public function is_subscription_item_in_cart() {
		if ( class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ) ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || 0 < count( wcs_get_order_type_cart_items( 'renewal' ) );
		}
		return false;
	}
}
