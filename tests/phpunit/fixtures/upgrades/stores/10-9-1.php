<?php
/**
 * Hand-built store state checked against tag 10.9.1.
 *
 * Sources: stripe-settings.php, the account and PMC classes, and the express
 * checkout helper at that tag. Keep these values independent of current defaults
 * and other release fixtures. Credentials are inert; only the URL is substituted.
 */

return [
	'options'             => [
		'wc_stripe_version'                                         => '10.9.1',
		'woocommerce_stripe_settings'                               => [
			'enabled'                                   => 'yes',
			'testmode'                                  => 'no',
			'publishable_key'                           => 'pk_live_upgrade_fixture',
			'secret_key'                                => 'sk_live_upgrade_fixture',
			'connection_type'                           => 'connect',
			'test_publishable_key'                      => 'pk_test_upgrade_fixture',
			'test_secret_key'                           => 'sk_test_upgrade_fixture',
			'test_connection_type'                      => 'connect',
			'webhook_secret'                            => 'whsec_live_old_fixture',
			'test_webhook_secret'                       => 'whsec_test_old_fixture',
			'webhook_data'                              => [
				'id'     => 'we_live_old',
				'url'    => '{{webhook_url}}',
				'secret' => 'sk_live_upgrade_fixture',
			],
			'test_webhook_data'                         => [
				'id'     => 'we_test_old',
				'url'    => '{{webhook_url}}',
				'secret' => 'sk_test_upgrade_fixture',
			],
			'upe_checkout_experience_enabled'           => 'yes',
			'upe_checkout_experience_accepted_payments' => [
				'card',
			],
			'stripe_upe_payment_method_order'           => [
				'card',
				'klarna',
				'link',
			],
			'pmc_enabled'                               => 'yes',
			'optimized_checkout_element'                => 'no',
			'adaptive_pricing'                          => 'no',
			'capture'                                   => 'yes',
			'saved_cards'                               => 'no',
			'sepa_tokens_for_ideal'                     => 'no',
			'sepa_tokens_for_bancontact'                => 'no',
			'express_checkout'                          => 'yes',
			'express_checkout_button_locations'         => [
				'cart',
			],
			'link_button_locations'                     => [
				'cart',
			],
			'amazon_pay_button_locations'               => [],
			'express_checkout_button_type'              => 'buy',
			'express_checkout_button_theme'             => 'light',
			'express_checkout_button_size'              => 'large',
			'logging'                                   => 'no',
		],
		'woocommerce_gateway_order'                                 => [
			'bacs'          => '0',
			'stripe'        => '1',
			'stripe_klarna' => '2',
			'stripe_link'   => '3',
			'cod'           => '4',
		],
		'woocommerce_stripe_klarna_settings'                        => [
			'enabled' => 'no',
			'title'   => 'Merchant Klarna title',
		],
		'woocommerce_wc_stripe_failed_refund_admin_settings'        => [
			'enabled'            => 'no',
			'additional_content' => 'Contact the store owner before retrying this refund.',
		],
		'woocommerce_wc_stripe_failed_refund_customer_settings'     => [
			'enabled'            => 'yes',
			'additional_content' => 'Our support team will contact you about your refund.',
		],
		'wc_stripe_ocs_ap_default_on_migration_ran'                 => 'yes',
		'wc_stripe_express_checkout_cpm_location_migrated'          => 'yes',
		'wc_stripe_restored_adaptive_pricing_after_amount_mismatch' => 'yes',
	],
	'visible'             => [
		'is_stripe_enabled'                 => true,
		'is_test_mode_enabled'              => false,
		'enabled_payment_method_ids'        => [
			'card',
		],
		'ordered_payment_method_ids'        => [
			'card',
			'klarna',
		],
		'is_oc_enabled'                     => false,
		'is_ap_enabled'                     => false,
		'is_manual_capture_enabled'         => false,
		'is_saved_cards_enabled'            => false,
		'is_express_checkout_enabled'       => true,
		'express_checkout_button_locations' => [
			'cart',
		],
		'link_button_locations'             => [
			'cart',
		],
		'amazon_pay_button_locations'       => [],
		'express_checkout_button_type'      => 'buy',
		'express_checkout_button_theme'     => 'light',
		'express_checkout_button_size'      => 'large',
	],
	'pmc_available'       => true,
	'pmc_preferences'     => [
		'card'       => 'on',
		'klarna'     => 'off',
		'link'       => 'off',
		'apple_pay'  => 'on',
		'google_pay' => 'on',
	],
	'webhook_api_version' => '2026-03-25.dahlia',
];
