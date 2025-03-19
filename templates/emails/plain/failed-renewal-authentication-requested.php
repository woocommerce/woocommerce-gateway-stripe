<?php
/**
 * Admin email about payment retry failed due to authentication
 *
 * @package WooCommerce_Stripe/Templates/Emails
 * @version 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '= ' . esc_html( $email_heading ) . " =\n\n";

$is_preview = apply_filters( 'woocommerce_is_email_preview', false );
$retry_time = '';

if ( $is_preview ) {
	$interval = 12 * HOUR_IN_SECONDS;
	$retry_time = human_time_diff( time(), time() + ( $interval ) );
} elseif ( is_a( $retry, 'WCS_Retry' ) && method_exists( $retry, 'get_time' ) ) {
	$retry_time = wcs_get_human_time_diff( $retry->get_time() );
}

printf(
	// translators: 1) an order number, 2) the customer's full name, 3) lowercase human time diff in the form returned by wcs_get_human_time_diff(), e.g. 'in 12 hours'.
	esc_html_x(
		'The automatic recurring payment for order %1$s from %2$s has failed. The customer was sent an email requesting authentication of payment. If the customer does not authenticate the payment, they will be requested by email again %3$s.',
		'In admin renewal failed email',
		'woocommerce-gateway-stripe'
	),
	esc_html( $order->get_order_number() ),
	esc_html( $order->get_formatted_billing_full_name() ),
	esc_html( $retry_time )
) . "\n\n";
printf( esc_html__( 'The renewal order is as follows:', 'woocommerce-gateway-stripe' ) ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
* Shows order meta data.
*/
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
* Shows customer details, and email address.
*/
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
