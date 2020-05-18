<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php
$billing_email = $order->get_billing_email();
$billing_phone = $order->get_billing_phone();

?>
<p><?php
	echo wp_kses(
		sprintf(
			// translators: %s is a link to the payment re-authentication URL.
			_x( 'Your pre-order is now available, but payment cannot be completed automatically. %s', 'In failed SCA authentication for a pre-order.', 'woocommerce-gateway-stripe' ),
			'<a href="' . esc_url( $authorization_url ) . '">' . esc_html__( 'Authorize the payment now &raquo;', 'woocommerce-gateway-stripe' ) . '</a>'
		),
		array( 'a' => array( 'href' => true ) )
	);
?></p>

<?php if ( $email->get_custom_message() ) : ?>
	<blockquote><?php echo wpautop( wptexturize( $email->get_custom_message() ) ); ?></blockquote>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_before_order_table', $order, false, $plain_text, $email );

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_after_order_table', $order, false, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

?>
<p>
<?php esc_html_e( 'Thanks for shopping with us.', 'woocommerce-gateway-stripe' ); ?>
</p>
<?php

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
