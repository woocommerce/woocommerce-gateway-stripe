<?php
/**
 * PP 4.X → Woo Stripe settings mapping placeholder.
 *
 * PP 4.X mapping is deferred until PP 4.X ships and its option layout is
 * analyzed. Until then the orchestrator falls back to PP 3.X best-effort
 * mapping with a logged warning when version detection returns '4'.
 *
 * When PP 4.X ships:
 *   1. Analyze PP 4.X's option-blob structure (likely option key renames or
 *      blob consolidation).
 *   2. Populate the methods below.
 *   3. Add tests in `tests/phpunit/admin/migrations/class-wc-stripe-pp-settings-map-4x-test.php`.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Concrete map for Payment Plugins for Stripe v4.X (placeholder).
 *
 * @since 10.8.0
 */
class WC_Stripe_PP_Settings_Map_4X extends WC_Stripe_PP_Settings_Map {

	public function get_auto_rows(): array {
		return [];
	}

	public function get_transform_rows(): array {
		return [];
	}

	public function get_dropped_rows(): array {
		return [];
	}

	public function get_investigate_rows(): array {
		return [];
	}

	public function get_build_rows(): array {
		return [];
	}
}
