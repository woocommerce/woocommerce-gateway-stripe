<?php
/**
 * Class WC_Stripe_Migrate_Link_Button_Locations
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_Migrate_Link_Button_Locations
 *
 * Before Link had a dedicated `link_button_locations` setting, it inherited its
 * locations from the payment request (Apple Pay / Google Pay) button. This
 * migration copies those locations into the new setting on upgrade so existing
 * merchants keep their configured Link placement.
 *
 * @since 10.9.0
 */
class WC_Stripe_Migrate_Link_Button_Locations {
	/**
	 * Only execute the migration if not applied yet.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// If Link already has its own locations, there is nothing to migrate.
		if ( isset( $stripe_settings['link_button_locations'] ) ) {
			return;
		}

		// If payment request locations were never saved, there is nothing to copy.
		if ( ! isset( $stripe_settings['express_checkout_button_locations'] ) ) {
			return;
		}

		$stripe_settings['link_button_locations'] = $stripe_settings['express_checkout_button_locations'];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}
}
