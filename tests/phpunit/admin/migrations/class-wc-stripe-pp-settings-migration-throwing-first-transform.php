<?php
/**
 * Test double for WC_Stripe_PP_Settings_Migration_Test.
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-pp-settings-migration.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/pp-settings-maps/class-wc-stripe-pp-settings-map-3x.php';
require_once __DIR__ . '/class-wc-stripe-pp-settings-map-3x-throwing-first.php';

/**
 * Overrides map_for_version() to inject a map whose first TRANSFORM callable always throws.
 * Used to verify that apply_transform_rows() isolates per-row exceptions.
 */
class WC_Stripe_PP_Settings_Migration_Throwing_First_Transform extends WC_Stripe_PP_Settings_Migration {
	protected static function map_for_version( string $version ): ?WC_Stripe_PP_Settings_Map {
		return new WC_Stripe_PP_Settings_Map_3X_Throwing_First();
	}
}
