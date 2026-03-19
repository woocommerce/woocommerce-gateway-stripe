<?php
/**
 * Class WC_Stripe_Agentic_Customize_Checkout_Line_Item
 *
 * Typed wrapper around a line item from the customize_checkout webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed access to a single customize_checkout line item.
 *
 * Unlike WC_Stripe_Agentic_Line_Item (which wraps a completed checkout
 * session line item using price.external_reference), this class wraps
 * the line_item_details entries from the customize_checkout event
 * which use sku_id for product identification.
 *
 * This is a simple value object with two getters. It is tested indirectly
 * through the tax calculator and customization hook tests.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Customize_Checkout_Line_Item {

	/**
	 * The raw line item object.
	 *
	 * @var stdClass
	 */
	private stdClass $item;

	/**
	 * Constructor.
	 *
	 * @since 10.6.0
	 * @param stdClass $item The raw line item object from the customize_checkout event.
	 */
	public function __construct( stdClass $item ) {
		$this->item = $item;
	}

	/**
	 * Returns the line item ID.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_id(): string {
		return (string) ( $this->item->id ?? '' );
	}

	/**
	 * Returns the SKU ID used to look up the WooCommerce product.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_sku_id(): string {
		return (string) ( $this->item->sku_id ?? '' );
	}

	/**
	 * Returns the requested quantity.
	 *
	 * @since 10.6.0
	 * @return int
	 */
	public function get_quantity(): int {
		return (int) ( $this->item->quantity ?? 1 );
	}

	/**
	 * Returns the product name.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_name(): string {
		return (string) ( $this->item->name ?? '' );
	}
}
