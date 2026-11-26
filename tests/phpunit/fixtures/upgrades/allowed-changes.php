<?php
/**
 * Exact upgrade exceptions. Keep historical inputs and expected outputs independent
 * of production defaults; a change here is a reviewable change to the contract.
 *
 * $fixture and $stage are supplied by the test. null in a storage transition means
 * the key must be absent, not a wildcard. Each transition must actually occur.
 */

$changes  = [
	'settings' => [],
	'visible'  => [],
	'writes'   => [],
];
$settings = $fixture['options']['woocommerce_stripe_settings'];
$version  = $fixture['options']['wc_stripe_version'];

if ( 'upgrade' === $stage ) {
	if ( ! empty( $fixture['stale_pmc_mirror'] ) ) {
		// An active PMC is authoritative; refreshing its fallback mirror must not
		// change effective selections or write preferences back to Stripe.
		$changes['settings']['upe_checkout_experience_accepted_payments'] = [ [ 'card', 'klarna', 'link' ], [ 'card' ] ];
		$changes['settings']['express_checkout']                          = [ 'no', 'yes' ];
	}
	// The 10.8 back-book rollout is an intentional historical default change.
	if ( '10.7.0' === $version ) {
		$changes['settings']['optimized_checkout_element'] = [ 'no', 'yes' ];
		$changes['settings']['adaptive_pricing']           = [ 'no', 'yes' ];
		$changes['visible']                                = [
			'is_oc_enabled' => true,
			'is_ap_enabled' => true,
		];
		// Link inherited these locations in 10.7, before its dedicated setting existed.
		$changes['settings']['link_button_locations'] = [ null, [ 'cart' ] ];
	}
	// Eligibility is rechecked after upgrades; this flag prevents wallet defaults
	// from overwriting the existing selections when synchronization resumes.
	if ( 'no' === $settings['pmc_enabled'] ) {
		$changes['settings']['pmc_enabled']                        = [ 'no', null ];
		$changes['settings']['skip_pmc_express_checkout_defaults'] = [ null, 'yes' ];
	}

	// These releases registered 23 events. The new charge.expired subscription
	// requires replacement endpoints and signing secrets in both modes.
	$events = json_decode( file_get_contents( __DIR__ . '/webhook-events.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local, immutable fixture.
	array_splice( $events, 5, 0, [ 'charge.expired' ] );
	foreach ( [ 'live', 'test' ] as $stripe_mode ) {
		$prefix = 'live' === $stripe_mode ? '' : 'test_';
		$changes['settings'][ $prefix . 'webhook_data' ]   = [
			$settings[ $prefix . 'webhook_data' ],
			[
				'id'             => 'we_' . $stripe_mode . '_new',
				'url'            => WC_Stripe_Helper::get_webhook_url(),
				'signing_secret' => 'whsec_' . $stripe_mode . '_new_fixture',
			],
		];
		$changes['settings'][ $prefix . 'webhook_secret' ] = [ $settings[ $prefix . 'webhook_secret' ], 'whsec_' . $stripe_mode . '_new_fixture' ];
		$changes['writes'][]                               = [
			'method' => 'POST',
			'path'   => '/v1/webhook_endpoints',
			'mode'   => $stripe_mode,
			'body'   => [
				'enabled_events' => $events,
				'url'            => WC_Stripe_Helper::get_webhook_url(),
				'api_version'    => '2026-03-25.dahlia',
				'metadata'       => [ 'created_by' => 'woocommerce_gateway_stripe' ],
			],
		];
		$changes['writes'][]                               = [
			'method' => 'DELETE',
			'path'   => '/v1/webhook_endpoints/we_' . $stripe_mode . '_old',
			'mode'   => $stripe_mode,
			'body'   => [],
		];
	}
}

if ( 'settings GET' === $stage && 'no' === $settings['pmc_enabled'] ) {
	$changes['settings']['pmc_enabled'] = [ null, $fixture['pmc_available'] ? 'yes' : 'no' ];
	if ( $fixture['pmc_available'] ) {
		$stripe_mode = 'yes' === $settings['testmode'] ? 'test' : 'live';
		// Only this agreeing local/remote profile may write PMC: every preference
		// in the payload must already have that value on Stripe.
		$body = [];
		foreach ( $fixture['pmc_preferences'] as $method_id => $preference ) {
			$body[ $method_id ] = [ 'display_preference' => [ 'preference' => $preference ] ];
		}
		$changes['writes'][] = [
			'method' => 'POST',
			'path'   => '/v1/payment_method_configurations/pmc_' . $stripe_mode . '_fixture',
			'mode'   => $stripe_mode,
			'body'   => $body,
		];
	}
}

return $changes;
