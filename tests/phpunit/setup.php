<?php
/**
 * Set up shared by all tests.
 */
update_option( 'woocommerce_default_country', 'US:CA' );

if ( version_compare( WC_VERSION, '6.4.1', '=' ) ) {
	\Automattic\WooCommerce\Internal\Admin\Install::create_tables();
	\Automattic\WooCommerce\Internal\Admin\Install::create_events();
}
