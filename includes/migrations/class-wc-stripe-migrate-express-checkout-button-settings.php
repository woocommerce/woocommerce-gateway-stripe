<?php
/**
 * Class WC_Stripe_Migrate_Express_Checkout_Button_Settings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collapses the per-method express checkout button settings into a unified model:
 * a single location => methods map (under `express_checkout_button_locations`)
 * plus one shared button size (`express_checkout_button_size`).
 *
 * Each express method (payment_request, link, amazon_pay) previously stored its
 * own `*_button_locations` and `*_button_size`. That was redundant and could
 * render express buttons at mismatched sizes on the same page.
 *
 * @since 10.9.0
 */
class WC_Stripe_Migrate_Express_Checkout_Button_Settings {
	/**
	 * Option flag set once the migration has run, so it executes only once.
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

		// Only rewrite settings when there is legacy per-method data to collapse.
		// Fresh installs have none and pick up the default map at render time.
		$has_legacy_locations = isset( $settings['express_checkout_button_locations'] )
			|| isset( $settings['link_button_locations'] )
			|| isset( $settings['amazon_pay_button_locations'] );

		if ( $has_legacy_locations ) {
			// Build the map before overwriting the option it partly derives from.
			$settings['express_checkout_button_locations'] = WC_Stripe_Express_Checkout_Helper::build_locations_map_from_legacy( $settings );

			// The per-method sizes are dropped in favour of the shared
			// `express_checkout_button_size`, which is left untouched.
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
