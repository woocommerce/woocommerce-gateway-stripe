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
	 * Add payment method to user and return WC payment token.
	 *
	 * By default we use WC_Payment_Token_SEPA, because most
	 * payment methods support saving payment methods via
	 * conversion to SEPA Direct Debit.
	 *
	 * @param WP_User $user User to add payment token to.
	 * @param object $intent JSON object for Stripe payment intent.
	 *
	 * @return WC_Payment_Token_SEPA|null WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $intent ) {
		if ( ! $this->is_reusable() ) {
			return null;
		}
		$payment_method_details = $this->get_payment_method_details_from_intent( $intent );

		$token = new WC_Payment_Token_SEPA();
		$token->set_last4( $payment_method_details->iban_last4 );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method_details->generated_sepa_debit );
		$token->set_user_id( $user->ID );
		$token->save();

		return $token;
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

	/**
	 * Returns payment method details from Payment Intent
	 * in order to save payment method.
	 *
	 * @param object $intent JSON object for Stripe payment intent.
	 *
	 * @return object
	 */
	protected function get_payment_method_details_from_intent( $intent ) {
		if ( 'payment_intent' === $intent->object ) {
			$charge                 = end( $intent->charges->data );
			$payment_method_details = (array) $charge->payment_method_details;
			return $payment_method_details[ $this->stripe_id ];
		} elseif ( 'setup_intent' === $intent->object ) {
			// TODO: We will need to do something different here to get the generated SEPA pm...
			return null;
		}
	}
}
