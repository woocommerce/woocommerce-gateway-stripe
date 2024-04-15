<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Co_Branded_CC_Compatibility {
	const MIN_WC_VERSION = '8.9.0';

	public static function is_wc_supported() {
		return true;
		return 'beta' !== WC_VERSION && version_compare( WC_VERSION, self::MIN_WC_VERSION, '>' );
	}
}
