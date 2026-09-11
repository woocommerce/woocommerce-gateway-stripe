<?php
/**
 * Plugin Name: WC Stripe E2E Checkout Fields
 * Description: Lets E2E tests omit selected WooCommerce checkout fields.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		if ( isset( $_COOKIE['wc_stripe_e2e_without_billing_country'] ) ) {
			unset( $fields['billing']['billing_country'] );
		}

		return $fields;
	}
);
