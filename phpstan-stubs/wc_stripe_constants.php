<?php

/**
 * Stubs for constants defined via function calls in WooCommerce Stripe that
 * PHPStan doesn't seem to pick up correctly. As of 2025-12-24, this is not resolved.
 *
 * @see https://github.com/phpstan/phpstan/issues/11210
 */

if ( ! defined( 'WC_STRIPE_PLUGIN_URL' ) ) {
	define( 'WC_STRIPE_PLUGIN_URL', 'https://example.com/wp-content/plugins/woocommerce-gateway-stripe' );
}

if ( ! defined( 'WC_STRIPE_PLUGIN_PATH' ) ) {
	// This definition is not correct, but we can't use a function like dirname( __DIR__ ) as PHPStan can't resolve it.
	define( 'WC_STRIPE_PLUGIN_PATH', __DIR__ );
}
