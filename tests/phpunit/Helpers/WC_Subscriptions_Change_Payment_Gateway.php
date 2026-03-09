<?php

/**
 * A helper class for setting up mocks for WC_Subscriptions_Change_Payment_Gateway.
 */
class WC_Subscriptions_Change_Payment_Gateway {

	/**
	 * Stub: update_payment_method.
	 *
	 * @param WC_Subscription $subscription Subscription to update.
	 * @param string          $gateway_id   Gateway ID.
	 */
	public static function update_payment_method( $subscription, $gateway_id ) {}

	/**
	 * Stub: will_subscription_update_all_payment_methods.
	 *
	 * @param WC_Subscription $subscription Subscription to check.
	 * @return bool
	 */
	public static function will_subscription_update_all_payment_methods( $subscription ) {
		return false;
	}

	/**
	 * Stub: update_all_payment_methods_from_subscription.
	 *
	 * @param WC_Subscription $subscription Subscription to update from.
	 * @param string          $gateway_id   Gateway ID.
	 * @return bool
	 */
	public static function update_all_payment_methods_from_subscription( $subscription, $gateway_id ) {
		return false;
	}
}
