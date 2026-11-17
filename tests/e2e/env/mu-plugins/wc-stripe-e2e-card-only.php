<?php
/**
 * Plugin Name: WC Stripe E2E Card Only
 * Description: Provides a browser-scoped card-only configuration for saved-card tests.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'option_woocommerce_stripe_settings',
	function ( $settings ) {
		if (
			! isset( $_COOKIE['wc_stripe_e2e_card_only'] ) ||
			! is_array( $settings ) ||
			'yes' !== ( $settings['testmode'] ?? 'no' )
		) {
			return $settings;
		}

		// Keep card selection local so concurrent tests retain their shared Stripe and Link settings.
		$settings['pmc_enabled']                               = 'no';
		$settings['upe_checkout_experience_accepted_payments'] = [ 'card' ];

		return $settings;
	}
);
