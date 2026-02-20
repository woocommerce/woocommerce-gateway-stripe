<?php
/**
 * Class WC_Stripe_Agentic_Commerce_Tax_Calculator
 *
 * Calculates tax rates for the customize_checkout webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.5.0
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
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Tax_Calculator {

	/**
	 * Calculates tax rates for each line item in the customize_checkout event.
	 *
	 * Returns a Stripe-format response array with line_items containing
	 * tax_rates in rate_data format. Stripe applies the rates itself —
	 * we only provide the rate percentages.
	 *
	 * @since 10.5.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event The customize_checkout event.
	 * @return array The response array in Stripe's expected format.
	 */
	public function calculate( WC_Stripe_Agentic_Customize_Checkout_Event $event ): array {
		$tax_address = $event->get_tax_address();

		if ( ! $tax_address ) {
			WC_Stripe_Logger::error(
				'Agentic tax calculator: no address provided for tax calculation.',
				[ 'event_id' => $event->get_id() ]
			);

			return [];
		}

		$country  = $tax_address->country ?? '';
		$state    = $tax_address->state ?? '';
		$postcode = $tax_address->postal_code ?? '';
		$city     = $tax_address->city ?? '';

		$location = [
			'country'  => $country,
			'state'    => $state,
			'postcode' => $postcode,
			'city'     => $city,
		];

		$line_items    = $event->get_line_items();
		$response_items = [];

		foreach ( $line_items as $line_item ) {
			$item_response = $this->calculate_line_item_taxes( $line_item, $location, $event->get_id() );

			if ( null !== $item_response ) {
				$response_items[] = $item_response;
			}
		}

		return [ 'line_items' => $response_items ];
	}

	/**
	 * Calculates tax rates for a single line item.
	 *
	 * @since 10.5.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Line_Item $line_item The line item.
	 * @param array                                          $location  Tax location with country, state, postcode, city keys.
	 * @param string                                         $event_id  The event ID for logging.
	 * @return array|null The line item tax response, or null if skipped.
	 */
	private function calculate_line_item_taxes(
		WC_Stripe_Agentic_Customize_Checkout_Line_Item $line_item,
		array $location,
		string $event_id
	): ?array {
		$sku = $line_item->get_sku_id();

		if ( '' === $sku ) {
			WC_Stripe_Logger::warning(
				'Agentic tax calculator: line item has no sku_id, skipping.',
				[
					'event_id'     => $event_id,
					'line_item_id' => $line_item->get_id(),
				]
			);
			return null;
		}

		$product = $this->find_product_by_sku( $sku );

		if ( ! $product ) {
			WC_Stripe_Logger::warning(
				'Agentic tax calculator: product not found for SKU, skipping line item.',
				[
					'event_id'     => $event_id,
					'sku_id'       => $sku,
					'line_item_id' => $line_item->get_id(),
				]
			);
			return null;
		}

		$tax_class             = $product->get_tax_class();
		$location['tax_class'] = $tax_class;
		$tax_rates             = WC_Tax::find_rates( $location );

		return [
			'id'        => $line_item->get_id(),
			'tax_rates' => $this->format_tax_rates( $tax_rates ),
		];
	}

	/**
	 * Converts WooCommerce tax rates to Stripe's rate_data format.
	 *
	 * @since 10.5.0
	 * @param array $wc_tax_rates Tax rates from WC_Tax::find_rates().
	 * @return array Array of Stripe tax rate objects with rate_data.
	 */
	private function format_tax_rates( array $wc_tax_rates ): array {
		$inclusive  = wc_prices_include_tax();
		$formatted = [];

		foreach ( $wc_tax_rates as $rate ) {
			$formatted[] = [
				'rate_data' => [
					'display_name' => $rate['label'] ?? __( 'Tax', 'woocommerce-gateway-stripe' ),
					'inclusive'    => $inclusive,
					'percentage'  => (float) ( $rate['rate'] ?? 0 ),
				],
			];
		}

		return $formatted;
	}

	/**
	 * Finds a WooCommerce product by its SKU.
	 *
	 * @since 10.5.0
	 * @param string $sku The product SKU.
	 * @return WC_Product|null The product, or null if not found.
	 */
	private function find_product_by_sku( string $sku ): ?WC_Product {
		$product_id = wc_get_product_id_by_sku( $sku );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		/**
		 * Filters the product resolved from a SKU during agentic tax calculation.
		 *
		 * @since 10.5.0
		 * @param WC_Product|false|null $product The resolved product (or null/false).
		 * @param string               $sku     The SKU that was looked up.
		 */
		$product = apply_filters( 'wc_stripe_agentic_tax_product_by_sku', $product, $sku );

		return $product instanceof WC_Product ? $product : null;
	}
}
