<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Mode
 */
class WC_Stripe_Mode {
	public const MODE_TEST = 'test';
	public const MODE_LIVE = 'live';

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

	/**
	 * Return the current mode.
	 *
	 * @return string The current mode.
	 */
	public static function get_current_mode(): string {
		return self::is_test() ? self::MODE_TEST : self::MODE_LIVE;
	}

	/**
	 * Checks if the mode is valid.
	 *
	 * @param string|null $mode The mode to check.
	 * @return bool Whether the mode is valid.
	 */
	public static function is_valid_mode( $mode ): bool {
		return in_array( $mode, [ self::MODE_TEST, self::MODE_LIVE ], true );
	}
}
