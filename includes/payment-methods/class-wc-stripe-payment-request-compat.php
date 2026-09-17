<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No-op stand-in for the WC_Stripe_Payment_Request class removed in 10.4.0.
 *
 * Third parties still read woocommerce_gateway_stripe()->payment_request_configuration and
 * register its methods as hook callbacks; a null property made those callbacks fatal at
 * hook-fire time. This keeps them resolving to a valid object without restoring any behaviour.
 *
 * Every call logs a deprecation so remaining third-party usage stays visible; remove this
 * class together with the WC_Stripe::$payment_request_configuration property once that
 * signal dies down.
 *
 * @deprecated 10.4.0 Use WC_Stripe_Express_Checkout_Element instead.
 */
class WC_Stripe_Payment_Request_Compat {
	public function display_payment_request_button_html(): void {
		wc_deprecated_function( 'WC_Stripe_Payment_Request::display_payment_request_button_html', '10.4.0', 'WC_Stripe_Express_Checkout_Element' );
	}

	public function display_payment_request_button_separator_html(): void {
		wc_deprecated_function( 'WC_Stripe_Payment_Request::display_payment_request_button_separator_html', '10.4.0', 'WC_Stripe_Express_Checkout_Element' );
	}

	/**
	 * Swallow any other removed method so legacy callbacks never fatal.
	 *
	 * @param string  $name
	 * @param mixed[] $arguments
	 * @return null
	 */
	public function __call( string $name, array $arguments ) {
		wc_deprecated_function( 'WC_Stripe_Payment_Request::' . $name, '10.4.0', 'WC_Stripe_Express_Checkout_Element' );
		return null;
	}
}
