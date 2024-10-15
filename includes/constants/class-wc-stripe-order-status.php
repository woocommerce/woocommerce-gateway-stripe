<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Order_Status
 *
 * Contains a list of order statuses used by the WooCommerce plugin.
 */
class WC_Stripe_Order_Status {
	const CANCELLED  = 'cancelled';
	const COMPLETED  = 'completed';
	const FAILED     = 'failed';
	const ON_HOLD    = 'on-hold';
	const PENDING    = 'pending';
	const PROCESSING = 'processing';
	const REFUNDED   = 'refunded';
	const TRASH      = 'trash';
}
