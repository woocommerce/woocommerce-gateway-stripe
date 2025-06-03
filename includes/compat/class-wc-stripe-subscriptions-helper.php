<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class to handle subscriptions.
 */
class WC_Stripe_Subscriptions_Helper {
	/**
	 * Transient key for detached subscriptions.
	 *
	 * @var string
	 */
	private const DETACHED_SUBSCRIPTIONS_TRANSIENT_KEY = 'wcstripe_detached_subscriptions';

	/**
	 * Stripe customer page base URL.
	 *
	 * @var string
	 */
	private const STRIPE_CUSTOMER_PAGE_BASE_URL = 'https://dashboard.stripe.com/customers/';

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public static function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscription' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
	}

	/**
	 * Loads up to 50 subscriptions, and attempts to return those that are detached from the customer.
	 *
	 * @return array
	 *
	 * @deprecated 9.6.0 This method is no longer used and will be removed in a future version.
	 */
	public static function get_some_detached_subscriptions() {
		_deprecated_function( __METHOD__, '9.6.0' );
		return self::get_detached_subscriptions( 50 );
	}

	/**
	 * Loads all subscriptions, and attempts to return those that are detached from the customer.
	 *
	 * @param int $limit The maximum number of subscriptions to retrieve. Use -1 for no limit (default).
	 * @return array
	 */
	public static function get_detached_subscriptions( $limit = -1 ) {
		// Check if we have a cached result.
		$cached_subscriptions = get_transient( self::DETACHED_SUBSCRIPTIONS_TRANSIENT_KEY );
		if ( is_array( $cached_subscriptions ) ) {
			return $cached_subscriptions;
		}

		$subscriptions = wcs_get_subscriptions(
			[
				'subscriptions_per_page' => $limit,
				'page'                   => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'subscription_status'    => [ 'active', 'on-hold', 'pending-cancel' ],
			]
		);

		$detached_subscriptions = [];
		foreach ( $subscriptions as $subscription ) {
			if ( ! $subscription instanceof WC_Subscription ) {
				continue;
			}

			$source_id = $subscription->get_meta( '_stripe_source_id' );
			if ( $source_id ) {
				$payment_method = WC_Stripe_API::get_payment_method( $source_id );
				if ( empty( $payment_method->customer ) ) {
					$detached_subscriptions[] = [
						'id'                        => $subscription->get_id(),
						'customer_id'               => $subscription->get_meta( '_stripe_customer_id' ),
						'change_payment_method_url' => $subscription->get_change_payment_method_url(),
					];
				}
			}
		}

		// Cache the result for a day.
		set_transient( self::DETACHED_SUBSCRIPTIONS_TRANSIENT_KEY, $detached_subscriptions, DAY_IN_SECONDS );

		return $detached_subscriptions;
	}

	/**
	 * Builds a string containing messages about subscriptions that are detached from the customer.
	 *
	 * @param $subscriptions array An array of subscriptions that are detached from the customer.
	 * @return string A string containing the messages to be displayed in the admin interface.
	 */
	public static function build_subscriptions_detached_messages( $subscriptions = [] ) {
		$detached_messages = '';
		foreach ( $subscriptions as $subscription ) {
			$customer_payment_method_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $subscription['change_payment_method_url'] ),
				esc_html(
				/* translators: this is a text for a link pointing to the customer's payment method page */
					__( 'Payment method page &rarr;', 'woocommerce-gateway-stripe' )
				)
			);
			$customer_stripe_page = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::STRIPE_CUSTOMER_PAGE_BASE_URL . $subscription['customer_id'] ),
				esc_html(
				/* translators: this is a text for a link pointing to the customer's page on Stripe */
					__( 'Stripe customer page &rarr;', 'woocommerce-gateway-stripe' )
				)
			);
			$detached_messages .= sprintf(
			/* translators: %1$s is the subscription ID. %2$s is a customer payment method page. %3$s is the customer's page on Stripe */
				__( '#%1$s: %2$s | %3$s<br/>', 'woocommerce-gateway-stripe' ),
				$subscription['id'],
				$customer_payment_method_link,
				$customer_stripe_page
			);
		}
		if ( ! empty( $detached_messages ) ) {
			$detached_messages = __( 'Some subscriptions are missing payment methods, <strong>preventing renewals</strong>. Share the payment method page link with the customer to update it or manually set the Stripe Payment Method ID meta field in the subscriptions details\' "Billing" section to another from the customer\'s page on Stripe. Below are the last subscriptions affected and the links as mentioned earlier:<br />', 'woocommerce-gateway-stripe' ) . $detached_messages;
		}
		return $detached_messages;
	}
}
