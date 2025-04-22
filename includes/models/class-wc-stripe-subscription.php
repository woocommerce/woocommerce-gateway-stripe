<?php
use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Subscription
 *
 * Wrapper for the original WC_Subscription class to allow custom getters and setter with the extension's specific metadata.
 */
class WC_Stripe_Subscription extends WC_Subscription {
	/**
	 * Meta key for the Stripe source ID.
	 *
	 * @var string
	 */
	const META_STRIPE_SOURCE_ID = '_stripe_source_id';
}
