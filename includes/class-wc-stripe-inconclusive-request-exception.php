<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown when a Stripe request may or may not have been processed.
 *
 * The connection failed, Stripe answered with an outage status (5xx), or the response body was
 * empty. Stripe may have carried out the request, so a caller that retries must resend the same
 * idempotency key, or it risks doing the work twice (for example charging a card twice).
 *
 * It extends WC_Stripe_Exception so every existing catch block keeps handling it.
 *
 * @since x.x.x
 */
class WC_Stripe_Inconclusive_Request_Exception extends WC_Stripe_Exception {
}
