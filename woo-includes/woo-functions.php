<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WC_Stripe_Dependencies' ) ) {
	require_once( dirname( __FILE__ ) . '/class-wc-stripe-dependencies.php' );
}

/**
 * WC Detection
 */
if ( ! function_exists( 'wc_stripe_is_wc_active' ) ) {
	function wc_stripe_is_wc_active() {
		return WC_Stripe_Dependencies::woocommerce_active_check();
	}
}
