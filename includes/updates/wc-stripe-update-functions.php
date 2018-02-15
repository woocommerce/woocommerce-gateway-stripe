<?php
/**
 * Functions for update Stripe
 *
 * @since: 4.1.0
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WC_STRIPE_VERSION' ) ) {
	exit;
}

/**
 * 4.1.0 UPDATE
 */
function wc_stripe_update_410() {
	wc_stripe_update_410_metaname();
}

/**
 * Update meta_key name for "fee" & "net revenue".
 */
function wc_stripe_update_410_metaname() {
	global $wpdb;

	// Fees.
	$wpdb->query( "
		UPDATE $wpdb->postmeta
		SET `meta_key` = '_stripe_fee'
		WHERE `meta_key` = 'Stripe Fee'
	" );

	// Net.
	$wpdb->query( "
		UPDATE $wpdb->postmeta
		SET `meta_key` = '_stripe_net'
		WHERE `meta_key` = 'Net Revenue From Stripe'
	" );
}
