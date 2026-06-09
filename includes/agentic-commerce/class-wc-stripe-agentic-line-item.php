<?php
/**
 * Class WC_Stripe_Agentic_Line_Item
 *
 * Typed wrapper around a raw Stripe checkout session line item.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed access to a single Stripe checkout session line item.
 *
 * The Stripe API returns untyped objects from json_decode(). This class
 * wraps a single line item and provides small, testable getter methods
 * with proper return types and fallback logic.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Line_Item {

	/**
	 * The raw Stripe line item object.
	 *
	 * @var object
	 */
	private object $item;

	/**
	 * Constructor.
	 *
	 * @since 10.6.0
	 * @param object $item The raw Stripe line item object.
	 */
	public function __construct( object $item ) {
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
	 * Returns the line item description.
	 *
	 * @since 10.6.0
	 * @return string
	 */
	public function get_description(): string {
		return (string) ( $this->item->description ?? '' );
	}

	/**
	 * Returns the quantity.
	 *
	 * @since 10.6.0
	 * @return int
	 */
	public function get_quantity(): int {
		return (int) ( $this->item->quantity ?? 1 );
	}

	/**
	 * Returns the total amount in the smallest currency unit (includes tax).
	 *
	 * @since 10.6.0
	 * @return int
	 */
	public function get_amount_total(): int {
		return (int) ( $this->item->amount_total ?? 0 );
	}

	/**
	 * Returns the tax amount in the smallest currency unit.
	 *
	 * @since 10.6.0
	 * @return int
	 */
	public function get_amount_tax(): int {
		return (int) ( $this->item->amount_tax ?? 0 );
	}

	/**
	 * Returns the WooCommerce product ID resolved from the price's external_reference.
	 *
	 * Tries the merchant SKU first (current sync contract), then falls back to
	 * a numeric product-ID lookup so catalogs synced under the legacy
	 * "external_reference = product_id" contract — and products without a SKU —
	 * keep resolving instead of failing the checkout. Returns 0 when neither
	 * path matches a real product.
	 *
	 * @since 10.6.0
	 * @return int
	 */
	public function get_product_id(): int {
		if ( ! isset( $this->item->price ) || ! is_object( $this->item->price ) ) {
			return 0;
		}

		$external_reference = $this->item->price->external_reference ?? '';
		if ( ! is_string( $external_reference ) ) {
			return 0;
		}

		return WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product_id_by_external_reference( $external_reference );
	}

	/**
	 * Checks whether this line item has a resolvable WooCommerce product ID.
	 *
	 * @since 10.6.0
	 * @return bool
	 */
	public function has_product_id(): bool {
		return 0 !== $this->get_product_id();
	}
}
