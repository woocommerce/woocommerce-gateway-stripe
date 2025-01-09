<?php
// This file is the body of WC_Gateway_Stripe_Eps::init_form_fields().

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_stripe_eps_settings',
	[
		'geo_target'  => [
			'description' => __( 'Customer Geography: Austria', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		],
		'activation'  => [
			'description' => __( 'Must be activated from your Stripe Dashboard Settings <a href="https://dashboard.stripe.com/account/payments/settings" target="_blank">here</a>', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		],
		'enabled'     => [
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe EPS', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'       => [
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'EPS', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'description' => [
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'You will be redirected to EPS.', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'webhook'     => [
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			/* translators: webhook URL */
			'description' => $this->display_admin_settings_webhook_description(), // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
		],
	]
);
