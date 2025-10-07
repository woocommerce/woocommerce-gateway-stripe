<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';
	const AMAZON_PAY_FEATURE_FLAG_NAME        = '_wcstripe_feature_amazon_pay';

	/**
	 * Feature flag for Stripe ECE (Express Checkout Element).
	 * This feature flag controls whether the new Express Checkout Element (ECE) or the legacy Payment Request Button (PRB) is used to render express checkout buttons.
	 *
	 * @var string
	 *
	 * @deprecated This feature flag will be removed in version 10.1.0. ECE will be permanently enabled.
	 */
	const ECE_FEATURE_FLAG_NAME = '_wcstripe_feature_ece';

	/**
	 * Feature flag for Optimized Checkout (OC).
	 *
	 * @var string
	 *
	 * @deprecated This feature flag will be removed in version 9.9.0.
	 */
	const OC_FEATURE_FLAG_NAME = '_wcstripe_feature_oc';

	/**
	 * Map of feature flag option names => their default "yes"/"no" value.
	 * This single source of truth makes it easier to maintain our dev tools.
	 *
	 * @var array
	 */
	protected static $feature_flags = [
		'_wcstripe_feature_upe'                => 'yes',
		self::AMAZON_PAY_FEATURE_FLAG_NAME     => 'no',
		self::OC_FEATURE_FLAG_NAME             => 'no',
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
		return 'yes' === self::get_option_with_default( self::AMAZON_PAY_FEATURE_FLAG_NAME );
	}

	/**
	 * Checks whether Stripe ECE (Express Checkout Element) feature flag is enabled.
	 * Express checkout buttons are rendered with either ECE or PRB depending on this feature flag.
	 *
	 * @return bool
	 *
	 * @deprecated 10.0.0 ECE is always enabled. This method will be removed in a future release.
	 */
	public static function is_stripe_ece_enabled() {
		return true;
	}

	/**
	 * Checks whether UPE "preview" feature flag is enabled.
	 * This allows the merchant to enable/disable UPE checkout.
	 *
	 * @return bool
	 */
	public static function is_upe_preview_enabled() {
		return 'yes' === self::get_option_with_default( '_wcstripe_feature_upe' );
	}

	/**
	 * Checks whether UPE is enabled.
	 *
	 * @return bool
	 *
	 * @deprecated 10.0.0 UPE is always enabled. This method will be removed in a future release.
	 */
	public static function is_upe_checkout_enabled() {
		return true;
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
		return ( new \DateTime() )->format( 'Y-m-d' ) > '2024-10-28' && ! self::is_upe_checkout_enabled();
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

		/**
		 * Filter to control the availability of the Optimized Checkout feature.
		 *
		 * @since 9.6.0
		 * @deprecated This filter will be removed in version 9.9.0. No replacement will be provided as the Optimized Checkout feature will be permanently enabled.
		 * @param string $default_value The default value for the feature flag.
		 * @param string $pmc_enabled The value of the 'pmc_enabled' setting.
		 */
		return apply_filters(
			'wc_stripe_is_optimized_checkout_available',
			true,
			'yes',
			$pmc_enabled
		);
	}
}
