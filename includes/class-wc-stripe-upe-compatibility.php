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

	public static function are_inbox_notes_supported() {
		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return false;
		}

		try {
			WC_Data_Store::load( 'admin-note' );
		} catch ( Exception $e ) {
			return false;
		}

		return trait_exists( 'Automattic\WooCommerce\Admin\Notes\NoteTraits' ) && class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' );
	}
}
