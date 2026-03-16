<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Manual_Approval
 *
 * Validates an agentic finalize_checkout event and returns an approval or decline response.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the manual approval decision for agentic checkout orders.
 *
 * Validates each line item for product existence, purchasability,
 * and stock availability. Provides a filter for custom merchant logic.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Commerce_Manual_Approval {

	/**
	 * Validates the finalize_checkout event and returns the approval response.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event The finalize checkout event.
	 * @return array The manual_approval_details response array.
	 * @throws Exception When product resolution fails.
	 */
	public function validate( WC_Stripe_Agentic_Customize_Checkout_Event $event ): array {
		$line_items     = $event->get_line_items();
		$decline_reason = null;

		foreach ( $line_items as $line_item ) {
			$decline_reason = $this->validate_line_item( $line_item );

			if ( null !== $decline_reason ) {
				break;
			}
		}

		/**
		 * Filters the manual approval decision for an agentic checkout order.
		 *
		 * Return null to approve, or a non-empty string to decline with that reason.
		 *
		 * @since 10.6.0
		 * @param string|null                                $decline_reason Null to approve, or a decline reason string.
		 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event          The finalize checkout event.
		 */
		$decline_reason = apply_filters( 'wc_stripe_agentic_approve_order', $decline_reason, $event );

		if ( null === $decline_reason ) {
			return [
				'manual_approval_details' => [
					'type' => 'approved',
				],
			];
		}

		return [
			'manual_approval_details' => [
				'type'     => 'declined',
				'declined' => [
					'reason' => (string) $decline_reason,
				],
			],
		];
	}

	/**
	 * Validates a single line item for purchasability and stock.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Line_Item $line_item The line item to validate.
	 * @return string|null Null if valid, or a decline reason string.
	 * @throws Exception When product resolution fails.
	 */
	private function validate_line_item( WC_Stripe_Agentic_Customize_Checkout_Line_Item $line_item ): ?string {
		$product_id = (int) $line_item->get_sku_id();
		$product    = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product( $product_id );

		if ( ! $product->is_purchasable() ) {
			return sprintf(
				/* translators: %s: product name */
				__( '%s is not available for purchase.', 'woocommerce-gateway-stripe' ),
				$product->get_name()
			);
		}

		if ( ! $product->is_in_stock() ) {
			return sprintf(
				/* translators: %s: product name */
				__( '%s is out of stock.', 'woocommerce-gateway-stripe' ),
				$product->get_name()
			);
		}

		if ( $product->managing_stock() ) {
			$stock_quantity = $product->get_stock_quantity();
			$quantity       = $line_item->get_quantity();

			if ( null === $stock_quantity || $quantity > $stock_quantity ) {
				return sprintf(
					/* translators: 1: product name, 2: available quantity */
					__( 'Insufficient stock for %1$s. Only %2$d available.', 'woocommerce-gateway-stripe' ),
					$product->get_name(),
					(int) $stock_quantity
				);
			}
		}

		return null;
	}
}
