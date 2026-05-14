<?php
/**
 * Abstract base class for PP→Woo Stripe settings maps.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines the per-major-version mapping contract used by the PP settings
 * migration orchestrator. Concrete subclasses (one per PP major version)
 * return the AUTO/TRANSFORM/DROP/INVESTIGATE/BUILD row lists.
 *
 * @since 9.7.0
 */
abstract class WC_Stripe_PP_Settings_Map {

	/**
	 * AUTO rows: direct source→destination copy.
	 *
	 * Each row shape:
	 *   [
	 *     'source_option' => string,  // PP wp_options key (e.g., 'woocommerce_stripe_cc_settings')
	 *     'source_key'    => string,  // PP nested key within the option blob (e.g., 'enabled')
	 *     'dest_option'   => string,  // Woo Stripe wp_options key (typically 'woocommerce_stripe_settings')
	 *     'dest_key'      => string,  // Woo Stripe nested key within the option blob
	 *   ]
	 *
	 * @return array<int, array<string, string>>
	 */
	abstract public function get_auto_rows(): array;

	/**
	 * TRANSFORM rows: source→destination with a value/shape transformer.
	 *
	 * Each row shape extends the AUTO row with:
	 *   'transformer' => callable  // fn(mixed $source_value): mixed
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function get_transform_rows(): array;

	/**
	 * DROP rows: documented no-ops. Returned for ledger/audit visibility; no code path executes for these.
	 *
	 * @return array<int, string> PP setting names that we explicitly do not migrate.
	 */
	abstract public function get_dropped_rows(): array;

	/**
	 * INVESTIGATE rows: pending product decision. Not migrated; recorded in the ledger for audit.
	 *
	 * @return array<int, string>
	 */
	abstract public function get_investigate_rows(): array;

	/**
	 * BUILD rows: features absent in Woo Stripe; cannot migrate without destination. Recorded for tracking.
	 *
	 * @return array<int, string>
	 */
	abstract public function get_build_rows(): array;
}
