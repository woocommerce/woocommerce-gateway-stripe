<?php
/**
 * Trait WC_Stripe_Subscriptions_Utilities
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility functions related to WC Subscriptions.
 */
trait WC_Stripe_Subscriptions_Utilities {

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
	}
}
