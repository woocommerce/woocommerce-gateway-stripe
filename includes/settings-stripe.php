<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_stripe_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no'
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Credit Card (Stripe)', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Pay with your credit card via Stripe.', 'woocommerce-gateway-stripe'),
			'desc_tip'    => true,
		),
		'testmode' => array(
			'title'       => __( 'Test mode', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_secret_key' => array(
			'title'       => __( 'Test Secret Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_publishable_key' => array(
			'title'       => __( 'Test Publishable Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'secret_key' => array(
			'title'       => __( 'Live Secret Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'publishable_key' => array(
			'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'capture' => array(
			'title'       => __( 'Capture', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'stripe_checkout' => array(
			'title'       => __( 'Stripe Checkout', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe Checkout', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, this option shows a "pay" button and modal credit card form on the checkout, instead of credit card fields directly on the page.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'stripe_checkout_locale' => array(
			'title'       => __( 'Stripe Checkout locale', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => __( 'Language to display in Stripe Checkout modal. Specify Auto to display Checkout in the user\'s preferred language, if available. English will be used by default.', 'woocommerce-gateway-stripe' ),
			'default'     => 'en',
			'desc_tip'    => true,
			'options'     => array(
				'auto' => __( 'Auto', 'woocommerce-gateway-stripe' ),
				'zh'   => __( 'Simplified Chinese', 'woocommerce-gateway-stripe' ),
				'nl'   => __( 'Dutch', 'woocommerce-gateway-stripe' ),
				'en'   => __( 'English', 'woocommerce-gateway-stripe' ),
				'fr'   => __( 'French', 'woocommerce-gateway-stripe' ),
				'de'   => __( 'German', 'woocommerce-gateway-stripe' ),
				'it'   => __( 'Italian', 'woocommerce-gateway-stripe' ),
				'ja'   => __( 'Japanese', 'woocommerce-gateway-stripe' ),
				'es'   => __( 'Spanish', 'woocommerce-gateway-stripe' ),
			),
		),
		'stripe_bitcoin' => array(
			'title'       => __( 'Bitcoin Currency', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Bitcoin Currency', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, an option to accept bitcoin will show on the checkout modal. Note: Stripe Checkout needs to be enabled and store currency must be set to USD.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'stripe_checkout_image' => array(
			'title'       => __( 'Stripe Checkout Image', 'woocommerce-gateway-stripe' ),
			'description' => __( 'Optionally enter the URL to a 128x128px image of your brand or product. e.g. <code>https://yoursite.com/wp-content/uploads/2013/09/yourimage.jpg</code>', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'default'     => '',
			'desc_tip'    => true,
		),
		'request_payment_api' => array(
			'title'       => __( 'RequestPayment API', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable RequestPayment API', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay using the RequestPayment API if avaiable in the browser.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'saved_cards' => array(
			'title'       => __( 'Saved Cards', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
