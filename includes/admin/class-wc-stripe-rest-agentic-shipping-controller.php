<?php
/**
 * REST controller for Agentic Checkout shipping calculation hook.
 *
 * @package WooCommerce_Stripe/Admin
 * @since   8.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agentic Shipping Calculation REST Controller.
 */
class WC_Stripe_REST_Agentic_Shipping_Controller extends WP_REST_Controller {
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
	protected $rest_base = 'stripe/agentic/compute_shipping_options';

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
					'callback'            => [ $this, 'compute_shipping_options' ],
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
				'callback'            => [ $this, 'compute_shipping_options' ],
				'permission_callback' => '__return_true',
			],
		];
		return $routes;
	}

	/**
	 * Handle shipping calculation request from Stripe.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function compute_shipping_options( $request ) {
		// Check if feature is enabled.
		if ( ! $this->is_agentic_checkout_enabled() ) {
			WC_Stripe_Logger::log( 'Agentic shipping calculation request rejected: feature disabled' );
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
				'Agentic shipping calculation request rejected: invalid signature',
				[ 'error' => $verification->get_error_message() ]
			);
			return $verification;
		}

		// Parse request body.
		$body        = json_decode( $request->get_body(), true );
		$line_items  = $body['line_items_details'] ?? [];
		$fulfillment = $body['fulfillment_details'] ?? [];
		$currency    = $body['currency'] ?? 'usd';

		WC_Stripe_Logger::log(
			'Agentic shipping calculation request received',
			[
				'line_items_count' => count( $line_items ),
				'destination'      => $fulfillment['address'] ?? [],
			]
		);

		try {
			// Calculate shipping options.
			$shipping_options = $this->calculate_shipping( $line_items, $fulfillment );

			/**
			 * Filter shipping options for agentic checkout.
			 *
			 * @since 8.9.0
			 * @param array $shipping_options Calculated shipping options.
			 * @param array $cart_items       Cart items (line items).
			 * @param array $destination      Destination address.
			 */
			$shipping_options = apply_filters(
				'wc_stripe_agentic_shipping_options',
				$shipping_options,
				$line_items,
				$fulfillment['address'] ?? []
			);

			WC_Stripe_Logger::log(
				'Agentic shipping calculated',
				[
					'options_count' => count( $shipping_options ),
				]
			);

			return new WP_REST_Response(
				[ 'fulfillment_options' => $shipping_options ],
				200
			);

		} catch ( Exception $e ) {
			WC_Stripe_Logger::log(
				'Agentic shipping calculation failed',
				[ 'error' => $e->getMessage() ]
			);

			return new WP_Error(
				'shipping_calculation_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Calculate shipping options using WooCommerce.
	 *
	 * @param array $line_items  Line items.
	 * @param array $fulfillment Fulfillment details.
	 * @return array Shipping options.
	 * @throws Exception If calculation fails.
	 */
	private function calculate_shipping( $line_items, $fulfillment ) {
		$address = $fulfillment['address'] ?? [];

		if ( empty( $address ) ) {
			throw new Exception( 'No destination address provided' );
		}

		// Build cart items for shipping calculation.
		$cart_items = [];
		foreach ( $line_items as $item ) {
			$sku      = $item['sku_id'] ?? '';
			$quantity = $item['quantity'] ?? 1;

			$product = $this->get_product_by_sku( $sku );

			if ( ! $product ) {
				throw new Exception( "Product not found for SKU: {$sku}" );
			}

			// Skip non-shippable products.
			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$cart_items[] = [
				'product'  => $product,
				'quantity' => $quantity,
			];
		}

		// If no shippable items, return empty options.
		if ( empty( $cart_items ) ) {
			return [];
		}

		// Get shipping methods for destination.
		$shipping_methods = $this->get_shipping_methods_for_address( $cart_items, $address );

		// Format shipping options for Stripe.
		$options = [];
		foreach ( $shipping_methods as $method ) {
			$options[] = [
				'id'                      => $method['id'],
				'display_name'            => $method['label'],
				'description'             => $method['description'] ?? '',
				'shipping_amount'         => (int) ( $method['cost'] * 100 ), // Convert to cents
				'earliest_delivery_time'  => $method['earliest_delivery'] ?? null,
				'latest_delivery_time'    => $method['latest_delivery'] ?? null,
			];
		}

		return $options;
	}

	/**
	 * Get shipping methods for address.
	 *
	 * @param array $cart_items Cart items.
	 * @param array $address    Destination address.
	 * @return array Shipping methods.
	 */
	private function get_shipping_methods_for_address( $cart_items, $address ) {
		// Set destination for shipping calculation.
		$country  = $address['country'] ?? '';
		$state    = $address['state'] ?? '';
		$postcode = $address['postal_code'] ?? '';
		$city     = $address['city'] ?? '';

		// Build package for shipping calculation.
		$package = [
			'destination' => [
				'country'  => $country,
				'state'    => $state,
				'postcode' => $postcode,
				'city'     => $city,
				'address'  => $address['line1'] ?? '',
				'address_2' => $address['line2'] ?? '',
			],
			'contents'    => [],
		];

		// Add cart items to package.
		foreach ( $cart_items as $item ) {
			$product = $item['product'];
			$package['contents'][] = [
				'data'     => $product,
				'quantity' => $item['quantity'],
			];
		}

		// Get available shipping methods.
		$shipping_zone    = WC_Shipping_Zones::get_zone_matching_package( $package );
		$shipping_methods = $shipping_zone->get_shipping_methods( true );

		$available_methods = [];
		foreach ( $shipping_methods as $method ) {
			if ( ! $method->is_enabled() ) {
				continue;
			}

			// Calculate cost for this method.
			$method->calculate_shipping( $package );
			$rates = $method->rates ?? [];

			foreach ( $rates as $rate ) {
				$available_methods[] = [
					'id'          => $rate->get_id(),
					'label'       => $rate->get_label(),
					'cost'        => $rate->get_cost(),
					'description' => $rate->get_label(),
				];
			}
		}

		return $available_methods;
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
		return $headers ? $headers : [];
	}
}
