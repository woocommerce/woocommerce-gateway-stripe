<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';

	/**
	 * Checks whether UPE "preview" feature flag is enabled.
	 * This allows the merchant to enable/disable UPE checkout.
	 *
	 * @return bool
	 */
	public static function is_upe_preview_enabled() {
		return 'yes' === get_option( '_wcstripe_feature_upe', 'no' ) || self::is_upe_settings_redesign_enabled();
	}

	/**
	 * Checks whether UPE is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_checkout_enabled() {
		$stripe_settings = get_option( 'woocommerce_stripe_settings', null );
		return ! empty( $stripe_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) && 'yes' === $stripe_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ];
	}

	/**
	 * Checks whether the feature flag used for the new settings + UPE is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_settings_redesign_enabled() {
		return 'yes' === get_option( '_wcstripe_feature_upe_settings', 'no' );
	}
}
