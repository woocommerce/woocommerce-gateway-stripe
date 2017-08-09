<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'woocommerce_stripe_settings' );
delete_option( 'wc_stripe_show_request_api_notice' );
delete_option( 'wc_stripe_show_apple_pay_notice' );
delete_option( 'wc_stripe_show_ssl_notice' );
delete_option( 'wc_stripe_show_keys_notice' );
delete_option( 'wc_stripe_version' );
