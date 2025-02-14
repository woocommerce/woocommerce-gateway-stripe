<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';
	const ECE_FEATURE_FLAG_NAME               = '_wcstripe_feature_ece';
	const AMAZON_PAY_FEATURE_FLAG_NAME        = '_wcstripe_feature_amazon_pay';

	const LPM_ACH_FEATURE_FLAG_NAME  = '_wcstripe_feature_lpm_ach';
	const LPM_ACSS_FEATURE_FLAG_NAME = '_wcstripe_feature_lpm_acss';
	const LPM_BACS_FEATURE_FLAG_NAME = '_wcstripe_feature_lpm_bacs';

	/**
	 * Checks whether ACH LPM (Local Payment Method) feature flag is enabled.
	 * ACH LPM is a feature that allows merchants to enable/disable the ACH payment method.
	 *
	 * @return bool
	 */
	public static function is_ach_lpm_enabled() {
		return 'yes' === get_option( self::LPM_ACH_FEATURE_FLAG_NAME, 'no' );
	}

	/**
	 * Checks whether ACSS LPM (Local Payment Method) feature flag is enabled.
	 * ACSS LPM is a feature that allows merchants to enable/disable the ACSS payment method.
	 *
	 * @return bool
	 */
	public static function is_acss_lpm_enabled() {
		return 'yes' === get_option( self::LPM_ACSS_FEATURE_FLAG_NAME, 'no' );
	}

	/**
	 * Feature flag to control Amazon Pay feature availability.
	 *
	 * @return bool
	 */
	public static function is_amazon_pay_available() {
		return 'yes' === get_option( self::AMAZON_PAY_FEATURE_FLAG_NAME, 'no' );
	}

	/**
	 * Checks whether Bacs LPM (Local Payment Method) feature flag is enabled.
	 * Alows the merchant to enable/disable Bacs payment method.
	 *
	 * @return bool
	 */
	public static function is_bacs_lpm_enabled(): bool {
		return 'yes' === get_option( self::LPM_BACS_FEATURE_FLAG_NAME, 'no' );
	}

	/**
	 * Checks whether Stripe ECE (Express Checkout Element) feature flag is enabled.
	 * Express checkout buttons are rendered with either ECE or PRB depending on this feature flag.
	 *
	 * @return bool
	 */
	public static function is_stripe_ece_enabled() {
		return 'yes' === get_option( self::ECE_FEATURE_FLAG_NAME, 'yes' );
	}

	/**
	 * Checks whether UPE "preview" feature flag is enabled.
	 * This allows the merchant to enable/disable UPE checkout.
	 *
	 * @return bool
	 */
	public static function is_upe_preview_enabled() {
		return 'yes' === get_option( '_wcstripe_feature_upe', 'yes' );
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
}
