<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';
	const ECE_FEATURE_FLAG_NAME               = '_wcstripe_feature_ece';
	const AMAZON_PAY_FEATURE_FLAG_NAME        = '_wcstripe_feature_amazon_pay';
	const OC_FEATURE_FLAG_NAME                = '_wcstripe_feature_oc';
	const LPM_ACH_FEATURE_FLAG_NAME           = '_wcstripe_feature_lpm_ach';
	const LPM_ACSS_FEATURE_FLAG_NAME          = '_wcstripe_feature_lpm_acss';
	const LPM_BACS_FEATURE_FLAG_NAME          = '_wcstripe_feature_lpm_bacs';
	const LPM_BLIK_FEATURE_FLAG_NAME          = '_wcstripe_feature_lpm_blik';
	const LPM_BECS_DEBIT_FEATURE_FLAG_NAME    = '_wcstripe_feature_lpm_becs_debit';

	/**
	 * Feature flag to control SPE (Single Payment Element, now OC - Optimized CHeckout) feature availability.
	 *
	 * @deprecated since 9.5.0 Use `WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME` instead.
	 */
	const SPE_FEATURE_FLAG_NAME = '_wcstripe_feature_spe';

	/**
	 * Map of feature flag option names => their default "yes"/"no" value.
	 * This single source of truth makes it easier to maintain our dev tools.
	 *
	 * @var array
	 */
	protected static $feature_flags = [
		'_wcstripe_feature_upe'                => 'yes',
		self::ECE_FEATURE_FLAG_NAME            => 'yes',
		self::AMAZON_PAY_FEATURE_FLAG_NAME     => 'no',
		self::OC_FEATURE_FLAG_NAME             => 'no',
		self::LPM_ACH_FEATURE_FLAG_NAME        => 'yes',
		self::LPM_ACSS_FEATURE_FLAG_NAME       => 'yes',
		self::LPM_BACS_FEATURE_FLAG_NAME       => 'yes',
		self::LPM_BECS_DEBIT_FEATURE_FLAG_NAME => 'yes',
		self::LPM_BLIK_FEATURE_FLAG_NAME       => 'yes',
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
	 * Checks whether ACH LPM (Local Payment Method) feature flag is enabled.
	 * ACH LPM is a feature that allows merchants to enable/disable the ACH payment method.
	 *
	 * @return bool
	 */
	public static function is_ach_lpm_enabled() {
		return 'yes' === self::get_option_with_default( self::LPM_ACH_FEATURE_FLAG_NAME );
	}

	/**
	 * Checks whether ACSS LPM (Local Payment Method) feature flag is enabled.
	 * ACSS LPM is a feature that allows merchants to enable/disable the ACSS payment method.
	 *
	 * @return bool
	 */
	public static function is_acss_lpm_enabled() {
		return 'yes' === self::get_option_with_default( self::LPM_ACSS_FEATURE_FLAG_NAME );
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
	 * Checks whether Bacs LPM (Local Payment Method) feature flag is enabled.
	 * Alows the merchant to enable/disable Bacs payment method.
	 *
	 * @return bool
	 */
	public static function is_bacs_lpm_enabled(): bool {
		return 'yes' === self::get_option_with_default( self::LPM_BACS_FEATURE_FLAG_NAME );
	}

	/**
	 * Checks whether BLIK LPM (Local Payment Method) feature flag is enabled.
	 * BLIK LPM is a feature that allows merchants to enable/disable the BLIK payment method.
	 *
	 * @return bool
	 */
	public static function is_blik_lpm_enabled(): bool {
		return 'yes' === self::get_option_with_default( self::LPM_BLIK_FEATURE_FLAG_NAME );
	}

	/**
	 * Checks whether BECS Debit LPM (Local Payment Method) feature flag is enabled.
	 * https://docs.stripe.com/payments/au-becs-debit.
	 *
	 * @return bool
	 */
	public static function is_becs_debit_lpm_enabled(): bool {
		return 'yes' === self::get_option_with_default( self::LPM_BECS_DEBIT_FEATURE_FLAG_NAME );
	}

	/**
	 * Checks whether Stripe ECE (Express Checkout Element) feature flag is enabled.
	 * Express checkout buttons are rendered with either ECE or PRB depending on this feature flag.
	 *
	 * @return bool
	 */
	public static function is_stripe_ece_enabled() {
		return 'yes' === self::get_option_with_default( self::ECE_FEATURE_FLAG_NAME );
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
	 */
	public static function is_upe_checkout_enabled() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		return ! empty( $stripe_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] )
			&& 'yes' === $stripe_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ];
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
	 * Whether the Single Payment Element (SPE) feature flag is enabled.
	 *
	 * @return bool
	 *
	 * @deprecated since 9.5.0 Use `WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME` instead.
	 */
	public static function is_spe_available() {
		return self::is_oc_available();
	}

	/**
	 * Whether the Optimized Checkout (OC, previously known as SPE) feature flag is enabled.
	 *
	 * @return bool
	 */
	public static function is_oc_available() {
		return 'yes' === self::get_option_with_default( self::OC_FEATURE_FLAG_NAME );
	}
}
