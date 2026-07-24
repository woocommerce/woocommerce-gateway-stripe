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
		$stripe_settings = WC_Stripe::get_instance()->get_settings();

		// If Link already has its own locations, there is nothing to migrate.
		if ( isset( $stripe_settings['link_button_locations'] ) ) {
			return;
		}

		// If payment request locations were never saved, there is nothing to copy.
		if ( ! isset( $stripe_settings['express_checkout_button_locations'] ) ) {
			return;
		}

		$express_locations = $stripe_settings['express_checkout_button_locations'];

		// Express checkout supports the express-only `change_payment_method` location
		// (when Subscriptions is active), which Link does not. Drop anything outside
		// Link's own allowed locations.
		if ( is_array( $express_locations ) ) {
			$form_fields       = WC_Stripe::get_instance()->get_main_stripe_gateway()->get_form_fields();
			$allowed_locations = array_keys( $form_fields['link_button_locations']['options'] ?? [] );
			$express_locations = array_values( array_intersect( $express_locations, $allowed_locations ) );
		}

		$stripe_settings['link_button_locations'] = $express_locations;
		WC_Stripe::get_instance()->update_settings( $stripe_settings );
	}
}
