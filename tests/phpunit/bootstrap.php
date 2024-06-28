<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Stripe
 */

require_once __DIR__ . '/../../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP8.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Stripe Payment Gateway plugin.
	require_once ABSPATH . '/wp-content/plugins/woocommerce/woocommerce.php';
	require __DIR__ . '/setup.php';
	require_once __DIR__ . '/helpers/class-wc-helper-subscriptions-background-repairer.php';

	$_plugin_dir = __DIR__ . '/../../';
	require $_plugin_dir . 'woocommerce-gateway-stripe.php';

	// REST API.
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-base-controller.php';
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-settings-controller.php';
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-account-keys-controller.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

# Load WooCommerce Helpers (https://github.com/woocommerce/woocommerce/tree/master/tests/legacy/framework/helpers)
# To keep the plugin self-contained, copy any needed helper to the `helpers/` sub-folder.
require_once __DIR__ . '/helpers/class-upe-test-helper.php';
require_once __DIR__ . '/helpers/class-wc-helper-product.php';
require_once __DIR__ . '/helpers/class-wc-helper-shipping.php';
require_once __DIR__ . '/helpers/class-wc-helper-order.php';
require_once __DIR__ . '/helpers/class-wc-helper-subscription.php';
require_once __DIR__ . '/helpers/class-wc-helper-subscriptions.php';
require_once __DIR__ . '/helpers/class-wc-helper-subscriptions-cart.php';
require_once __DIR__ . '/helpers/class-wc-helper-token.php';
require_once __DIR__ . '/helpers/class-wc-helper-stripe-api.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-helper.php';
