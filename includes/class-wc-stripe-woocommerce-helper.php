<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Provides static methods as helpers for WooCommerce-related features.
 *
 * @since 8.6.0
 */
class WC_Stripe_WooCommerce_Helper {
	/**
	 * Checks if the custom orders table is enabled.
	 *
	 * @return bool Whether the custom orders table is enabled.
	 */
	public static function is_custom_orders_table_enabled() {
		return class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
