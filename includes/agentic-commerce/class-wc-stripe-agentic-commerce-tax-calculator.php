<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Tax_Calculator
 *
 * Calculates tax rates for the customize_checkout webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes tax rates for agentic checkout line items using WooCommerce's tax engine.
 *
 * Given a customize_checkout event, resolves each line item's product by
 * SKU, determines the applicable tax class, and returns WooCommerce tax
 * rates in Stripe's rate_data format (percentage-based).
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Commerce_Tax_Calculator {
	/**
	 * Extracts line items from a customization hook event.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event The customization hook event.
	 * @return array<string,string> The line items hash. Line item ID => SKU ID.
	 */
	public function extract_line_items_from_customization_hook(
		WC_Stripe_Agentic_Customize_Checkout_Event $event
	): array {
		$line_items = [];

		foreach ( $event->get_line_items() as $line_item ) {
			$line_items[ $line_item->get_id() ] = $line_item->get_sku_id();
		}

		return $line_items;
	}

	/**
	 * Calculates tax rates for each line item in the customize_checkout event.
	 *
	 * Returns a Stripe-format response array with line_items containing
	 * tax_rates in rate_data format. Stripe applies the rates itself —
	 * we only provide the rate percentages.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event      The customization hook event.
	 * @param array<string,string>                       $line_items The line items hash. Line item ID => Product ID.
	 * @return array The response array in Stripe's expected format.
	 */
	public function calculate(
		WC_Stripe_Agentic_Customize_Checkout_Event $event,
		array $line_items
	): array {
		// If Stripe is managing tax (automatic_tax enabled) or WC tax is disabled, return empty rates.
		if ( $event->is_automatic_tax_enabled() || ! wc_tax_enabled() ) {
			return [
				'line_items' => $this->map_line_items(
					fn( $line_item_id ) => [
						'id'        => $line_item_id,
						'tax_rates' => [],
					],
					$line_items
				),
			];
		}

		$tax_based_on_billing = 'billing' === get_option( 'woocommerce_tax_based_on' );

		// Use the billing address by default.
		$tax_address = $event->get_billing_address();

		// Prefer the shipping address if available and set as preference.
		if ( ! $tax_based_on_billing ) {
			$shipping_address = $event->get_shipping_address();
			if ( $shipping_address ) {
				$tax_address = $shipping_address;
			}
		}

		$response_items = $this->map_line_items(
			fn( $line_item_id, $product_id ) => $this->calculate_line_item_taxes( $line_item_id, $product_id, $tax_address ),
			$line_items
		);

		return [ 'line_items' => $response_items ];
	}

	/**
	 * Calculates tax rates for a single line item.
	 *
	 * @since 10.6.0
	 * @param string                 $line_item_id The line item ID.
	 * @param string                 $product_id   The product ID.
	 * @param WC_Stripe_API_Address  $address      The tax address.
	 * @return array The line item tax response.
	 */
	private function calculate_line_item_taxes(
		string $line_item_id,
		string $product_id,
		WC_Stripe_API_Address $address
	): array {
		if ( '' === $product_id ) {
			throw new Exception(
				sprintf(
					'Line item %s has no sku_id.',
					$line_item_id
				)
			);
		}

		$product = WC_Stripe_Agentic_Commerce_Product_Resolver::resolve_product( (int) $product_id );

		$tax_rates = WC_Tax::find_rates(
			[
				'country'   => $address->get_country(),
				'state'     => $address->get_state(),
				'postcode'  => $address->get_postal_code(),
				'city'      => $address->get_city(),
				'tax_class' => $product->get_tax_class(),
			]
		);

		return [
			'id'        => $line_item_id,
			'tax_rates' => $this->format_tax_rates( $tax_rates ),
		];
	}

	/**
	 * Converts WooCommerce tax rates to Stripe's rate_data format.
	 *
	 * @since 10.6.0
	 * @param array $wc_tax_rates Tax rates from WC_Tax::find_rates().
	 * @return array Array of Stripe tax rate objects with rate_data.
	 */
	private function format_tax_rates( array $wc_tax_rates ): array {
		$inclusive = wc_prices_include_tax();
		$formatted = [];

		foreach ( $wc_tax_rates as $rate ) {
			$formatted[] = [
				'rate_data' => [
					'display_name' => $rate['label'] ?? __( 'Tax', 'woocommerce-gateway-stripe' ),
					'inclusive'    => $inclusive,
					'percentage'   => (float) ( $rate['rate'] ?? 0 ),
				],
			];
		}

		return $formatted;
	}

	/**
	 * Maps line items to a callback.
	 *
	 * @since 10.6.0
	 * @param callable $callback   The callback to map the line items.
	 * @param array    $line_items The line items.
	 * @return array The mapped line items.
	 */
	private function map_line_items( callable $callback, array $line_items ): array {
		$result = [];

		foreach ( $line_items as $line_item_id => $product_id ) {
			$result[] = $callback( $line_item_id, $product_id );
		}

		return $result;
	}
}
