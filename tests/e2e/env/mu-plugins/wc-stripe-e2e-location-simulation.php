<?php
/**
 * Plugin Name: WC Stripe E2E Location Simulation
 * Description: Lets e2e tests simulate the shopper's country for Adaptive Pricing using Stripe's documented "+location_XX" customer_email test hook.
 *
 * @see https://docs.stripe.com/payments/currencies/localize-prices/adaptive-pricing?payment-ui=embedded-components#testing
 *
 * Copied into mu-plugins by tests/e2e/bin/run-tests.sh for the adaptive-pricing
 * project. Tests opt in per-request via the wc_stripe_e2e_location cookie
 * (a two-letter country code); other tests in the project are unaffected.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wc_stripe_request_body',
	function ( $request, $api ) {
		// Stripe rejects customer_email alongside customer, so only guest
		// sessions (no attached Stripe customer) can carry the simulation.
		if ( 'checkout/sessions' !== $api || ! empty( $request['customer'] ) ) {
			return $request;
		}

		$location = isset( $_COOKIE['wc_stripe_e2e_location'] ) ? strtoupper( sanitize_key( wp_unslash( $_COOKIE['wc_stripe_e2e_location'] ) ) ) : '';
		if ( preg_match( '/^[A-Z]{2}$/', $location ) ) {
			$request['customer_email'] = 'test+location_' . $location . '@example.com';
		}

		return $request;
	},
	10,
	2
);
