<?php
/**
 * Class WC_Stripe_Agentic_Customize_Checkout_Event
 *
 * Typed wrapper around the v1.delegated_checkout.customize_checkout webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed access to the customize_checkout webhook event data.
 *
 * Stripe sends this event when it needs tax calculations (or shipping
 * options) during the agentic checkout flow. The event contains line
 * items, addresses, and tax configuration.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Customize_Checkout_Event {

	/**
	 * The raw Stripe event object.
	 *
	 * @var stdClass
	 */
	private stdClass $event;

	/**
	 * Constructor.
	 *
	 * @since 10.6.0
	 * @param stdClass $event The raw Stripe event object.
	 */
	public function __construct( stdClass $event ) {
		$this->event = $event;
	}

	/**
	 * Returns the event ID.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_id(): string {
		return (string) ( $this->event->id ?? '' );
	}

	/**
	 * Returns the event type.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_type(): string {
		return (string) ( $this->event->type ?? '' );
	}

	/**
	 * Returns whether this is a live mode event.
	 *
	 * @since 10.6.0
	 * @return bool
	 */
	public function is_livemode(): bool {
		return (bool) ( $this->event->livemode ?? false );
	}

	/**
	 * Returns the currency code in lowercase.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_currency(): string {
		return strtolower( (string) ( $this->event->data->currency ?? '' ) );
	}

	/**
	 * Returns whether Stripe Tax (automatic tax) is enabled.
	 *
	 * When true, Stripe handles tax calculation and the merchant
	 * should not return custom tax rates.
	 *
	 * @since 10.6.0
	 * @return bool
	 */
	public function is_automatic_tax_enabled(): bool {
		return (bool) ( $this->event->data->automatic_tax->enabled ?? false );
	}

	/**
	 * Returns the line items as typed wrapper objects.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_Agentic_Customize_Checkout_Line_Item[]
	 */
	public function get_line_items(): array {
		$raw_items = $this->event->data->line_item_details ?? [];

		if ( ! is_array( $raw_items ) ) {
			return [];
		}

		return array_map(
			function ( $item ) {
				$normalized = $item instanceof stdClass ? $item : (object) $item;
				return new WC_Stripe_Agentic_Customize_Checkout_Line_Item( $normalized );
			},
			$raw_items
		);
	}

	/**
	 * Returns the billing address object.
	 *
	 * Note: The customize_checkout event does not include a separate billing address.
	 * The shipping address is used as the billing address for tax/shipping calculations.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_API_Address
	 */
	public function get_billing_address(): WC_Stripe_API_Address {
		$shipping_details = $this->event->data->shipping_details ?? null;
		$address          = $shipping_details->address ?? null;
		if ( null === $address ) {
			throw new Exception(
				sprintf(
					'Customize checkout hook %s has no billing address.',
					$this->get_id()
				)
			);
		}
		return new WC_Stripe_API_Address( $address );
	}

	/**
	 * Returns the shipping address object.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_API_Address|null
	 */
	public function get_shipping_address(): ?WC_Stripe_API_Address {
		$shipping_details = $this->event->data->shipping_details ?? null;
		$address          = $shipping_details->address ?? null;
		if ( null === $address ) {
			return null;
		}
		return new WC_Stripe_API_Address( $address );
	}
}
