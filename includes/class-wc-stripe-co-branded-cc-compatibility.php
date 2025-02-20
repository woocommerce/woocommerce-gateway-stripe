<?php
/**
 * Stripe Co-branded cards compatibility class.
 *
 * @package WooCommerce Stripe
 * @since 8.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Co_Branded_CC_Compatibility {
	const MIN_WC_VERSION = '8.9.0';
	/**
	 * Determines whether the current version of WooCommerce is supported.
	 *
	 * @return bool
	 */
	public static function is_wc_supported() {
		return 'beta' !== WC_VERSION && version_compare( WC_VERSION, self::MIN_WC_VERSION, '>' );
	}
}
