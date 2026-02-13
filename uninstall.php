<?php
/**
 * WooCommerce Stripe Gateway Uninstall
 *
 * @version  x.x.x
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Exit if uninstall not called from WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove OAuth access_token refresh scheduled job.
wp_clear_scheduled_hook( 'wc_stripe_refresh_connection' );

/*
 * ONLY remove the Stripe keys and keep the other configuration.
 * This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( ! defined( 'WC_REMOVE_ALL_DATA' ) || true !== WC_REMOVE_ALL_DATA ) {
	// Remove OAuth keys from the settings
	$settings = get_option( 'woocommerce_stripe_settings', [] );
	if ( is_array( $settings ) ) {
		// Disable the gateway before removing the plugin, to avoid an invalid API keys notice when reinstalling.
		$settings['enabled'] = 'no';
		// Live keys
		unset( $settings['publishable_key'], $settings['secret_key'] );
		unset( $settings['connection_type'], $settings['refresh_token'] );
		unset( $settings['pmc_enabled'] );
		unset( $settings['webhook_data'] );
		unset( $settings['webhook_secret'] );
		// Test keys
		unset( $settings['test_publishable_key'], $settings['test_secret_key'] );
		unset( $settings['test_connection_type'], $settings['test_refresh_token'] );
		unset( $settings['test_webhook_data'] );
		unset( $settings['test_webhook_secret'] );
	}
	update_option( 'woocommerce_stripe_settings', $settings );

} else {
	// If WC_REMOVE_ALL_DATA constant is set to true in the merchant's wp-config.php,
	// remove ALL plugin settings.
	delete_option( 'woocommerce_stripe_settings' );

	// Individual payment methods settings
	delete_option( 'woocommerce_stripe_affirm_settings' );
	delete_option( 'woocommerce_stripe_afterpay_clearpay_settings' );
	delete_option( 'woocommerce_stripe_alipay_settings' );
	delete_option( 'woocommerce_stripe_bancontact_settings' );
	delete_option( 'woocommerce_stripe_boleto_settings' );
	delete_option( 'woocommerce_stripe_cashapp_settings' );
	delete_option( 'woocommerce_stripe_card_settings' );
	delete_option( 'woocommerce_stripe_eps_settings' );
	delete_option( 'woocommerce_stripe_giropay_settings' );
	delete_option( 'woocommerce_stripe_ideal_settings' );
	delete_option( 'woocommerce_stripe_klarna_settings' );
	delete_option( 'woocommerce_stripe_link_settings' );
	delete_option( 'woocommerce_stripe_multibanco_settings' );
	delete_option( 'woocommerce_stripe_oxxo_settings' );
	delete_option( 'woocommerce_stripe_p24_settings' );
	delete_option( 'woocommerce_stripe_sepa_settings' );
	delete_option( 'woocommerce_stripe_sepa_debit_settings' );
	delete_option( 'woocommerce_stripe_sofort_settings' );
	delete_option( 'woocommerce_stripe_wechat_pay_settings' );

	delete_option( 'woocommerce_gateway_stripe_retention' );
	delete_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' );

	delete_option( 'wc_stripe_elements_options' );
	delete_option( 'wc_stripe_version' );

	delete_option( 'wc_stripe_show_style_notice' );
	delete_option( 'wc_stripe_show_styles_notice' );
	delete_option( 'wc_stripe_show_ssl_notice' );
	delete_option( 'wc_stripe_show_request_api_notice' );
	delete_option( 'wc_stripe_show_apple_pay_notice' );
	delete_option( 'wc_stripe_show_keys_notice' );
	delete_option( 'wc_stripe_show_3ds_notice' );
	delete_option( 'wc_stripe_show_phpver_notice' );
	delete_option( 'wc_stripe_show_wcver_notice' );
	delete_option( 'wc_stripe_show_curl_notice' );
	delete_option( 'wc_stripe_show_sca_notice' );
	delete_option( 'wc_stripe_show_changed_keys_notice' );
	delete_option( 'wc_stripe_show_customization_notice' );
	delete_option( 'wc_stripe_show_payment_methods_notice' );
	delete_option( 'wc_stripe_show_upe_payment_methods_notice' );
	delete_option( 'wc_stripe_show_alipay_notice' );
	delete_option( 'wc_stripe_show_bancontact_notice' );
	delete_option( 'wc_stripe_show_eps_notice' );
	delete_option( 'wc_stripe_show_giropay_notice' );
	delete_option( 'wc_stripe_show_ideal_notice' );
	delete_option( 'wc_stripe_show_multibanco_notice' );
	delete_option( 'wc_stripe_show_oxxo_notice' );
	delete_option( 'wc_stripe_show_p24_notice' );
	delete_option( 'wc_stripe_show_sepa_notice' );
	delete_option( 'wc_stripe_show_sofort_notice' );

	// BNPL promotional banner
	delete_option( 'wc_stripe_show_bnpl_promotion_banner' );

	// OC promotional banner
	delete_option( 'wc_stripe_show_oc_promotion_banner' );

	// Webhook stats
	delete_option( 'wc_stripe_wh_monitor_began_at' );
	delete_option( 'wc_stripe_wh_last_success_at' );
	delete_option( 'wc_stripe_wh_last_failure_at' );
	delete_option( 'wc_stripe_wh_last_error' );
	delete_option( 'wc_stripe_wh_test_monitor_began_at' );
	delete_option( 'wc_stripe_wh_test_last_success_at' );
	delete_option( 'wc_stripe_wh_test_last_failure_at' );
	delete_option( 'wc_stripe_wh_test_last_error' );

	// OAuth connection stats
	delete_option( 'wc_stripe_oauth_updated_at' );
	delete_option( 'wc_stripe_oauth_failed_attempts' );
	delete_option( 'wc_stripe_oauth_last_failed_at' );
	delete_option( 'wc_stripe_test_oauth_updated_at' );
	delete_option( 'wc_stripe_test_oauth_failed_attempts' );
	delete_option( 'wc_stripe_test_oauth_last_failed_at' );

	// Feature flags
	delete_option( '_wcstripe_feature_upe' );
	delete_option( 'upe_checkout_experience_accepted_payments' );
	delete_option( '_wcstripe_feature_ece' );
}
