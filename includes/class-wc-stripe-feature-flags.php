<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	/**
	 * Checks whether UPE feature flag is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_enabled() {
		return '1' === get_option( '_wcstripe_feature_upe', '0' ) || self::is_upe_settings_redesign_enabled();
	}

	/**
	 * Checks whether the feature flag used for the new settings + UPE is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_settings_redesign_enabled() {
//		return true;
		return '1' === get_option( '_wcstripe_feature_upe_settings', '0' );
	}
}
