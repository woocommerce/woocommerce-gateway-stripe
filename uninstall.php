<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// if uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Only remove ALL product and page data if WC_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA ) {
	// Delete options.
	delete_option( 'woocommerce_stripe_settings' );
	delete_option( 'wc_stripe_show_styles_notice' );
	delete_option( 'wc_stripe_show_request_api_notice' );
	delete_option( 'wc_stripe_show_apple_pay_notice' );
	delete_option( 'wc_stripe_show_ssl_notice' );
	delete_option( 'wc_stripe_show_keys_notice' );
	delete_option( 'wc_stripe_show_alipay_notice' );
	delete_option( 'wc_stripe_show_bancontact_notice' );
	delete_option( 'wc_stripe_show_bitcoin_notice' );
	delete_option( 'wc_stripe_show_eps_notice' );
	delete_option( 'wc_stripe_show_giropay_notice' );
	delete_option( 'wc_stripe_show_ideal_notice' );
	delete_option( 'wc_stripe_show_multibanco_notice' );
	delete_option( 'wc_stripe_show_p24_notice' );
	delete_option( 'wc_stripe_show_sepa_notice' );
	delete_option( 'wc_stripe_show_sofort_notice' );
	delete_option( 'wc_stripe_version' );
	delete_option( 'woocommerce_stripe_bancontact_settings' );
	delete_option( 'woocommerce_stripe_alipay_settings' );
	delete_option( 'woocommerce_stripe_bitcoin_settings' );
	delete_option( 'woocommerce_stripe_ideal_settings' );
	delete_option( 'woocommerce_stripe_p24_settings' );
	delete_option( 'woocommerce_stripe_giropay_settings' );
	delete_option( 'woocommerce_stripe_sepa_settings' );
	delete_option( 'woocommerce_stripe_sofort_settings' );
	delete_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' );
}
