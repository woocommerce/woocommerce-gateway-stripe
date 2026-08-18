<?php
/**
 * Class WC_Stripe_Agentic_Order_Rejected_Exception
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   11.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signals that an agentic checkout session was permanently rejected during order
 * creation. The shopper was already charged, so the webhook handler refunds the
 * payment instead of retrying.
 *
 * @since 11.0.0
 */
class WC_Stripe_Agentic_Order_Rejected_Exception extends Exception {}
