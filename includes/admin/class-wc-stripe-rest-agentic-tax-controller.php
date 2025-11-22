<?php
/**
 * REST controller for Agentic Checkout tax calculation hook.
 *
 * @package WooCommerce_Stripe/Admin
 * @since   8.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agentic Tax Calculation REST Controller.
 */
class WC_Stripe_REST_Agentic_Tax_Controller extends WP_REST_Controller {
	use WC_Stripe_Agentic_Authentication;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'stripe/agentic/tax';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'compute_tax' ),
					'permission_callback' => '__return_true', // Auth handled via signature.
				),
			)
		);
	}

	/**
	 * Get registered routes.
	 *
	 * @return array
	 */
	public function get_routes() {
		$routes = array();
		$routes[ '/' . $this->namespace . '/' . $this->rest_base ] = array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compute_tax' ),
				'permission_callback' => '__return_true',
			),
		);
		return $routes;
	}

	/**
	 * Handle tax calculation request from Stripe.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function compute_tax( $request ) {
		// Check if feature is enabled.
		if ( ! $this->is_agentic_checkout_enabled() ) {
			WC_Stripe_Logger::log( 'Agentic tax calculation request rejected: feature disabled' );
			return new WP_Error(
				'feature_disabled',
				'Agentic Checkout is not enabled',
				array( 'status' => 403 )
			);
		}

		// Verify Stripe signature.
		$verification = $this->verify_stripe_signature();
		if ( is_wp_error( $verification ) ) {
			WC_Stripe_Logger::log(
				'Agentic tax calculation request rejected: invalid signature',
				array( 'error' => $verification->get_error_message() )
			);
			return $verification;
		}

		// Parse request body.
		$body                = json_decode( $request->get_body(), true );
		$checkout_session_id = $body['id'] ?? '';
		$line_items          = $body['line_items_details'] ?? array();
		$fulfillment         = $body['fulfillment_details'] ?? array();
		$billing             = $body['billing_details'] ?? array();
		$currency            = $body['currency'] ?? 'usd';

		// Validate JSON parsing.
		if ( null === $body && json_last_error() !== JSON_ERROR_NONE ) {
			WC_Stripe_Logger::log(
				'Agentic tax calculation request failed: invalid JSON',
				array( 'error' => json_last_error_msg() )
			);
			return new WP_Error(
				'invalid_json',
				'Invalid JSON in request body',
				array( 'status' => 400 )
			);
		}

		WC_Stripe_Logger::log(
			'Agentic tax calculation request received',
			array(
				'checkout_session_id' => $checkout_session_id,
				'line_items_count'    => count( $line_items ),
				'currency'            => $currency,
			)
		);

		try {
			// Calculate taxes.
			$tax_breakdown = $this->calculate_taxes( $line_items, $fulfillment, $billing );

			/**
			 * Filter tax breakdown for agentic checkout.
			 *
			 * @since 8.9.0
			 * @param array $tax_breakdown Tax breakdown with amount and display_name for each rate.
			 * @param array $line_items    Line items.
			 * @param array $addresses     Fulfillment and billing addresses.
			 */
			$tax_breakdown = apply_filters(
				'wc_stripe_agentic_tax_breakdown',
				$tax_breakdown,
				$line_items,
				array(
					'fulfillment' => $fulfillment,
					'billing'     => $billing,
				)
			);

			// Calculate total tax from breakdown.
			$total_tax = array_sum( array_column( $tax_breakdown, 'amount' ) );

			WC_Stripe_Logger::log(
				'Agentic tax calculated',
				array(
					'checkout_session_id' => $checkout_session_id,
					'total_tax'           => $total_tax,
					'tax_count'           => count( $tax_breakdown ),
				)
			);

			// Format response according to Stripe protocol.
			return new WP_REST_Response(
				array(
					'id'     => $checkout_session_id,
					'result' => array(
						'type'       => 'calculated',
						'calculated' => array(
							'taxes' => $tax_breakdown,
						),
					),
				),
				200
			);

		} catch ( Exception $e ) {
			WC_Stripe_Logger::log(
				'Agentic tax calculation failed',
				array( 'error' => $e->getMessage() )
			);

			return new WP_Error(
				'tax_calculation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Calculate taxes using WooCommerce.
	 *
	 * @param array $line_items  Line items.
	 * @param array $fulfillment Fulfillment details.
	 * @param array $billing     Billing details.
	 * @return array Tax breakdown with amount and display_name for each rate.
	 * @throws Exception If calculation fails.
	 */
	private function calculate_taxes( $line_items, $fulfillment, $billing ) {
		// Use shipping address for tax calculation (or billing if no shipping).
		$tax_address = $fulfillment['address'] ?? $billing['address'] ?? array();

		if ( empty( $tax_address ) ) {
			throw new Exception( 'No address provided for tax calculation' );
		}

		// Set customer location for tax calculation.
		$country  = $tax_address['country'] ?? '';
		$state    = $tax_address['state'] ?? '';
		$postcode = $tax_address['postal_code'] ?? '';
		$city     = $tax_address['city'] ?? '';

		// Track taxes by rate ID to aggregate across items.
		$tax_totals = array();

		// Calculate tax for each line item.
		foreach ( $line_items as $item ) {
			$sku = $item['sku_id'] ?? '';
			// Convert cents to dollars for WC tax calculation.
			$price = ( ( $item['unit_amount'] ?? 0 ) / 100 ) * ( $item['quantity'] ?? 1 );

			// Get product for tax class.
			$product = $this->get_product_by_sku( $sku );

			if ( ! $product ) {
				throw new Exception( sprintf( 'Product not found for SKU: %s', esc_html( $sku ) ) );
			}

			$tax_class = $product->get_tax_class();

			// Get tax rates for this product and location.
			$tax_rates = WC_Tax::get_rates( $tax_class, array( $country, $state, $postcode, $city ) );

			// Handle empty tax rates (no taxes apply).
			if ( empty( $tax_rates ) ) {
				continue;
			}

			// Calculate tax for this item.
			$taxes = WC_Tax::calc_tax( $price, $tax_rates );

			// Aggregate taxes by rate ID.
			foreach ( $taxes as $rate_id => $tax_amount ) {
				if ( ! isset( $tax_totals[ $rate_id ] ) ) {
					// Get the tax rate details for display name.
					$rate_details = $tax_rates[ $rate_id ] ?? null;
					if ( ! $rate_details ) {
						continue;
					}

					$tax_totals[ $rate_id ] = array(
						'amount'       => 0,
						'display_name' => $rate_details['label'] ?? 'Tax',
					);
				}

				$tax_totals[ $rate_id ]['amount'] += $tax_amount;
			}
		}

		// Calculate shipping tax if shipping amount is provided.
		$shipping_amount = $fulfillment['shipping_amount'] ?? 0;
		if ( $shipping_amount > 0 ) {
			// Convert cents to dollars for WC tax calculation.
			$shipping_amount_dollars = $shipping_amount / 100;

			// Get shipping tax class (usually empty string for standard rate).
			$shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );
			if ( 'inherit' === $shipping_tax_class ) {
				$shipping_tax_class = '';
			}

			// Get tax rates for shipping.
			$shipping_tax_rates = WC_Tax::get_rates( $shipping_tax_class, array( $country, $state, $postcode, $city ) );

			if ( ! empty( $shipping_tax_rates ) ) {
				// Calculate shipping taxes.
				$shipping_taxes = WC_Tax::calc_shipping_tax( $shipping_amount_dollars, $shipping_tax_rates );

				// Aggregate shipping taxes.
				foreach ( $shipping_taxes as $rate_id => $tax_amount ) {
					if ( ! isset( $tax_totals[ $rate_id ] ) ) {
						$rate_details = $shipping_tax_rates[ $rate_id ] ?? null;
						if ( ! $rate_details ) {
							continue;
						}

						$tax_totals[ $rate_id ] = array(
							'amount'       => 0,
							'display_name' => $rate_details['label'] ?? 'Tax',
						);
					}

					$tax_totals[ $rate_id ]['amount'] += $tax_amount;
				}
			}
		}

		// Convert to array of taxes with amounts in cents.
		$tax_breakdown = array();
		foreach ( $tax_totals as $tax ) {
			$tax_breakdown[] = array(
				'amount'       => (int) round( $tax['amount'] * 100 ), // Convert dollars to cents.
				'display_name' => $tax['display_name'],
			);
		}

		// Handle case where no taxes apply.
		if ( empty( $tax_breakdown ) ) {
			return array();
		}

		return $tax_breakdown;
	}

	/**
	 * Get product by SKU.
	 *
	 * @param string $sku Product SKU.
	 * @return WC_Product|null
	 */
	private function get_product_by_sku( $sku ) {
		$product_id = wc_get_product_id_by_sku( $sku );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		/** This filter is documented in includes/admin/class-wc-stripe-rest-agentic-approval-controller.php */
		return apply_filters( 'wc_stripe_agentic_product_by_sku', $product, $sku );
	}

	/**
	 * Get request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		$headers = getallheaders();
		return $headers ? $headers : array();
	}
}
