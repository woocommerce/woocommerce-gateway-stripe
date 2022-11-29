<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_stripe_boleto_settings',
	[
		'geo_target'  => [
			'description' => __( 'Customer Geography: Brazil', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		],
		'activation'  => [
			'description' => sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
				esc_html__( 'Must be activated from your Stripe Dashboard Settings %1$shere%2$s', 'woocommerce-gateway-stripe' ),
				'<a href="https://dashboard.stripe.com/account/payments/settings" target="_blank">',
				'</a>'
			),
			'type'        => 'title',
		],
		'enabled'     => [
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe Boleto', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'       => [
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Boleto', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'description' => [
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( "You'll be able to download or print the Boleto after checkout.", 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'expiration' => [
			'title'       => __( 'Expiration', 'woocommerce-gateway-stripe' ),
			'type'        => 'number',
			'description' => __( 'This controls the expiration in number of days for the voucher.', 'woocommerce-gateway-stripe' ),
			'default'     => 3,
			'desc_tip'    => true,
		],
		'webhook'     => [
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			/* translators: webhook URL */
			'description' => $this->display_admin_settings_webhook_description(),
		],
	]
);
