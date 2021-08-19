<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Feature_Flags {
	/**
	 * Checks whether UPE feature flag is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_enabled() {
		return '1' === get_option( '_wcstripe_feature_upe', '0' );
	}
}
