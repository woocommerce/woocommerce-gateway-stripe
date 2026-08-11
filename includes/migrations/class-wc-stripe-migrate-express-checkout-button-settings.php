<?php
/**
 * Class WC_Stripe_Migrate_Express_Checkout_Button_Settings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collapses the per-method express checkout button settings into a single
 * location => methods map (`express_checkout_button_locations`) plus one shared
 * button size (`express_checkout_button_size`).
 *
 * @since 10.9.0
 */
class WC_Stripe_Migrate_Express_Checkout_Button_Settings {
	/**
	 * Flag ensuring the migration runs only once.
	 *
	 * @var string
	 */
	public const MIGRATED_OPTION = 'wc_stripe_ece_unified_button_settings_migrated';

	/**
	 * Run the migration once.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		if ( 'yes' === get_option( self::MIGRATED_OPTION ) ) {
			return;
		}

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$stored_locations = $settings['express_checkout_button_locations'] ?? null;

		// A unified map means a previous (or concurrent) pass already migrated —
		// possible when the one-shot flag is lost. Feeding the map back through the
		// legacy converter would corrupt it, so it is kept authoritative as-is.
		$already_unified = is_array( $stored_locations )
			&& WC_Stripe_Express_Checkout_Helper::is_locations_map( $stored_locations );

		$has_per_method_leftovers = isset( $settings['link_button_locations'] )
			|| isset( $settings['amazon_pay_button_locations'] )
			|| isset( $settings['link_button_size'] )
			|| isset( $settings['amazon_pay_button_size'] );

		// Fresh installs have no legacy data to collapse; they pick up the default map
		// at render time. Orphan per-method sizes alone must not trigger a conversion,
		// which would store an empty map and suppress that default.
		$needs_conversion = ! $already_unified
			&& ( null !== $stored_locations
				|| isset( $settings['link_button_locations'] )
				|| isset( $settings['amazon_pay_button_locations'] ) );

		if ( $needs_conversion ) {
			// Build the map before overwriting the option it partly derives from.
			$settings['express_checkout_button_locations'] = WC_Stripe_Express_Checkout_Helper::build_locations_map_from_legacy( $settings );
		}

		if ( $needs_conversion || $has_per_method_leftovers ) {
			// Per-method sizes are dropped; the shared `express_checkout_button_size` is left untouched.
			unset(
				$settings['link_button_locations'],
				$settings['amazon_pay_button_locations'],
				$settings['link_button_size'],
				$settings['amazon_pay_button_size']
			);

			WC_Stripe_Helper::update_main_stripe_settings( $settings );
		}

		update_option( self::MIGRATED_OPTION, 'yes' );
	}
}
