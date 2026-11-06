<?php
/**
 * Plugin Name: WC Stripe E2E Checkout Fields
 * Description: Lets E2E tests omit or add selected WooCommerce checkout fields.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		if ( isset( $_COOKIE['wc_stripe_e2e_without_billing_country'] ) ) {
			unset( $fields['billing']['billing_country'] );
		}

		// Cookie-gated so the required field exists only for specs that opt
		// in; registered unconditionally it would block every other spec's
		// checkout. The express checkout Store API POST carries the same
		// cookie, so server-side validation sees the same field list.
		if ( isset( $_COOKIE['wc_stripe_e2e_required_custom_field'] ) ) {
			$fields['billing']['billing_e2e_custom_field'] = [
				'type'     => 'text',
				'label'    => 'E2E custom field',
				'required' => true,
			];
		}

		return $fields;
	}
);
