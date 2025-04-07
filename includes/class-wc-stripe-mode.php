<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Mode
 */
class WC_Stripe_Mode {
	/**
	 * Checks if the plugin is in live mode.
	 *
	 * @return bool Whether the plugin is in live mode.
	 */
	public static function is_live() {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		return 'yes' !== ( $settings['testmode'] ?? 'no' );
	}

	/**
	 * Checks if the plugin is in test mode.
	 *
	 * @return bool Whether the plugin is in test mode.
	 */
	public static function is_test() {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		return 'yes' === ( $settings['testmode'] ?? 'no' );
	}
}
