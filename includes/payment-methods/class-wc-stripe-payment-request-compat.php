<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward-compatibility shim for the removed WC_Stripe_Payment_Request class.
 *
 * The legacy WC_Stripe_Payment_Request class was removed in 10.4.0 in favour of the
 * Express Checkout implementation (WC_Stripe_Express_Checkout_Element). Some third-party
 * integrations still read `woocommerce_gateway_stripe()->payment_request_configuration`
 * and register its methods as hook callbacks — for example Avada's "WooCommerce One Page
 * Checkout" module registers `display_payment_request_button_html()` and
 * `display_payment_request_button_separator_html()` on `woocommerce_review_order_before_submit`.
 *
 * When that property was `null`, those callbacks resolved to `[ null, 'method' ]` and threw
 * an uncaught TypeError at hook-fire time, fatalling the entire checkout page:
 *
 *   call_user_func_array(): Argument #1 ($callback) must be a valid callback,
 *   first array member is not a valid class name or object
 *
 * This shim restores a harmless object on that property so those legacy callbacks resolve to
 * valid, no-op methods instead of fatalling. It intentionally does NOT reintroduce any Payment
 * Request behaviour — the buttons themselves are now rendered by the Express Checkout element.
 *
 * @deprecated 10.4.0 Use WC_Stripe_Express_Checkout_Element instead. This shim only exists to
 *             avoid fatal errors from third-party callers that still reference the removed
 *             class, and will be removed in a future release.
 */
class WC_Stripe_Payment_Request_Compat {
	/**
	 * No-op replacement for the removed button-rendering callback.
	 *
	 * @return void
	 */
	public function display_payment_request_button_html() {}

	/**
	 * No-op replacement for the removed separator-rendering callback.
	 *
	 * @return void
	 */
	public function display_payment_request_button_separator_html() {}

	/**
	 * Swallow any other call to a removed method so legacy callbacks never fatal.
	 *
	 * @param string $name      The method being called.
	 * @param array  $arguments The arguments passed to the method.
	 *
	 * @return null
	 */
	public function __call( $name, $arguments ) {
		return null;
	}
}
