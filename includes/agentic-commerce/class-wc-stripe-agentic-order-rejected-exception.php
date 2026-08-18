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
 * creation — no retry can succeed. The webhook handler treats it differently from a
 * transient failure: the shopper was already charged (payment is captured before the
 * webhook fires), so the payment is refunded and the job is not retried.
 *
 * @since 11.0.0
 */
class WC_Stripe_Agentic_Order_Rejected_Exception extends Exception {}
