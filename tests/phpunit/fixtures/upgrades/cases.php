<?php

require_once __DIR__ . '/../../helpers/upgrade-changes/class-wc-stripe-upgrade-oc-ap-change.php';
require_once __DIR__ . '/../../helpers/upgrade-changes/class-wc-stripe-upgrade-link-locations-change.php';
require_once __DIR__ . '/../../helpers/upgrade-changes/class-wc-stripe-upgrade-webhook-change.php';
require_once __DIR__ . '/../../helpers/upgrade-changes/class-wc-stripe-upgrade-pmc-retry-change.php';
require_once __DIR__ . '/../../helpers/upgrade-changes/class-wc-stripe-upgrade-pmc-mirror-change.php';

// Pin the historical 11.0.1 US-account order without PMC. An incomplete saved
// order would expand on the first settings read, including on the old release.
$test_mode_order         = [
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
$test_mode_gateway_order = [ 'bacs' => '0' ];
foreach ( $test_mode_order as $method_id ) {
	$test_mode_gateway_order[ 'card' === $method_id ? 'stripe' : 'stripe_' . $method_id ] = (string) count( $test_mode_gateway_order );
}
$test_mode_gateway_order['cod'] = (string) count( $test_mode_gateway_order );

return [
	'10.7.0 opt-outs'                       => [
		'source'  => '10.7.0',
		'upgrade' => [
			'OC/AP rollout'       => WC_Stripe_Upgrade_OC_AP_Change::expected(),
			'Link locations'      => WC_Stripe_Upgrade_Link_Locations_Change::expected(),
			'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
		'opt_out' => [
			'settings' => [
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			],
			'visible'  => [
				'is_oc_enabled' => false,
				'is_ap_enabled' => false,
			],
		],
	],
	'10.9.1 opt-outs'                       => [
		'source'  => '10.9.1',
		'upgrade' => [ 'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected() ],
	],
	'11.0.1 opt-outs'                       => [
		'source'  => '11.0.1',
		'upgrade' => [ 'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected() ],
	],
	'11.0.1 empty selections'               => [
		'source'          => '11.0.1',
		'settings'        => [
			'upe_checkout_experience_accepted_payments' => [],
			'express_checkout_button_locations'         => [],
			'link_button_locations'                     => [],
			'express_checkout'                          => 'no',
		],
		'pmc_preferences' => [
			'card'       => 'off',
			'klarna'     => 'off',
			'link'       => 'off',
			'apple_pay'  => 'off',
			'google_pay' => 'off',
		],
		'visible'         => [
			'enabled_payment_method_ids'        => [],
			'express_checkout_button_locations' => [],
			'link_button_locations'             => [],
			'is_express_checkout_enabled'       => false,
		],
		'upgrade'         => [ 'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected() ],
	],
	'11.0.1 missing opt-in flags'           => [
		'source'         => '11.0.1',
		'unset_settings' => [ 'optimized_checkout_element', 'adaptive_pricing' ],
		'upgrade'        => [ 'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected() ],
	],
	'11.0.1 PMC becomes eligible'           => [
		'source'     => '11.0.1',
		'settings'   => [ 'pmc_enabled' => 'no' ],
		'upgrade'    => [
			'PMC eligibility retry' => WC_Stripe_Upgrade_PMC_Retry_Change::on_upgrade(),
			'webhook replacement'   => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
		'first_read' => [ 'PMC sync resumes' => WC_Stripe_Upgrade_PMC_Retry_Change::when_sync_resumes( 'live' ) ],
	],
	'11.0.1 PMC stays ineligible'           => [
		'source'        => '11.0.1',
		'settings'      => [ 'pmc_enabled' => 'no' ],
		'pmc_available' => false,
		'upgrade'       => [
			'PMC eligibility retry' => WC_Stripe_Upgrade_PMC_Retry_Change::on_upgrade(),
			'webhook replacement'   => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
		'first_read'    => [ 'PMC sync stays disabled' => WC_Stripe_Upgrade_PMC_Retry_Change::when_sync_stays_disabled() ],
	],
	'11.0.1 stale PMC mirror'               => [
		'source'   => '11.0.1',
		'settings' => [
			'upe_checkout_experience_accepted_payments' => [ 'card', 'klarna', 'link' ],
			'express_checkout'                          => 'no',
		],
		'upgrade'  => [
			'PMC mirror refresh'  => WC_Stripe_Upgrade_PMC_Mirror_Change::expected(),
			'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
	],
	'11.0.1 test mode opt-outs'             => [
		'source'   => '11.0.1',
		'settings' => [ 'testmode' => 'yes' ],
		'visible'  => [ 'is_test_mode_enabled' => true ],
		'upgrade'  => [ 'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected() ],
	],
	'11.0.1 test mode PMC becomes eligible' => [
		'source'     => '11.0.1',
		'settings'   => [
			'testmode'    => 'yes',
			'pmc_enabled' => 'no',
		],
		'visible'    => [ 'is_test_mode_enabled' => true ],
		'upgrade'    => [
			'PMC eligibility retry' => WC_Stripe_Upgrade_PMC_Retry_Change::on_upgrade(),
			'webhook replacement'   => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
		'first_read' => [ 'PMC sync resumes' => WC_Stripe_Upgrade_PMC_Retry_Change::when_sync_resumes( 'test' ) ],
	],
	'11.0.1 test mode PMC stays ineligible' => [
		'source'        => '11.0.1',
		'settings'      => [
			'testmode'                        => 'yes',
			'pmc_enabled'                     => 'no',
			'stripe_upe_payment_method_order' => $test_mode_order,
		],
		'options'       => [ 'woocommerce_gateway_order' => $test_mode_gateway_order ],
		'visible'       => [
			'is_test_mode_enabled'       => true,
			'ordered_payment_method_ids' => [
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
			],
		],
		'pmc_available' => false,
		'upgrade'       => [
			'PMC eligibility retry' => WC_Stripe_Upgrade_PMC_Retry_Change::on_upgrade(),
			'webhook replacement'   => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
		'first_read'    => [ 'PMC sync stays disabled' => WC_Stripe_Upgrade_PMC_Retry_Change::when_sync_stays_disabled() ],
	],
	'11.0.1 test mode stale PMC mirror'     => [
		'source'   => '11.0.1',
		'settings' => [
			'testmode'                                  => 'yes',
			'upe_checkout_experience_accepted_payments' => [ 'card', 'klarna', 'link' ],
			'express_checkout'                          => 'no',
		],
		'visible'  => [ 'is_test_mode_enabled' => true ],
		'upgrade'  => [
			'PMC mirror refresh'  => WC_Stripe_Upgrade_PMC_Mirror_Change::expected(),
			'webhook replacement' => WC_Stripe_Upgrade_Webhook_Change::expected(),
		],
	],
];
