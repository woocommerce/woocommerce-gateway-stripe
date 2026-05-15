<?php
/**
 * Test double for WC_Stripe_PP_Settings_Migration_Test.
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/pp-settings-maps/class-wc-stripe-pp-settings-map-3x.php';

/**
 * Extends the 3.X map with a first TRANSFORM row whose callable always throws.
 * Used to verify transformer-exception isolation in the orchestrator.
 */
class WC_Stripe_PP_Settings_Map_3X_Throwing_First extends WC_Stripe_PP_Settings_Map_3X {
	public function get_transform_rows(): array {
		$rows                   = parent::get_transform_rows();
		$rows[0]['transformer'] = static function () {
			throw new \RuntimeException( 'Injected transformer exception for isolation test' );
		};
		return $rows;
	}
}
