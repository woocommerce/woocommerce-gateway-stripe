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
	 * Returns the WooCommerce product ID from the price's external_reference.
	 *
	 * Returns 0 if the price object is missing, external_reference is absent,
	 * or the value is not a valid nonzero integer.
	 *
	 * @since 10.6.0
	 * @return int
	 */
	public function get_product_id(): int {
		if ( ! isset( $this->item->price ) || ! is_object( $this->item->price ) ) {
			return 0;
		}

		return intval( $this->item->price->external_reference ?? '' );
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
