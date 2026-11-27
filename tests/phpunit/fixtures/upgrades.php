<?php
/**
 * Historical stores checked against tags 10.7.0, 10.9.1 and 11.0.1: settings,
 * account/PMC classes and express checkout helpers. Shared literals were the same
 * in those releases; none are read from current production defaults.
 *
 * Expected arrays below are the reviewed exceptions to preservation. Keep the
 * HTTP payloads exact. Credentials are inert; only the webhook URL is substituted.
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

$options                                = [
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
$options['woocommerce_stripe_settings'] = $settings;

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

// All three releases registered these 23 events.
$events = [
	'account.updated',
	'source.chargeable',
	'source.canceled',
	'charge.succeeded',
	'charge.failed',
	'charge.captured',
	'charge.dispute.created',
	'charge.dispute.closed',
	'charge.refunded',
	'charge.refund.updated',
	'review.opened',
	'review.closed',
	'payment_intent.processing',
	'payment_intent.succeeded',
	'payment_intent.payment_failed',
	'payment_intent.amount_capturable_updated',
	'payment_intent.requires_action',
	'setup_intent.succeeded',
	'setup_intent.setup_failed',
	'checkout.session.completed',
	'checkout.session.expired',
	'checkout.session.async_payment_succeeded',
	'checkout.session.async_payment_failed',
];

// The added charge.expired event requires new endpoints and signing secrets.
$updated_events = $events;
array_splice( $updated_events, 5, 0, [ 'charge.expired' ] );
$webhook_settings = [];
$writes           = [];
foreach ( [ 'live', 'test' ] as $stripe_mode ) {
	$prefix                                       = 'live' === $stripe_mode ? '' : 'test_';
	$webhook_settings[ $prefix . 'webhook_data' ] = [
		'id'             => 'we_' . $stripe_mode . '_new',
		'url'            => '{{webhook_url}}',
		'signing_secret' => 'whsec_' . $stripe_mode . '_new_fixture',
	];
	$webhook_settings[ $prefix . 'webhook_secret' ] = 'whsec_' . $stripe_mode . '_new_fixture';
	$writes[]                                       = [
		'method' => 'POST',
		'path'   => '/v1/webhook_endpoints',
		'mode'   => $stripe_mode,
		'body'   => [
			'enabled_events' => $updated_events,
			'url'            => '{{webhook_url}}',
			'api_version'    => '2026-03-25.dahlia',
			'metadata'       => [ 'created_by' => 'woocommerce_gateway_stripe' ],
		],
	];
	$writes[]                                       = [
		'method' => 'DELETE',
		'path'   => '/v1/webhook_endpoints/we_' . $stripe_mode . '_old',
		'mode'   => $stripe_mode,
		'body'   => [],
	];
}
$common               = [
	'pmc_preferences'     => [
		'card'       => 'on',
		'klarna'     => 'off',
		'link'       => 'off',
		'apple_pay'  => 'on',
		'google_pay' => 'on',
	],
	'webhook_events'      => $events,
	'webhook_api_version' => '2026-03-25.dahlia',
	'writes'              => $writes,
];
$completed_migrations = [
	'wc_stripe_ocs_ap_default_on_migration_ran'                 => 'yes',
	'wc_stripe_express_checkout_cpm_location_migrated'          => 'yes',
	'wc_stripe_restored_adaptive_pricing_after_amount_mismatch' => 'yes',
];

$fixtures    = [];
$old_options = $options + [ 'wc_stripe_version' => '10.7.0' ];
unset( $old_options['woocommerce_stripe_settings']['link_button_locations'] );
// 10.8 intentionally enabled OC/AP; 10.9 gave Link its own copy of the locations.
$old_after                             = array_replace(
	$settings,
	$webhook_settings,
	[
		'optimized_checkout_element' => 'yes',
		'adaptive_pricing'           => 'yes',
	]
);
$fixtures['10.7.0 historical rollout'] = [
	array_replace(
		$common,
		[
			'options'             => $old_options,
			'after_install'       => $old_after,
			'after_read'          => $old_after,
			'visible'             => array_replace(
				$visible,
				[
					'is_oc_enabled' => true,
					'is_ap_enabled' => true,
				]
			),
			'webhook_api_version' => '2025-09-30.clover',
		]
	),
];

$empty_options                                = $options + $completed_migrations + [ 'wc_stripe_version' => '10.9.1' ];
$empty_options['woocommerce_stripe_settings'] = array_replace(
	$settings,
	[
		'testmode'                                  => 'yes',
		'upe_checkout_experience_accepted_payments' => [],
		'express_checkout'                          => 'no',
		'express_checkout_button_locations'         => [],
		'link_button_locations'                     => [],
	]
);
unset( $empty_options['woocommerce_stripe_settings']['optimized_checkout_element'], $empty_options['woocommerce_stripe_settings']['adaptive_pricing'] );
$empty_after                                      = array_replace( $empty_options['woocommerce_stripe_settings'], $webhook_settings );
$fixtures['10.9.1 empty and missing preferences'] = [
	array_replace(
		$common,
		[
			'options'         => $empty_options,
			'after_install'   => $empty_after,
			'after_read'      => $empty_after,
			'visible'         => array_replace(
				$visible,
				[
					'is_test_mode_enabled'              => true,
					'enabled_payment_method_ids'        => [],
					'is_express_checkout_enabled'       => false,
					'express_checkout_button_locations' => [],
					'link_button_locations'             => [],
				]
			),
			'pmc_preferences' => [
				'card'       => 'off',
				'klarna'     => 'off',
				'link'       => 'off',
				'apple_pay'  => 'off',
				'google_pay' => 'off',
			],
		]
	),
];

$pmc_options = $options + $completed_migrations + [ 'wc_stripe_version' => '11.0.1' ];
$pmc_options['woocommerce_stripe_settings']['pmc_enabled'] = 'no';
// install() retries eligibility; the first settings read completes synchronization.
// Local and remote choices agree, so the PMC write must preserve every preference.
$pmc_install = array_replace( $settings, $webhook_settings, [ 'skip_pmc_express_checkout_defaults' => 'yes' ] );
unset( $pmc_install['pmc_enabled'] );
$fixtures['11.0.1 PMC retry preserves opt-outs'] = [
	array_replace(
		$common,
		[
			'options'       => $pmc_options,
			'after_install' => $pmc_install,
			'after_read'    => $pmc_install + [ 'pmc_enabled' => 'yes' ],
			'visible'       => $visible,
			'writes'        => array_merge(
				$writes,
				[
					[
						'method' => 'POST',
						'path'   => '/v1/payment_method_configurations/pmc_live_fixture',
						'mode'   => 'live',
						'body'   => [
							'card'       => [ 'display_preference' => [ 'preference' => 'on' ] ],
							'klarna'     => [ 'display_preference' => [ 'preference' => 'off' ] ],
							'link'       => [ 'display_preference' => [ 'preference' => 'off' ] ],
							'apple_pay'  => [ 'display_preference' => [ 'preference' => 'on' ] ],
							'google_pay' => [ 'display_preference' => [ 'preference' => 'on' ] ],
						],
					],
				]
			),
		]
	),
];

return $fixtures;
