<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_stripe_upe_settings',
	[
		'enabled'     => [
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe UPE', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'       => [
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Stripe UPE', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'description' => [
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Stripe new checkout experience.', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'upe_checkout_experience'                   => [
			'title'       => __( 'Checkout experience', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
		],
		'upe_checkout_experience_enabled'           => [
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable new checkout experience', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will... TBD', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'upe_checkout_experience_accepted_payments' => [
			'type'        => 'upe_checkout_experience_accepted_payments',
		],
	]
);
