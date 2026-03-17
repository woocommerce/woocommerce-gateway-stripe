<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Stripe
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/class-wc-stripe-test-suite-loader.php';

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
	require_once __DIR__ . '/helpers/class-wcs-background-repairer.php';

	$_plugin_dir = __DIR__ . '/../../';
	require $_plugin_dir . 'woocommerce-gateway-stripe.php';

	// REST API.
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-base-controller.php';
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-settings-controller.php';
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-account-keys-controller.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// When paratest runs workers in parallel, each gets a unique TEST_TOKEN.
// The per-worker databases (e.g. wc_stripe_tests_1) are created by install-wp-tests.sh
// before paratest starts. We attempt creation here as a safety net, but silently
// skip on failure to avoid writing to STDERR (which paratest interprets as a test error).
$_test_token = getenv( 'TEST_TOKEN' );
if ( $_test_token ) {
	$_wp_db_host    = getenv( 'WORDPRESS_DB_HOST' );
	$_db_host_parts = explode( ':', $_wp_db_host ? $_wp_db_host : 'db' );
	$_db_host       = $_db_host_parts[0];
	$_db_port       = isset( $_db_host_parts[1] ) ? (int) $_db_host_parts[1] : 3306;
	$_worker_db     = 'wc_stripe_tests_' . $_test_token;
	mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB -- prevent exceptions so we can fail silently.
	$_mysql_pass = getenv( 'MYSQL_ROOT_PASSWORD' );
	$_mysqli     = @new mysqli( $_db_host, 'root', $_mysql_pass ? $_mysql_pass : '', '', $_db_port ); // phpcs:ignore WordPress.DB
	if ( ! $_mysqli->connect_error ) {
		$_mysqli->query( "CREATE DATABASE IF NOT EXISTS `{$_worker_db}`" ); // phpcs:ignore WordPress.DB
		$_mysqli->close();
	}
	unset( $_wp_db_host, $_db_host_parts, $_db_host, $_db_port, $_worker_db, $_mysql_pass, $_mysqli );
}
unset( $_test_token );

require $_tests_dir . '/includes/bootstrap.php';

# Load WooCommerce Helpers (https://github.com/woocommerce/woocommerce/tree/master/tests/legacy/framework/helpers)
# To keep the plugin self-contained, copy any needed helper to the `helpers/` sub-folder.
# These helpers cannot be autoloaded, so we need to require them manually.
require_once __DIR__ . '/helpers/class-wc-subscription.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-cart.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-helpers.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-product.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-switcher.php';
require_once __DIR__ . '/helpers/class-wc-pre-orders-product.php';
require_once __DIR__ . '/helpers/class-wc-deposits-product-manager.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-change-payment-gateway.php';
