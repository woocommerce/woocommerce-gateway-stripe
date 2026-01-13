<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';
	const AMAZON_PAY_FEATURE_FLAG_NAME        = '_wcstripe_feature_amazon_pay';

	/**
	 * Map of feature flag option names => their default "yes"/"no" value.
	 * This single source of truth makes it easier to maintain our dev tools.
	 *
	 * @var array
	 */
	protected static $feature_flags = [
		'_wcstripe_feature_upe'                => 'yes',
		self::AMAZON_PAY_FEATURE_FLAG_NAME     => 'no',
	];

	/**
	 * Retrieve all defined feature flags with their default values.
	 * Note: This method is intended for use in the dev tools.
	 *
	 * @return array
	 */
	public static function get_all_feature_flags_with_defaults() {
		return self::$feature_flags;
	}

	/**
	 * Retrieve the default value for a specific feature flag.
	 *
	 * @param string $flag
	 * @return string
	 */
	public static function get_option_with_default( $flag ) {
		$default = isset( self::$feature_flags[ $flag ] ) ? self::$feature_flags[ $flag ] : 'no';
		return get_option( $flag, $default );
	}

	/**
	 * Feature flag to control Amazon Pay feature availability.
	 *
	 * @return bool
	 */
	public static function is_amazon_pay_available() {
		$enable_amazon_pay = 'yes' === self::get_option_with_default( self::AMAZON_PAY_FEATURE_FLAG_NAME );

		/**
		 * Filter to control the availability of the Amazon Pay feature.
		 *
		 * @since 10.1.0
		 * @param bool $enable_amazon_pay Whether Amazon Pay should be enabled.
		 */
		return (bool) apply_filters( 'wc_stripe_is_amazon_pay_available', $enable_amazon_pay );
	}

	/**
	 * Checks whether UPE has been manually disabled by the merchant.
	 *
	 * @return bool
	 */
	public static function did_merchant_disable_upe() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		return ! empty( $stripe_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) && 'disabled' === $stripe_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ];
	}


	/**
	 * Checks if the APMs are deprecated. Stripe deprecated them on October 29, 2024 (for the legacy checkout).
	 *
	 * @return bool Whether the APMs are deprecated.
	 */
	public static function are_apms_deprecated() {
		return false;
	}

	/**
	 * Whether the Optimized Checkout (OC) feature flag is enabled.
	 *
	 * @return bool
	 */
	public static function is_oc_available() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$pmc_enabled     = $stripe_settings['pmc_enabled'] ?? 'no';
		if ( 'yes' !== $pmc_enabled ) {
			return false;
		}

		return true;
	}
}
