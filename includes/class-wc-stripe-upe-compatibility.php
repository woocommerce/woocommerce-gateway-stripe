<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_UPE_Compatibility {
	const MIN_WP_VERSION  = '5.6';
	const MIN_WC_VERSION  = '5.5';
	const LEARN_MORE_LINK = 'https://developer.woocommerce.com/2021/08/26/developer-advisory-woocommerce-stripe-support-policy-change/';

	public static function is_wp_supported() {
		return version_compare( get_bloginfo( 'version' ), self::MIN_WP_VERSION, '>=' );
	}

	public static function is_wc_supported() {
		return version_compare( WC_VERSION, self::MIN_WC_VERSION, '>=' );
	}
}
