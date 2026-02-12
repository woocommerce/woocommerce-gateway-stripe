<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';

	/**
	 * Feature flag for Amazon Pay.
	 *
	 * @var string
	 * @deprecated This feature flag will be removed in version 10.5.0. Amazon Pay is permanently enabled as of version 10.4.0.
	 */
	const AMAZON_PAY_FEATURE_FLAG_NAME        = '_wcstripe_feature_amazon_pay';

	/**
	 * Feature flag for Stripe Checkout Sessions.
	 *
	 * @var string
	 * @since 10.4.0
	 */
	const CHECKOUT_SESSIONS_FEATURE_FLAG_NAME = '_wcstripe_feature_stripe_checkout_sessions';

	/**
	 * Feature flag for Agentic Commerce.
	 *
	 * @var string
	 * @since 10.5.0
	 */
	const AGENTIC_COMMERCE_FEATURE_FLAG_NAME = '_wcstripe_feature_agentic_commerce';

	/**
	 * Map of feature flag option names => their default "yes"/"no" value.
	 * This single source of truth makes it easier to maintain our dev tools.
	 *
	 * @var array
	 */
	protected static $feature_flags = [
		self::AMAZON_PAY_FEATURE_FLAG_NAME        => 'no',
		self::CHECKOUT_SESSIONS_FEATURE_FLAG_NAME => 'no',
		self::AGENTIC_COMMERCE_FEATURE_FLAG_NAME  => 'no',
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
	 * @deprecated This method will be removed in a future version. Amazon Pay is permanently enabled as of version 10.4.0.
	 */
	public static function is_amazon_pay_available() {
		return true;
	}

	/**
	 * Feature flag to control the availability of Stripe Checkout Sessions.
	 *
	 * @return bool
	 * @since 10.4.0
	 */
	public static function is_checkout_sessions_available() {
		$stripe_settings              = WC_Stripe_Helper::get_stripe_settings();
		$is_pmc_enabled               = $stripe_settings['pmc_enabled'] ?? 'no';
		$is_oc_enabled                = $stripe_settings['optimized_checkout_element'] ?? 'no';
		$is_automatic_capture_enabled = $stripe_settings['capture'] ?? 'yes';

		// Stripe checkout sessions feature can only be available if:
		// - PMC is enabled
		// - OC Suite is enabled
		// - Automatic capture is enabled (i.e. manual capture or later capture is disabled)
		// If any of the above conditions are not met, the feature is not available.
		if ( 'yes' !== $is_pmc_enabled || 'yes' !== $is_oc_enabled || 'yes' !== $is_automatic_capture_enabled ) {
			return false;
		}

		$is_checkout_sessions_available = 'yes' === self::get_option_with_default( self::CHECKOUT_SESSIONS_FEATURE_FLAG_NAME );

		/**
		 * Filter to control the availability of the Stripe Checkout Sessions feature.
		 *
		 * @since 10.4.0
		 * Note: This filter will be removed when the feature rolls out.
		 * @param bool $is_checkout_sessions_available Whether Stripe Checkout Sessions should be available.
		 */
		return (bool) apply_filters( 'wc_stripe_is_checkout_sessions_available', $is_checkout_sessions_available );
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

	/**
	 * Whether Agentic Commerce product feed is enabled.
	 *
	 * @since 10.5.0
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_agentic_commerce_enabled(): bool {
		$is_agentic_commerce_enabled = 'yes' === self::get_option_with_default( self::AGENTIC_COMMERCE_FEATURE_FLAG_NAME );

		/**
		 * Filter to control the availability of the Agentic Commerce feature.
		 *
		 * @since 10.5.0
		 * @param bool $enabled Whether Agentic Commerce is enabled. Default false.
		 */
		return (bool) apply_filters(
			'wc_stripe_is_agentic_commerce_enabled',
			$is_agentic_commerce_enabled
		);
	}
}
