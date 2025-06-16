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
		$settings = get_option( WC_Stripe_Settings::SETTINGS_OPTION, [] );
		return 'yes' !== ( $settings['testmode'] ?? 'no' );
	}

	/**
	 * Checks if the plugin is in test mode.
	 *
	 * @return bool Whether the plugin is in test mode.
	 */
	public static function is_test() {
		$settings = get_option( WC_Stripe_Settings::SETTINGS_OPTION, [] );
		return 'yes' === ( $settings['testmode'] ?? 'no' );
	}
}
