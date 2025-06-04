<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Page_Helper class.
 *
 * Class to identify current page/screen.
 */
class WC_Stripe_Page_Helper {
	/**
	 * Checks if the current page is a subscription edit page in wp-admin.
	 *
	 * @return bool
	 */
	public static function is_subscription_edit_page() {
		$query_params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( WC_Stripe_Woo_Compat_Utils::is_custom_orders_table_enabled() ) { // If custom order tables are enabled, we need to check the page query param.
			return isset( $query_params['page'] ) && 'wc-orders--shop_subscription' === $query_params['page'] && isset( $query_params['id'] );
		}

		// If custom order tables are not enabled, we need to check the post type and action query params.
		$is_shop_order_post_type = isset( $query_params['post'] ) && 'shop_order' === get_post_type( $query_params['post'] );
		return isset( $query_params['action'] ) && 'edit' === $query_params['action'] && $is_shop_order_post_type;
	}
}
