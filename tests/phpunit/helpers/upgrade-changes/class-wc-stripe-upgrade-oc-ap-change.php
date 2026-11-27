<?php

/** The historical 10.8 rollout intentionally enabled OC/AP for eligible stores. */
class WC_Stripe_Upgrade_OC_AP_Change {
	public static function expected(): array {
		return [
			'settings' => [
				'optimized_checkout_element' => [ 'no', 'yes' ],
				'adaptive_pricing'           => [ 'no', 'yes' ],
			],
			'visible'  => [
				'is_oc_enabled' => true,
				'is_ap_enabled' => true,
			],
		];
	}
}
