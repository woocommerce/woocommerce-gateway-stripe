<?php
/**
 * Class WC_Stripe_Agentic_Price_Availability_Event
 *
 * Typed wrapper around the delegated_commerce.product_price_availability webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed access to the product_price_availability webhook event data.
 *
 * Stripe (or an agent, via Stripe) sends this event to request real-time
 * pricing and inventory for a single SKU instead of relying on the
 * uploaded catalog feed. Unlike the delegated checkout hooks, this event
 * carries no checkout session.
 *
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Price_Availability_Event {

	/**
	 * The raw Stripe event object.
	 *
	 * @var stdClass
	 */
	private stdClass $event;

	/**
	 * Constructor.
	 *
	 * @since 10.9.0
	 * @param stdClass $event The raw Stripe event object.
	 */
	public function __construct( stdClass $event ) {
		$this->event = $event;
	}

	/**
	 * Returns the event ID.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public function get_id(): string {
		return (string) ( $this->event->id ?? '' );
	}

	/**
	 * Returns the event type.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public function get_type(): string {
		return (string) ( $this->event->type ?? '' );
	}

	/**
	 * Returns whether this is a live mode event.
	 *
	 * @since 10.9.0
	 * @return bool
	 */
	public function is_livemode(): bool {
		return (bool) ( $this->event->livemode ?? false );
	}

	/**
	 * Returns the SKU identifier whose price and availability are requested.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public function get_sku_id(): string {
		return (string) ( $this->event->data->sku_id ?? '' );
	}

	/**
	 * Returns the merchant account ID from the event context.
	 *
	 * The response's `merchant_id` must echo this value back to Stripe.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public function get_merchant_id(): string {
		return (string) ( $this->event->context ?? '' );
	}
}
