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
	protected $rest_base = 'stripe/agentic/compute_tax';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'compute_tax' ],
					'permission_callback' => '__return_true', // Auth handled via signature
				],
			]
		);
	}

	/**
	 * Get registered routes.
	 *
	 * @return array
	 */
	public function get_routes() {
		$routes = [];
		$routes[ '/' . $this->namespace . '/' . $this->rest_base ] = [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'compute_tax' ],
				'permission_callback' => '__return_true',
			],
		];
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
				[ 'status' => 403 ]
			);
		}

		// Verify Stripe signature.
		$verification = $this->verify_stripe_signature();
		if ( is_wp_error( $verification ) ) {
			WC_Stripe_Logger::log(
				'Agentic tax calculation request rejected: invalid signature',
				[ 'error' => $verification->get_error_message() ]
			);
			return $verification;
		}

		// Parse request body.
		$body         = json_decode( $request->get_body(), true );
		$line_items   = $body['line_items_details'] ?? [];
		$fulfillment  = $body['fulfillment_details'] ?? [];
		$billing      = $body['billing_details'] ?? [];
		$currency     = $body['currency'] ?? 'usd';

		WC_Stripe_Logger::log(
			'Agentic tax calculation request received',
			[
				'line_items_count' => count( $line_items ),
				'currency'         => $currency,
			]
		);

		try {
			// Calculate taxes.
			$tax_amounts = $this->calculate_taxes( $line_items, $fulfillment, $billing );

			/**
			 * Filter tax amounts for agentic checkout.
			 *
			 * @since 8.9.0
			 * @param array $tax_amounts Calculated tax amounts.
			 * @param array $line_items  Line items.
			 * @param array $addresses   Fulfillment and billing addresses.
			 */
			$tax_amounts = apply_filters(
				'wc_stripe_agentic_tax_calculation',
				$tax_amounts,
				$line_items,
				[ 'fulfillment' => $fulfillment, 'billing' => $billing ]
			);

			WC_Stripe_Logger::log(
				'Agentic tax calculated',
				[
					'total_tax' => $tax_amounts['total_details']['amount_tax'],
				]
			);

			return new WP_REST_Response( $tax_amounts, 200 );

		} catch ( Exception $e ) {
			WC_Stripe_Logger::log(
				'Agentic tax calculation failed',
				[ 'error' => $e->getMessage() ]
			);

			return new WP_Error(
				'tax_calculation_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Calculate taxes using WooCommerce.
	 *
	 * @param array $line_items  Line items.
	 * @param array $fulfillment Fulfillment details.
	 * @param array $billing     Billing details.
	 * @return array Tax amounts.
	 * @throws Exception If calculation fails.
	 */
	private function calculate_taxes( $line_items, $fulfillment, $billing ) {
		// Use shipping address for tax calculation (or billing if no shipping).
		$tax_address = $fulfillment['address'] ?? $billing['address'] ?? [];

		if ( empty( $tax_address ) ) {
			throw new Exception( 'No address provided for tax calculation' );
		}

		// Set customer location for tax calculation.
		$country    = $tax_address['country'] ?? '';
		$state      = $tax_address['state'] ?? '';
		$postcode   = $tax_address['postal_code'] ?? '';
		$city       = $tax_address['city'] ?? '';

		// Calculate tax for each line item.
		$line_item_details  = [];
		$total_line_tax     = 0;
		$total_shipping_tax = 0;

		foreach ( $line_items as $item ) {
			$sku      = $item['sku_id'] ?? '';
			$price    = ( $item['unit_amount'] ?? 0 ) * ( $item['quantity'] ?? 1 );

			// Get product for tax class.
			$product = $this->get_product_by_sku( $sku );

			if ( ! $product ) {
				throw new Exception( "Product not found for SKU: {$sku}" );
			}

			$tax_class = $product->get_tax_class();

			// Calculate tax for this item.
			$taxes     = WC_Tax::calc_tax( $price, WC_Tax::get_rates( $tax_class, [ $country, $state, $postcode, $city ] ) );
			$tax_total = array_sum( $taxes );

			$line_item_details[] = [
				'sku_id'     => $sku,
				'amount_tax' => (int) $tax_total,
			];

			$total_line_tax += $tax_total;
		}

		// Return formatted response.
		return [
			'line_item_details'   => $line_item_details,
			'fulfillment_details' => [
				'amount_tax' => (int) $total_shipping_tax,
			],
			'total_details'       => [
				'amount_tax' => (int) ( $total_line_tax + $total_shipping_tax ),
			],
		];
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
		return getallheaders() ?: [];
	}
}
