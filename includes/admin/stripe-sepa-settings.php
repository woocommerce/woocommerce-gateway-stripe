<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$webhook_url = WC_Stripe_Helper::get_webhook_url();

return apply_filters( 'wc_stripe_sepa_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Mandate Information.', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'webhook' => array(
			'title'       => __( 'Webhook Enpoints', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			'description' => __( 'You must add the webhook endpoint <strong style="background-color:#ddd;">&nbsp;&nbsp;' . $webhook_url . '&nbsp;&nbsp;</strong> to your Stripe Account Settings <a href="https://dashboard.stripe.com/account/webhooks" target="_blank">Here</a> so you can receive notifications on the charge statuses.', 'woocommerce-gateway-stripe' ),
		),
	)
);
