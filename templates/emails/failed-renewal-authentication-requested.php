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

/**
 * Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php
	echo esc_html(
		sprintf(
			// translators: %1$s: an order number, %2$s: the customer's full name, %3$s: lowercase human time diff in the form returned by wcs_get_human_time_diff(), e.g. 'in 12 hours'.
			_x(
				'The automatic recurring payment for order %1$s from %2$s has failed. The customer was sent an email requesting authentication of payment. If the customer does not authenticate the payment, they will be requested by email again %3$s.',
				'In admin renewal failed email',
				'woocommerce-gateway-stripe'
			),
			$order->get_order_number(),
			$order->get_formatted_billing_full_name(),
			wcs_get_human_time_diff( $retry->get_time() )
		)
	);
	?>
</p>
<p><?php esc_html_e( 'The renewal order is as follows:', 'woocommerce-gateway-stripe' ); ?></p>

<?php

/**
 * Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
* Shows order meta data.
*/
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
* Shows customer details, and email address.
*/
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/**
* Output the email footer.
*/
do_action( 'woocommerce_email_footer', $email );
