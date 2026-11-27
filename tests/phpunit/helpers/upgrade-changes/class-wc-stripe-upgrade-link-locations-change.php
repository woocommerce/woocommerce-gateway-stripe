<?php

/** Before 10.9, Link inherited the express checkout locations. */
class WC_Stripe_Upgrade_Link_Locations_Change {
	public static function expected(): array {
		return [
			'settings' => [
				'link_button_locations' => [ null, [ 'cart' ] ],
			],
		];
	}
}
