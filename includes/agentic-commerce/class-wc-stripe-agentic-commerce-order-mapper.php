<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Order_Mapper
 *
 * Maps Stripe checkout session data to WooCommerce orders.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates WooCommerce orders from Stripe agentic checkout session data.
 *
 * @since 10.5.0
 * @see STRIPE-902 for full implementation.
 */
class WC_Stripe_Agentic_Commerce_Order_Mapper {

	/**
	 * Creates a WooCommerce order from a Stripe checkout session.
	 *
	 * @since 10.5.0
	 * @param object $checkout_session The Stripe checkout session object.
	 * @return WC_Order The created order.
	 * @throws Exception When the order cannot be created.
	 */
	public function create_order_from_checkout_session( object $checkout_session ): WC_Order {
		throw new Exception(
			esc_html(
				sprintf(
					'Agentic commerce order mapper not yet implemented. Session: %s',
					$checkout_session->id ?? 'unknown'
				)
			)
		);
	}
}
