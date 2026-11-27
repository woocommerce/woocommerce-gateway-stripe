<?php

/** An active PMC is authoritative; refreshing its local mirror must not write to Stripe. */
class WC_Stripe_Upgrade_PMC_Mirror_Change {
	public static function expected(): array {
		return [
			'settings' => [
				'upe_checkout_experience_accepted_payments' => [ [ 'card', 'klarna', 'link' ], [ 'card' ] ],
				'express_checkout'                          => [ 'no', 'yes' ],
			],
		];
	}
}
