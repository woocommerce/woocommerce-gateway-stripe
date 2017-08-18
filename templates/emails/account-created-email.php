<?php
/**
 * Account creation email to customer (plain text).
 *
 * @since 4.0.0
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo __( 'Hello! Thank you for visiting our website.', 'woocommerce-gateway-stripe' ) . "\n\n";

echo __( 'We have created an account for you. This gives you the convenience of checking on statuses of purchases and more.', 'woocommerce-gateway-stripe' ) . "\n\n";

echo __( 'Here is your login account information:', 'woocommerce-gateway-stripe' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf( __( 'Login Address: %s', 'woocommerce-gateway-stripe' ), wp_login_url() ) . "\n\n";
echo sprintf( __( 'Login Name: %s', 'woocommerce-gateway-stripe' ), $user_login ) . "\n\n";

echo __( 'Click the link below to set your password and gain access to your account.', 'woocommerce-gateway-stripe' ) . "\n";

echo esc_url_raw( add_query_arg( array( 'action' => 'rp', 'key' => $password_reset_key, 'login' => rawurlencode( $user_login ) ), wp_login_url() ) ) . "\n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
