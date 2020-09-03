<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( woocommerce_gateway_stripe()->connect->is_connected() ) {
	$reset_link = add_query_arg(
		array(
			'_wpnonce'                     => wp_create_nonce( 'reset_stripe_api_credentials' ),
			'reset_stripe_api_credentials' => true,
		),
		admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' )
	);

	$api_credentials_text = sprintf(
		__( '%1$sClear all Stripe account keys.%2$s %3$sThis will disable any connection to Stripe.%4$s', 'woocommerce-gateway-stripe' ),
		'<a id="wc_stripe_connect_button" href="' . $reset_link . '" class="button button-secondary">',
		'</a>',
		'<span style="color:red;">',
		'</span>'
	);
} else {
	$oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url();

	if ( ! is_wp_error( $oauth_url ) ) {
		$api_credentials_text = sprintf(
			__( '%1$sSetup or link an existing Stripe account.%2$s By clicking this button you agree to the %3$sTerms of Service%2$s. Or, manually enter Stripe account keys below.', 'woocommerce-gateway-stripe' ),
			'<a id="wc_stripe_connect_button" href="' . $oauth_url . '" class="button button-primary">',
			'</a>',
			'<a href="https://wordpress.com/tos">'

		);
	} else {
		$api_credentials_text = __( 'Manually enter Stripe keys below.', 'woocommerce-gateway-stripe' );
	}
}

return apply_filters(
	'wc_stripe_settings',
	array(
		'enabled'                       => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'                         => array(
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Credit Card (Stripe)', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'description'                   => array(
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Pay with your credit card via Stripe.', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'webhook'                       => array(
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			/* translators: webhook URL */
			'description' => $this->display_admin_settings_webhook_description(),
		),
		'api_credentials'               => array(
			'title'       => __( 'Stripe Account Keys', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			'description' => $api_credentials_text
		),
		'testmode'                      => array(
			'title'       => __( 'Test mode', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_publishable_key'          => array(
			'title'       => __( 'Test Publishable Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "pk_test_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_secret_key'               => array(
			'title'       => __( 'Test Secret Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "sk_test_" or "rk_test_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_webhook_secret'           => array(
			'title'       => __( 'Test Webhook Secret', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your webhook signing secret from the webhooks section in your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'publishable_key'               => array(
			'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "pk_live_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'secret_key'                    => array(
			'title'       => __( 'Live Secret Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "sk_live_" or "rk_live_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'webhook_secret'               => array(
			'title'       => __( 'Webhook Secret', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your webhook signing secret from the webhooks section in your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'inline_cc_form'                => array(
			'title'       => __( 'Inline Credit Card Form', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Choose the style you want to show for your credit card form. When unchecked, the credit card form will display separate credit card number field, expiry date field and cvc field.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'statement_descriptor'          => array(
			'title'       => __( 'Statement Descriptor', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Statement descriptors are limited to 22 characters, cannot use the special characters >, <, ", \, \', *, /, (, ), {, }, and must not consist solely of numbers. This will appear on your customer\'s statement in capital letters.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'capture'                       => array(
			'title'       => __( 'Capture', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'payment_request'               => array(
			'title'       => __( 'Payment Request Buttons', 'woocommerce-gateway-stripe' ),
			'label'       => sprintf(
				/* translators: 1) br tag 2) Stripe anchor tag 3) Apple anchor tag 4) Stripe dashboard opening anchor tag 5) Stripe dashboard closing anchor tag */
				__( 'Enable Payment Request Buttons. (Apple Pay/Google Pay) %1$sBy using Apple Pay, you agree to %2$s and %3$s\'s terms of service. (Apple Pay domain verification is performed automatically; configuration can be found on the %4$sStripe dashboard%5$s.)', 'woocommerce-gateway-stripe' ),
				'<br />',
				'<a href="https://stripe.com/apple-pay/legal" target="_blank">Stripe</a>',
				'<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>',
				'<a href="https://dashboard.stripe.com/settings/payments/apple_pay" target="_blank">',
				'</a>'
			),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay using Apple Pay or Chrome Payment Request if supported by the browser.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'payment_request_button_type'   => array(
			'title'       => __( 'Payment Request Button Type', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Type', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the button type you would like to show.', 'woocommerce-gateway-stripe' ),
			'default'     => 'buy',
			'desc_tip'    => true,
			'options'     => array(
				'default' => __( 'Default', 'woocommerce-gateway-stripe' ),
				'buy'     => __( 'Buy', 'woocommerce-gateway-stripe' ),
				'donate'  => __( 'Donate', 'woocommerce-gateway-stripe' ),
				'branded' => __( 'Branded', 'woocommerce-gateway-stripe' ),
				'custom'  => __( 'Custom', 'woocommerce-gateway-stripe' ),
			),
		),
		'payment_request_button_theme'  => array(
			'title'       => __( 'Payment Request Button Theme', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Theme', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the button theme you would like to show.', 'woocommerce-gateway-stripe' ),
			'default'     => 'dark',
			'desc_tip'    => true,
			'options'     => array(
				'dark'          => __( 'Dark', 'woocommerce-gateway-stripe' ),
				'light'         => __( 'Light', 'woocommerce-gateway-stripe' ),
				'light-outline' => __( 'Light-Outline', 'woocommerce-gateway-stripe' ),
			),
		),
		'payment_request_button_height' => array(
			'title'       => __( 'Payment Request Button Height', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Height', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-gateway-stripe' ),
			'default'     => '44',
			'desc_tip'    => true,
		),
		'payment_request_button_label' => array(
			'title'       => __( 'Payment Request Button Label', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Label', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Buy now', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		),
		'payment_request_button_branded_type' => array(
			'title'       => __( 'Payment Request Branded Button Label Format', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Branded Button Label Format', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the branded button label format.', 'woocommerce-gateway-stripe' ),
			'default'     => 'long',
			'desc_tip'    => true,
			'options'     => array(
				'short' => __( 'Logo only', 'woocommerce-gateway-stripe' ),
				'long'  => __( 'Text and logo', 'woocommerce-gateway-stripe' ),
			),
		),
		'saved_cards'                   => array(
			'title'       => __( 'Saved Cards', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'logging'                       => array(
			'title'       => __( 'Logging', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
