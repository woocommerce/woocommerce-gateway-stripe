<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_stripe_sepa_settings',
	array(
		'geo_target'                   => array(
			'description' => __( 'Customer Geography: France, Germany, Spain, Belgium, Netherlands, Luxembourg, Italy, Portugal, Austria, Ireland', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		),
		'guide'                        => array(
			'description' => __( '<a href="https://stripe.com/payments/payment-methods-guide#sepa-direct-debit" target="_blank">Payment Method Guide</a>', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		),
		'activation'                   => array(
			'description' => __( 'Must be activated from your Stripe Dashboard Settings <a href="https://dashboard.stripe.com/account/payments/settings" target="_blank">here</a>', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		),
		'enabled'                      => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'activate_subscriptions_early' => array(
			'title'       => __( 'Subscriptions Status', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Make subscriptions active while waiting on the payment process to complete', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'                        => array(
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'description'                  => array(
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Mandate Information.', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'webhook'                      => array(
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			/* translators: webhook URL */
			'description' => $this->display_admin_settings_webhook_description(),
		),
	)
);
