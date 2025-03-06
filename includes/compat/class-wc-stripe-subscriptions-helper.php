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
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public static function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscription' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
	}

	/**
	 * Returns a list of subscriptions that are detached from the customer.
	 *
	 * @return array
	 */
	public static function get_detached_subscriptions() {
		// Check if we have a cached result.
		$cached_subscriptions = get_transient( self::DETACHED_SUBSCRIPTIONS_TRANSIENT_KEY );
		if ( ! empty( $cached_subscriptions ) ) {
			return $cached_subscriptions;
		}

		$detached_subscriptions = [];
		$subscriptions          = wcs_get_subscriptions(
			[
				'subscriptions_per_page' => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'subscription_status'    => [ 'active', 'on-hold', 'pending-cancel' ],
			]
		);
		foreach ( $subscriptions as $subscription ) {
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
}
