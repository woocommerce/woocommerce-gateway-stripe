<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stripe_settings = apply_filters(
	'wc_stripe_settings',
	[
		'enabled'                             => [
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Stripe', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'                               => [
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Credit Card (Stripe)', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'title_upe'                           => [
			'title'       => __( 'Title', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout when multiple payment methods are enabled.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Popular payment methods', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'description'                         => [
			'title'       => __( 'Description', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Pay with your credit card via Stripe.', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'api_credentials'                     => [
			'title' => __( 'Stripe Account Keys', 'woocommerce-gateway-stripe' ),
			'type'  => 'stripe_account_keys',
		],
		'testmode'                            => [
			'title'       => __( 'Test mode', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'test_publishable_key'                => [
			'title'       => __( 'Test Publishable Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "pk_test_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'test_secret_key'                     => [
			'title'       => __( 'Test Secret Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "sk_test_" or "rk_test_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'publishable_key'                     => [
			'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "pk_live_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                          => [
			'title'       => __( 'Live Secret Key', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your stripe account. Invalid values will be rejected. Only values starting with "sk_live_" or "rk_live_" will be saved.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'webhook'                             => [
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			'description' => $this->display_admin_settings_webhook_description(),
		],
		'test_webhook_secret'                 => [
			'title'       => __( 'Test Webhook Secret', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your webhook signing secret from the webhooks section in your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'webhook_secret'                      => [
			'title'       => __( 'Webhook Secret', 'woocommerce-gateway-stripe' ),
			'type'        => 'password',
			'description' => __( 'Get your webhook signing secret from the webhooks section in your stripe account.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'inline_cc_form'                      => [
			'title'       => __( 'Inline Credit Card Form', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Choose the style you want to show for your credit card form. When unchecked, the credit card form will display separate credit card number field, expiry date field and cvc field.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
		'statement_descriptor'                => [
			'title'       => __( 'Statement Descriptor', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Statement descriptors are limited to 22 characters, cannot use the special characters >, <, ", \, \', *, /, (, ), {, }, and must not consist solely of numbers. This will appear on your customer\'s statement in capital letters.', 'woocommerce-gateway-stripe' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'capture'                             => [
			'title'       => __( 'Capture', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'payment_request'                     => [
			'title'       => __( 'Payment Request Buttons', 'woocommerce-gateway-stripe' ),
			'label'       => sprintf(
				/* translators: 1) br tag 2) Stripe anchor tag 3) Apple anchor tag 4) Stripe dashboard opening anchor tag 5) Stripe dashboard closing anchor tag */
				__( 'Enable Payment Request Buttons. (Apple Pay/Google Pay) %1$sBy using Apple Pay, you agree to %2$s and %3$s\'s terms of service. (Apple Pay domain verification is performed automatically in live mode; configuration can be found on the %4$sStripe dashboard%5$s.)', 'woocommerce-gateway-stripe' ),
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
		],
		'payment_request_button_type'         => [
			'title'       => __( 'Payment Request Button Type', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Type', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the button type you would like to show.', 'woocommerce-gateway-stripe' ),
			'default'     => 'buy',
			'desc_tip'    => true,
			'options'     => [
				'default' => __( 'Default', 'woocommerce-gateway-stripe' ),
				'buy'     => __( 'Buy', 'woocommerce-gateway-stripe' ),
				'donate'  => __( 'Donate', 'woocommerce-gateway-stripe' ),
				'branded' => __( 'Branded', 'woocommerce-gateway-stripe' ),
				'custom'  => __( 'Custom', 'woocommerce-gateway-stripe' ),
			],
		],
		'payment_request_button_theme'        => [
			'title'       => __( 'Payment Request Button Theme', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Theme', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the button theme you would like to show.', 'woocommerce-gateway-stripe' ),
			'default'     => 'dark',
			'desc_tip'    => true,
			'options'     => [
				'dark'          => __( 'Dark', 'woocommerce-gateway-stripe' ),
				'light'         => __( 'Light', 'woocommerce-gateway-stripe' ),
				'light-outline' => __( 'Light-Outline', 'woocommerce-gateway-stripe' ),
			],
		],
		'payment_request_button_height'       => [
			'title'       => __( 'Payment Request Button Height', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Height', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-gateway-stripe' ),
			'default'     => '44',
			'desc_tip'    => true,
		],
		'payment_request_button_label'        => [
			'title'       => __( 'Payment Request Button Label', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Button Label', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-gateway-stripe' ),
			'default'     => __( 'Buy now', 'woocommerce-gateway-stripe' ),
			'desc_tip'    => true,
		],
		'payment_request_button_branded_type' => [
			'title'       => __( 'Payment Request Branded Button Label Format', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Branded Button Label Format', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the branded button label format.', 'woocommerce-gateway-stripe' ),
			'default'     => 'long',
			'desc_tip'    => true,
			'options'     => [
				'short' => __( 'Logo only', 'woocommerce-gateway-stripe' ),
				'long'  => __( 'Text and logo', 'woocommerce-gateway-stripe' ),
			],
		],
		'payment_request_button_locations'    => [
			'title'             => __( 'Payment Request Button Locations', 'woocommerce-gateway-stripe' ),
			'type'              => 'multiselect',
			'description'       => __( 'Select where you would like Payment Request Buttons to be displayed', 'woocommerce-gateway-stripe' ),
			'desc_tip'          => true,
			'class'             => 'wc-enhanced-select',
			'options'           => [
				'product'  => __( 'Product', 'woocommerce-gateway-stripe' ),
				'cart'     => __( 'Cart', 'woocommerce-gateway-stripe' ),
				'checkout' => __( 'Checkout', 'woocommerce-gateway-stripe' ),
			],
			'default'           => [ 'product', 'cart' ],
			'custom_attributes' => [
				'data-placeholder' => __( 'Select pages', 'woocommerce-gateway-stripe' ),
			],
		],
		'payment_request_button_size'         => [
			'title'       => __( 'Payment Request Button Size', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the size of the button.', 'woocommerce-gateway-stripe' ),
			'default'     => 'default',
			'desc_tip'    => true,
			'options'     => [
				'default' => __( 'Default (40px)', 'woocommerce-gateway-stripe' ),
				'medium'  => __( 'Medium (48px)', 'woocommerce-gateway-stripe' ),
				'large'   => __( 'Large (56px)', 'woocommerce-gateway-stripe' ),
			],
		],
		'saved_cards'                         => [
			'title'       => __( 'Saved Cards', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.', 'woocommerce-gateway-stripe' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'logging'                             => [
			'title'       => __( 'Logging', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-stripe' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	]
);

if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
	// in the new settings, "checkout" is going to be enabled by default (if it is a new WCStripe installation).
	$stripe_settings['payment_request_button_locations']['default'][] = 'checkout';

	// no longer needed in the new settings.
	unset( $stripe_settings['payment_request_button_branded_type'] );
	unset( $stripe_settings['payment_request_button_height'] );
	unset( $stripe_settings['payment_request_button_label'] );
	// injecting some of the new options.
	$stripe_settings['payment_request_button_type']['options']['default'] = __( 'Only icon', 'woocommerce-gateway-stripe' );
	$stripe_settings['payment_request_button_type']['options']['book']    = __( 'Book', 'woocommerce-gateway-stripe' );
	// no longer valid options.
	unset( $stripe_settings['payment_request_button_type']['options']['branded'] );
	unset( $stripe_settings['payment_request_button_type']['options']['custom'] );
} else {
	unset( $stripe_settings['payment_request_button_size'] );
}

if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
	$upe_settings = [
		WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => [
			'title'       => __( 'New checkout experience', 'woocommerce-gateway-stripe' ),
			'label'       => sprintf(
				/* translators: 1) br tag 2) Stripe anchor tag 3) Apple anchor tag 4) Stripe dashboard opening anchor tag 5) Stripe dashboard closing anchor tag */
				__( 'Try the new payment experience (Early access) %1$sGet early access to a new, smarter payment experience on checkout and let us know what you think by %2$s. We recommend this feature for experienced merchants as the functionality is currently limited. %3$s', 'woocommerce-gateway-stripe' ),
				'<br />',
				'<a href="https://woocommerce.survey.fm/woocommerce-stripe-upe-opt-out-survey" target="_blank">submitting your feedback</a>',
				'<a href="https://woocommerce.com/document/stripe/#new-checkout-experience" target="_blank">Learn more</a>'
			),
			'type'        => 'checkbox',
			'description' => __( 'New checkout experience allows you to manage all payment methods on one screen and display them to customers based on their currency and location.', 'woocommerce-gateway-stripe' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	];
	if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
		// This adds the payment method section
		$upe_settings['upe_checkout_experience_accepted_payments'] = [
			'title'   => __( 'Payments accepted on checkout (Early access)', 'woocommerce-gateway-stripe' ),
			'type'    => 'upe_checkout_experience_accepted_payments',
			'default' => [ 'card' ],
		];
	}
	// Insert UPE options below the 'logging' setting.
	$stripe_settings = array_merge( $stripe_settings, $upe_settings );
}

return apply_filters(
	'wc_stripe_settings',
	$stripe_settings
);
