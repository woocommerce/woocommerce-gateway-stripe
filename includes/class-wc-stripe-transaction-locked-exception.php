<?php
/**
 * WooCommerce Stripe Transaction Locked Exception Class
 *
 * Signals that another request is recording the same charge on the order right now, for example
 * the customer's return and a webhook settling the same payment. The other request completes the
 * payment, so callers should retry later instead of failing the order.
 *
 * @since x.x.x
 * @internal Not part of the public API; it can change without notice.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Transaction_Locked_Exception extends WC_Stripe_Exception {}
