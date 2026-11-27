<?php

/** Eligibility is retried after upgrades without resetting existing wallet selections. */
class WC_Stripe_Upgrade_PMC_Retry_Change {
	public static function on_upgrade(): array {
		return [
			'settings' => [
				'pmc_enabled'                        => [ 'no', null ],
				'skip_pmc_express_checkout_defaults' => [ null, 'yes' ],
			],
		];
	}

	public static function when_sync_resumes( string $mode ): array {
		// These cases have agreeing local/remote selections. A conflicting choice
		// needs its own reviewed expectation rather than inheriting this payload.
		return [
			'settings' => [ 'pmc_enabled' => [ null, 'yes' ] ],
			'writes'   => [
				[
					'method' => 'POST',
					'path'   => '/v1/payment_method_configurations/pmc_' . $mode . '_fixture',
					'mode'   => $mode,
					'body'   => [
						'card'       => [ 'display_preference' => [ 'preference' => 'on' ] ],
						'klarna'     => [ 'display_preference' => [ 'preference' => 'off' ] ],
						'link'       => [ 'display_preference' => [ 'preference' => 'off' ] ],
						'apple_pay'  => [ 'display_preference' => [ 'preference' => 'on' ] ],
						'google_pay' => [ 'display_preference' => [ 'preference' => 'on' ] ],
					],
				],
			],
		];
	}

	public static function when_sync_stays_disabled(): array {
		return [ 'settings' => [ 'pmc_enabled' => [ null, 'no' ] ] ];
	}
}
