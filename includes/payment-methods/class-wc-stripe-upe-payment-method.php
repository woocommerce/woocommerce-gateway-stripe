<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Stripe_Subscriptions_Utilities;

/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 */


/**
 * Extendable abstract class for payment methods.
 */
abstract class WC_Stripe_UPE_Payment_Method {

	use WC_Stripe_Subscriptions_Utilities;

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
	 * Instance of WC Stripe Payments Token Service to save payment method
	 *
	 * @var WC_Stripe_Payment_Tokens
	 */
	protected $token_service;

	/**
	 * Create instance of payment method
	 *
	 * @param WC_Stripe_Payment_Tokens $token_service Instance of WC_Stripe_Payment_Tokens.
	 */
	public function __construct( $token_service ) {
		$this->token_service = $token_service;
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
	 * @param WC_Stripe_Customer $user User to get payment token from.
	 * @param stdClass  $payment_method Stripe payment method ID string.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $payment_method ) {
		return $this->token_service->add_payment_method_to_user( $payment_method, $user );
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @return bool
	 */
	public function is_subscription_item_in_cart() {
		if ( $this->is_subscriptions_enabled() ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || 0 < count( wcs_get_order_type_cart_items( 'renewal' ) );
		}
		return false;
	}
}
