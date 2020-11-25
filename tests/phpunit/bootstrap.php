<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP8.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	$plugin_dir = dirname( dirname( dirname( __DIR__ ) ) );
	require $plugin_dir . '/woocommerce/woocommerce.php';
	require __DIR__ . '/setup.php';
	require $plugin_dir . '/woocommerce-gateway-stripe/woocommerce-gateway-stripe.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

$wc_tests_framework_base_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/woocommerce/tests/framework';
require_once( $wc_tests_framework_base_dir . '/class-wc-mock-session-handler.php' );
require_once( $wc_tests_framework_base_dir . '/helpers/class-wc-helper-product.php' );
require_once( $wc_tests_framework_base_dir . '/helpers/class-wc-helper-coupon.php' );
require_once( $wc_tests_framework_base_dir . '/helpers/class-wc-helper-fee.php' );
require_once( $wc_tests_framework_base_dir . '/helpers/class-wc-helper-shipping.php' );
require_once( $wc_tests_framework_base_dir . '/helpers/class-wc-helper-customer.php' );
require_once( $wc_tests_framework_base_dir . '/helpers/class-wc-helper-order.php' );
