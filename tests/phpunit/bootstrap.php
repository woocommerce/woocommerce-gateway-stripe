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

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Stripe Payment Gateway plugin.
	require_once ABSPATH . '/wp-content/plugins/woocommerce/woocommerce.php';
	require dirname( __FILE__ ) . '/setup.php';

	$_plugin_dir = dirname( __FILE__, 3 );
	require $_plugin_dir . '/woocommerce-gateway-stripe.php';

	// REST API.
	require_once $_plugin_dir . '/includes/admin/class-wc-stripe-rest-base-controller.php';
	require_once $_plugin_dir . '/includes/admin/class-wc-rest-stripe-settings-controller.php';
	require_once $_plugin_dir . '/includes/admin/class-wc-rest-stripe-account-keys-controller.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

# Load WooCommerce Helpers (https://github.com/woocommerce/woocommerce/tree/master/tests/legacy/framework/helpers)
# To keep the plugin self-contained, copy any needed helper to the `helpers/` sub-folder.
require_once __DIR__ . '/helpers/class-upe-test-helper.php';
require_once __DIR__ . '/helpers/class-wc-helper-product.php';
require_once __DIR__ . '/helpers/class-wc-helper-shipping.php';
require_once __DIR__ . '/helpers/class-wc-helper-order.php';
require_once __DIR__ . '/helpers/class-wc-helper-token.php';
require_once __DIR__ . '/helpers/class-wc-helper-stripe-api.php';
