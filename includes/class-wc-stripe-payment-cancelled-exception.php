<?php
/**
 * WooCommerce Stripe Payment Cancelled Exception Class
 *
 * Signals a recoverable customer-initiated cancellation during a redirect payment flow
 * (e.g. closing the Klarna popup). Unlike WC_Stripe_Exception, this does not cause the
 * order to be set to the failed status — the shopper is returned to checkout and may retry.
 *
 * @since 10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Payment_Cancelled_Exception extends WC_Stripe_Exception {}
