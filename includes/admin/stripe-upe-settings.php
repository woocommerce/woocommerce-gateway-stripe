<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_stripe_upe_settings',
	[
		'upe_checkout_experience_accepted_payments' => [
			'type' => 'upe_checkout_experience_accepted_payments',
		],
	]
);
