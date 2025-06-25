<?php

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
			// translators: 1) an order number, 2) the reason for the refund request failure.
			_x(
				'The refund for order %1$s has failed. Reason:  %2$s.',
				'In customer refund failed email',
				'woocommerce-gateway-stripe'
			),
			$order->get_order_number(),
			$reason
		)
	);
	?>
</p>
<p><?php esc_html_e( 'The order details are as follows:', 'woocommerce-gateway-stripe' ); ?></p>

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
