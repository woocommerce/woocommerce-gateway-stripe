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
// The base DB name defaults to 'wc_stripe_tests' (matching install-wp-tests.sh) but can
// be overridden via the WP_TESTS_DB_NAME environment variable.
$_test_token = getenv( 'TEST_TOKEN' );
if ( $_test_token && ctype_digit( (string) $_test_token ) ) {
	$_wp_db_host    = getenv( 'WORDPRESS_DB_HOST' );
	$_db_host_parts = explode( ':', $_wp_db_host ? $_wp_db_host : 'db' );
	$_db_host       = $_db_host_parts[0];
	$_db_port       = isset( $_db_host_parts[1] ) ? (int) $_db_host_parts[1] : 3306;
	$_base_db_name  = getenv( 'WP_TESTS_DB_NAME' ) ? getenv( 'WP_TESTS_DB_NAME' ) : 'wc_stripe_tests';
	$_worker_db     = $_base_db_name . '_' . $_test_token;
	mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB -- prevent exceptions so we can fail silently.
	$_mysql_pass = getenv( 'MYSQL_ROOT_PASSWORD' );
	$_mysqli     = @new mysqli( $_db_host, 'root', $_mysql_pass ? $_mysql_pass : '', '', $_db_port ); // phpcs:ignore WordPress.DB
	if ( ! $_mysqli->connect_error ) {
		$_mysqli->query( "CREATE DATABASE IF NOT EXISTS `{$_worker_db}`" ); // phpcs:ignore WordPress.DB
		$_mysqli->close();
	}
	unset( $_wp_db_host, $_db_host_parts, $_db_host, $_db_port, $_base_db_name, $_worker_db, $_mysql_pass, $_mysqli );
}
unset( $_test_token );

require $_tests_dir . '/includes/bootstrap.php';

# Load test helpers manually. The helpers/ directory is excluded from the Composer classmap
# (to prevent stub classes like WC_Subscriptions from being autoloaded in E2E environments
# where the real plugin is active), so all helpers must be explicitly required here.
require_once __DIR__ . '/helpers/class-wc-subscription.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-cart.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-helpers.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-product.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-switcher.php';
require_once __DIR__ . '/helpers/class-wc-pre-orders-product.php';
require_once __DIR__ . '/helpers/class-wc-deposits-product-manager.php';
require_once __DIR__ . '/helpers/class-wc-subscriptions-change-payment-gateway.php';
require_once __DIR__ . '/helpers/class-wc-helper-order.php';
require_once __DIR__ . '/helpers/class-wc-helper-product.php';
require_once __DIR__ . '/helpers/class-wc-helper-shipping.php';
require_once __DIR__ . '/helpers/class-wc-helper-stripe-api.php';
require_once __DIR__ . '/helpers/class-wc-helper-token.php';
require_once __DIR__ . '/helpers/class-wcs-staging.php';
require_once __DIR__ . '/helpers/class-ajax-test-helper.php';
require_once __DIR__ . '/helpers/class-oc-test-helper.php';
require_once __DIR__ . '/helpers/class-pmc-test-helper.php';
require_once __DIR__ . '/helpers/class-upe-test-helper.php';

// Pre-create HPOS (Custom Orders Table) schema so that parallel workers don't
// race to create it when tests toggle `woocommerce_custom_orders_table_enabled`.
if ( class_exists( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class ) ) {
	$_data_sync = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
	if ( ! $_data_sync->check_orders_table_exists() ) {
		$_data_sync->create_database_tables();
	}
	unset( $_data_sync );
}
