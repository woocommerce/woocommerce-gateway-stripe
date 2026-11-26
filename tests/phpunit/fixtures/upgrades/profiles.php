<?php
/**
 * Hand-built stored states, checked against tags 10.7.0, 10.9.1 and 11.0.1.
 *
 * Sources: includes/admin/stripe-settings.php, class-wc-stripe-account.php,
 * class-wc-stripe-payment-method-configurations.php and the express checkout helper
 * at each tag. These are deliberately not generated from the current defaults.
 * All credentials are inert test values. The URL token is resolved by the harness.
 */

$settings = [
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
	'upe_checkout_experience_accepted_payments' => [ 'card' ],
	'stripe_upe_payment_method_order'           => [ 'card', 'klarna', 'link' ],
	'pmc_enabled'                               => 'yes',
	'optimized_checkout_element'                => 'no',
	'adaptive_pricing'                          => 'no',
	'capture'                                   => 'yes',
	'saved_cards'                               => 'no',
	'sepa_tokens_for_ideal'                     => 'no',
	'sepa_tokens_for_bancontact'                => 'no',
	'express_checkout'                          => 'yes',
	'express_checkout_button_locations'         => [ 'cart' ],
	'link_button_locations'                     => [ 'cart' ],
	'amazon_pay_button_locations'               => [],
	'express_checkout_button_type'              => 'buy',
	'express_checkout_button_theme'             => 'light',
	'express_checkout_button_size'              => 'large',
	'logging'                                   => 'no',
];

$visible = [
	'is_stripe_enabled'                 => true,
	'is_test_mode_enabled'              => false,
	'enabled_payment_method_ids'        => [ 'card' ],
	'ordered_payment_method_ids'        => [ 'card', 'klarna' ],
	'is_oc_enabled'                     => false,
	'is_ap_enabled'                     => false,
	'is_manual_capture_enabled'         => false,
	'is_saved_cards_enabled'            => false,
	'is_express_checkout_enabled'       => true,
	'express_checkout_button_locations' => [ 'cart' ],
	'link_button_locations'             => [ 'cart' ],
	'amazon_pay_button_locations'       => [],
	'express_checkout_button_type'      => 'buy',
	'express_checkout_button_theme'     => 'light',
	'express_checkout_button_size'      => 'large',
];

$profiles = [];
foreach ( [ '10.7.0', '10.9.1', '11.0.1' ] as $version ) {
	$options = [
		'wc_stripe_version'                                     => $version,
		'woocommerce_stripe_settings'                           => $settings,
		'woocommerce_gateway_order'                             => [
			'bacs'          => '0',
			'stripe'        => '1',
			'stripe_klarna' => '2',
			'stripe_link'   => '3',
			'cod'           => '4',
		],
		'woocommerce_stripe_klarna_settings'                    => [
			'enabled' => 'no',
			'title'   => 'Merchant Klarna title',
		],
		'woocommerce_wc_stripe_failed_refund_admin_settings'    => [
			'enabled'            => 'no',
			'additional_content' => 'Contact the store owner before retrying this refund.',
		],
		'woocommerce_wc_stripe_failed_refund_customer_settings' => [
			'enabled'            => 'yes',
			'additional_content' => 'Our support team will contact you about your refund.',
		],
	];
	if ( '10.7.0' === $version ) {
		unset( $options['woocommerce_stripe_settings']['link_button_locations'] );
	} else {
		$options['wc_stripe_ocs_ap_default_on_migration_ran']                 = 'yes';
		$options['wc_stripe_express_checkout_cpm_location_migrated']          = 'yes';
		$options['wc_stripe_restored_adaptive_pricing_after_amount_mismatch'] = 'yes';
	}
	$profiles[ $version . ' opt-outs' ] = [
		'options'             => $options,
		'visible'             => $visible,
		'pmc_available'       => true,
		'pmc_preferences'     => [
			'card'       => 'on',
			'klarna'     => 'off',
			'link'       => 'off',
			'apple_pay'  => 'on',
			'google_pay' => 'on',
		],
		'webhook_api_version' => '10.7.0' === $version ? '2025-09-30.clover' : '2026-03-25.dahlia',
	];
}

$empty = $profiles['11.0.1 opt-outs'];
foreach ( [ 'upe_checkout_experience_accepted_payments', 'express_checkout_button_locations', 'link_button_locations' ] as $key ) {
	$empty['options']['woocommerce_stripe_settings'][ $key ] = [];
}
$empty['options']['woocommerce_stripe_settings']['express_checkout'] = 'no';
$empty['pmc_preferences']                              = array_fill_keys( array_keys( $empty['pmc_preferences'] ), 'off' );
$empty['visible']['enabled_payment_method_ids']        = [];
$empty['visible']['express_checkout_button_locations'] = [];
$empty['visible']['link_button_locations']             = [];
$empty['visible']['is_express_checkout_enabled']       = false;
$profiles['11.0.1 empty selections']                   = $empty;

$missing = $profiles['11.0.1 opt-outs'];
// Missing opt-in flags must not silently acquire enabled defaults during an upgrade.
unset( $missing['options']['woocommerce_stripe_settings']['optimized_checkout_element'], $missing['options']['woocommerce_stripe_settings']['adaptive_pricing'] );
$profiles['11.0.1 missing opt-in flags'] = $missing;

$disabled = $profiles['11.0.1 opt-outs'];
$disabled['options']['woocommerce_stripe_settings']['pmc_enabled'] = 'no';
$profiles['11.0.1 PMC becomes eligible']                           = $disabled;
$disabled['pmc_available']               = false;
$profiles['11.0.1 PMC stays ineligible'] = $disabled;

$stale = $profiles['11.0.1 opt-outs'];
// With PMC already active, these local values are only a stale fallback mirror.
$stale['options']['woocommerce_stripe_settings']['upe_checkout_experience_accepted_payments'] = [ 'card', 'klarna', 'link' ];
$stale['options']['woocommerce_stripe_settings']['express_checkout']                          = 'no';
$stale['stale_pmc_mirror']           = true;
$profiles['11.0.1 stale PMC mirror'] = $stale;

foreach ( [ 'opt-outs', 'PMC becomes eligible', 'PMC stays ineligible', 'stale PMC mirror' ] as $scenario ) {
	$profile = $profiles[ '11.0.1 ' . $scenario ];
	$profile['options']['woocommerce_stripe_settings']['testmode'] = 'yes';
	$profile['visible']['is_test_mode_enabled']                    = true;
	if ( 'PMC stays ineligible' === $scenario ) {
		// Without PMC, the historical test-mode UI lists all US-account methods.
		// A saved order must include those methods to avoid first-read expansion.
		$profile['options']['woocommerce_stripe_settings']['stripe_upe_payment_method_order'] = [
			'card',
			'klarna',
			'link',
			'us_bank_account',
			'alipay',
			'amazon_pay',
			'blik',
			'affirm',
			'afterpay_clearpay',
			'eps',
			'bancontact',
			'ideal',
			'sepa_debit',
			'p24',
			'multibanco',
			'wechat_pay',
			'cashapp',
			'acss_debit',
		];
		$gateway_order = [ 'bacs' => '0' ];
		foreach ( $profile['options']['woocommerce_stripe_settings']['stripe_upe_payment_method_order'] as $method_id ) {
			$gateway_order[ 'card' === $method_id ? 'stripe' : 'stripe_' . $method_id ] = (string) count( $gateway_order );
		}
		$gateway_order['cod']                             = (string) count( $gateway_order );
		$profile['options']['woocommerce_gateway_order']  = $gateway_order;
		$profile['visible']['ordered_payment_method_ids'] = [
			'card',
			'klarna',
			'us_bank_account',
			'alipay',
			'blik',
			'affirm',
			'afterpay_clearpay',
			'eps',
			'bancontact',
			'ideal',
			'sepa_debit',
			'p24',
			'multibanco',
			'wechat_pay',
			'cashapp',
			'acss_debit',
		];
	}
	$profiles[ '11.0.1 test mode ' . $scenario ] = $profile;
}

return $profiles;
