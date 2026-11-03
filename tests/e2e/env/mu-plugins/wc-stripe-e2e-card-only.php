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

		// Use local card selection for this request without changing Stripe's
		// shared payment-method configuration or another browser's Link state.
		$settings['pmc_enabled']                               = 'no';
		$settings['upe_checkout_experience_accepted_payments'] = [ 'card' ];

		return $settings;
	}
);
