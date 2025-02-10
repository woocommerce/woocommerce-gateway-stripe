<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class to handle subscriptions.
 */
class WC_Stripe_Subscriptions_Helper {
	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public static function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscription' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
	}
}
