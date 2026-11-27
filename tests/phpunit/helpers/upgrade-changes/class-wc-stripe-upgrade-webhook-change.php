<?php

/** Adding charge.expired replaces endpoints and signing secrets in both modes. */
class WC_Stripe_Upgrade_Webhook_Change {
	public static function expected(): array {
		$changes = [
			'settings' => [],
			'writes'   => [],
		];
		$events  = json_decode( file_get_contents( __DIR__ . '/../../fixtures/upgrades/webhook-events.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local, immutable fixture.
		array_splice( $events, 5, 0, [ 'charge.expired' ] );
		foreach ( [ 'live', 'test' ] as $mode ) {
			$prefix = 'live' === $mode ? '' : 'test_';
			$changes['settings'][ $prefix . 'webhook_data' ]   = [
				[
					'id'     => 'we_' . $mode . '_old',
					'url'    => '{{webhook_url}}',
					'secret' => 'sk_' . $mode . '_upgrade_fixture',
				],
				[
					'id'             => 'we_' . $mode . '_new',
					'url'            => '{{webhook_url}}',
					'signing_secret' => 'whsec_' . $mode . '_new_fixture',
				],
			];
			$changes['settings'][ $prefix . 'webhook_secret' ] = [ 'whsec_' . $mode . '_old_fixture', 'whsec_' . $mode . '_new_fixture' ];
			$changes['writes'][]                               = [
				'method' => 'POST',
				'path'   => '/v1/webhook_endpoints',
				'mode'   => $mode,
				'body'   => [
					'enabled_events' => $events,
					'url'            => '{{webhook_url}}',
					'api_version'    => '2026-03-25.dahlia',
					'metadata'       => [ 'created_by' => 'woocommerce_gateway_stripe' ],
				],
			];
			$changes['writes'][]                               = [
				'method' => 'DELETE',
				'path'   => '/v1/webhook_endpoints/we_' . $mode . '_old',
				'mode'   => $mode,
				'body'   => [],
			];
		}
		return $changes;
	}
}
