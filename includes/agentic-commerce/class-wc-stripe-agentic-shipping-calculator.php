<?php
/**
 * Class WC_Stripe_Agentic_Shipping_Calculator
 *
 * Calculates shipping options for the customize_checkout webhook event.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes available shipping options for agentic checkout using WooCommerce's shipping engine.
 *
 * Given a customize_checkout event, resolves the destination address from the
 * session, calculates available shipping rates via WooCommerce, and returns them
 * in Stripe's shipping_options format so the AI agent can present them to the customer.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Shipping_Calculator {
	/**
	 * Calculates available shipping options for the given checkout session.
	 *
	 * Returns a Stripe-format response array with shipping_options containing
	 * each available rate's display name and fixed amount. Returns an empty
	 * array when shipping is disabled or no rates are found for the destination.
	 *
	 * @since 10.6.0
	 * @param WC_Stripe_Agentic_Customize_Checkout_Event $event    The customization hook event.
	 * @param string                                     $currency The three-letter currency code (e.g. "USD").
	 * @return array The response array in Stripe's expected format, or [] when no rates apply.
	 * @throws Exception When neither a shipping nor billing address is available.
	 */
	public function calculate( WC_Stripe_Agentic_Customize_Checkout_Event $event, string $currency ): array {
		if ( ! wc_shipping_enabled() ) {
			return [];
		}

		$address = $event->get_shipping_address();
		if ( null === $address ) {
			// Fall back to billing address; let the exception propagate if neither exists.
			$address = $event->get_billing_address();
		}

		// Populate contents with resolved products for content-dependent
		// shipping methods (table rate, weight-based). See STRIPE-986.
		$package = [
			'contents'        => [],
			'contents_cost'   => 0,
			'applied_coupons' => [],
			'user'            => [ 'ID' => get_current_user_id() ],
			'destination'     => [
				'country'  => $address->get_country() ?? '',
				'state'    => $address->get_state() ?? '',
				'postcode' => $address->get_postal_code() ?? '',
				'city'     => $address->get_city() ?? '',
				'address'  => '',
			],
			'cart_subtotal'   => 0,
		];

		$shipping = WC()->shipping();

		if ( ! $shipping ) {
			return [];
		}

		$shipping->calculate_shipping( [ $package ] );

		$shipping_rates = $shipping->get_packages()[0]['rates'] ?? [];

		if ( empty( $shipping_rates ) ) {
			return [];
		}

		$formatted_rates = [];

		$shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );
		if ( 'inherit' === $shipping_tax_class ) {
			$shipping_tax_class = '';
		}

		$tax_location = [
			'country'   => $address->get_country() ?? '',
			'state'     => $address->get_state() ?? '',
			'postcode'  => $address->get_postal_code() ?? '',
			'city'      => $address->get_city() ?? '',
			'tax_class' => (string) $shipping_tax_class,
		];

		$tax_rates = WC_Tax::find_rates( $tax_location );

		foreach ( $shipping_rates as $shipping_rate ) {
			$net_cost = (float) $shipping_rate->get_cost();
			$taxes    = WC_Tax::calc_tax( $net_cost, $tax_rates, false );
			$gross    = $net_cost + array_sum( $taxes );
			$amount   = WC_Stripe_Helper::get_stripe_amount( $gross, $currency );

			$formatted_rates[] = [
				'shipping_rate_data' => [
					'display_name' => $shipping_rate->get_label(),
					// Tax is pre-calculated and included in the amount, so we mark it
					// as inclusive to prevent Stripe from applying tax again.
					'tax_behavior' => 'inclusive',
					'fixed_amount' => [
						'amount'   => $amount,
						'currency' => strtolower( $currency ),
					],
					'metadata'     => [
						'wc_rate_id' => $shipping_rate->get_id(),
					],
				],
			];
		}

		WC_Stripe_Logger::info(
			'Agentic shipping calculator: shipping rates calculated.',
			[
				'rate_count' => count( $formatted_rates ),
				'currency'   => $currency,
			]
		);

		return [ 'shipping_options' => $formatted_rates ];
	}
}
