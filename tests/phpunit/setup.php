<?php
/**
 * Set up shared by all tests.
 */
update_option( 'woocommerce_default_country', 'US:CA' );

/**
 * Disables the transient cooldown for the payment method configuration fetch.
 */
add_filter(
	'transient_wcstripe_payment_method_config_fetch_cooldown',
	function () {
		return 0;
	}
);
