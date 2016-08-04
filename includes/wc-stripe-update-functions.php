<?php
/**
 * WooCommerce Stripe Updates.
 *
 * Functions for updating data, used by the background updater.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wc_stripe_303_copy_saved_cards_to_token_table() {
	global $wpdb;

	WC_Stripe::log( 'updating saved cards...' );
}

function wc_stripe_303_db_version() {
	$wc_stripe->update_stripe_db_version( '3.0.3' );
}
