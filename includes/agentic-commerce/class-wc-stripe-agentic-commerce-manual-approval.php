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
		$line_items        = $event->get_line_items();
		$decline           = null;
		$invalid_line_item = null;

		foreach ( $line_items as $line_item ) {
			$decline = $this->validate_line_item( $line_item );

			if ( null !== $decline ) {
				$invalid_line_item = $line_item;
				break;
			}
		}

		/**
		 * Filters the manual approval decision for an agentic checkout order.
		 *
		 * Return null to approve, or an array with 'code' and 'reason' keys to decline.
		 * Example: [ 'code' => 'not_purchasable', 'reason' => 'Product is not available.' ]
		 *
		 * @since 10.6.0
		 * @param array|null                                      $decline           Null to approve, or array with 'code' and 'reason'.
		 * @param WC_Stripe_Agentic_Customize_Checkout_Event      $event             The finalize checkout event.
		 * @param WC_Stripe_Agentic_Customize_Checkout_Line_Item|null $invalid_line_item The line item that failed validation, or null.
		 */
		$decline = apply_filters( 'wc_stripe_agentic_approve_order', $decline, $event, $invalid_line_item );

		if ( null === $decline ) {
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
					'reason' => (string) ( $decline['reason'] ?? '' ),
				],
			],
		];
	}

	/**
	 * Validates a single line item for purchasability and stock.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Line_Item $line_item The line item to validate.
	 * @return array|null Null if valid, or array with 'code' and 'reason' keys.
	 * @throws Exception When product resolution fails.
	 */
	private function validate_line_item( WC_Stripe_Agentic_Customize_Checkout_Line_Item $line_item ): ?array {
		$product_id = (int) $line_item->get_sku_id();
		$product    = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product( $product_id );

		if ( ! $product->is_purchasable() ) {
			return [
				'code'   => 'not_purchasable',
				'reason' => sprintf(
					/* translators: %s: product name */
					__( '%s is not available for purchase.', 'woocommerce-gateway-stripe' ),
					$product->get_name()
				),
			];
		}

		if ( ! $product->is_in_stock() ) {
			return [
				'code'   => 'not_in_stock',
				'reason' => sprintf(
					/* translators: %s: product name */
					__( '%s is out of stock.', 'woocommerce-gateway-stripe' ),
					$product->get_name()
				),
			];
		}

		if ( $product->managing_stock() ) {
			$stock_quantity = $product->get_stock_quantity();
			$quantity       = $line_item->get_quantity();

			if ( null === $stock_quantity || $quantity > $stock_quantity ) {
				return [
					'code'   => 'insufficient_stock',
					'reason' => sprintf(
						/* translators: 1: product name, 2: available quantity */
						__( 'Insufficient stock for %1$s. Only %2$d available.', 'woocommerce-gateway-stripe' ),
						$product->get_name(),
						(int) $stock_quantity
					),
				];
			}
		}

		return null;
	}
}
