<?php
/**
 * Plugin Name: WC Stripe E2E Webhooks
 * Description: Simulates endpoint availability for the Docker Adaptive Pricing project.
 */

defined( 'ABSPATH' ) || exit;

// The CLI listener forwards real events without a registered localhost endpoint.
// Account refreshes can invalidate a one-time webhook-status cache seed.
add_filter(
	'pre_option_wcstripe_cache_test_webhook_status',
	function ( $value ) {
		if ( ! class_exists( WC_Stripe_Mode::class ) || ! WC_Stripe_Mode::is_test() ) {
			return $value;
		}

		return [
			'data'    => 'enabled',
			'ttl'     => 2 * HOUR_IN_SECONDS,
			'updated' => time(),
		];
	}
);
