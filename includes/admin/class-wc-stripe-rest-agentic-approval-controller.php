<?php
/**
 * REST controller for Agentic Checkout approval hook.
 *
 * @package WooCommerce_Stripe/Admin
 * @since   8.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agentic Approval REST Controller.
 */
class WC_Stripe_REST_Agentic_Approval_Controller extends WP_REST_Controller {
	use WC_Stripe_Agentic_Authentication;

	/**
	 * Decline reason for rejected orders.
	 *
	 * @var string|null
	 */
	private $decline_reason = null;

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
	protected $rest_base = 'stripe/agentic/approve';

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
					'callback'            => [ $this, 'approve' ],
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
				'callback'            => [ $this, 'approve' ],
				'permission_callback' => '__return_true',
			],
		];
		return $routes;
	}

	/**
	 * Handle approval request from Stripe.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( $request ) {
		// Check if feature is enabled.
		if ( ! $this->is_agentic_checkout_enabled() ) {
			WC_Stripe_Logger::log( 'Agentic approval request rejected: feature disabled' );
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
				'Agentic approval request rejected: invalid signature',
				[ 'error' => $verification->get_error_message() ]
			);
			return $verification;
		}

		// Parse request body.
		$body               = json_decode( $request->get_body(), true );
		$checkout_session_id = $body['id'] ?? '';
		$line_items         = $body['line_items'] ?? [];
		$amount_total       = $body['amount_total'] ?? 0;
		$payment_method     = $body['payment_method_details'] ?? [];

		WC_Stripe_Logger::log(
			'Agentic approval request received',
			[
				'checkout_session_id' => $checkout_session_id,
				'amount'              => $amount_total,
			]
		);

		// Run validation checks.
		$approved = $this->validate_order( $line_items );

		/**
		 * Filter approval decision for agentic checkout.
		 *
		 * @since 8.9.0
		 * @param bool  $approved            Whether to approve the order.
		 * @param array $checkout_session_data Full checkout session data.
		 * @param array $line_items          Line items being purchased.
		 * @param array $payment_method_details Payment method details.
		 */
		$approved = apply_filters(
			'wc_stripe_agentic_approval_decision',
			$approved,
			$body,
			$line_items,
			$payment_method
		);

		// Build response.
		if ( $approved ) {
			WC_Stripe_Logger::log(
				'Agentic order approved',
				[ 'checkout_session_id' => $checkout_session_id ]
			);

			return new WP_REST_Response(
				[
					'id'     => $checkout_session_id,
					'result' => [
						'type' => 'approved',
					],
				],
				200
			);
		} else {
			$reason = $this->get_decline_reason( $line_items );

			/**
			 * Filter decline reason for agentic checkout.
			 *
			 * @since 8.9.0
			 * @param string $reason Decline reason.
			 * @param array  $checkout_session_data Full checkout session data.
			 */
			$reason = apply_filters(
				'wc_stripe_agentic_decline_reason',
				$reason,
				$body
			);

			WC_Stripe_Logger::log(
				'Agentic order declined',
				[
					'checkout_session_id' => $checkout_session_id,
					'reason'              => $reason,
				]
			);

			return new WP_REST_Response(
				[
					'id'     => $checkout_session_id,
					'result' => [
						'type'     => 'declined',
						'declined' => [
							'reason' => $reason,
						],
					],
				],
				200
			);
		}
	}

	/**
	 * Validate order for approval.
	 *
	 * @param array $line_items Line items to validate.
	 * @return bool True if approved, false if declined.
	 */
	private function validate_order( $line_items ) {
		$items = $line_items['data'] ?? [];

		foreach ( $items as $item ) {
			$sku      = $item['price']['lookup_key'] ?? '';
			$quantity = $item['quantity'] ?? 1;

			// Look up product by SKU.
			$product = $this->get_product_by_sku( $sku );

			if ( ! $product ) {
				$this->decline_reason = 'product_not_found';
				WC_Stripe_Logger::log(
					'Agentic approval declined: product not found',
					[ 'sku' => $sku ]
				);
				return false;
			}

			// Check if product is purchasable.
			if ( ! $product->is_purchasable() ) {
				$this->decline_reason = 'product_not_purchasable';
				WC_Stripe_Logger::log(
					'Agentic approval declined: product not purchasable',
					[ 'sku' => $sku, 'product_id' => $product->get_id() ]
				);
				return false;
			}

			// Check stock.
			if ( ! $product->is_in_stock() ) {
				$this->decline_reason = 'low_inventory';
				WC_Stripe_Logger::log(
					'Agentic approval declined: product out of stock',
					[ 'sku' => $sku, 'product_id' => $product->get_id() ]
				);
				return false;
			}

			// Check stock quantity if managing stock.
			if ( $product->managing_stock() ) {
				$stock_quantity = $product->get_stock_quantity();
				if ( $stock_quantity < $quantity ) {
					$this->decline_reason = 'low_inventory';
					WC_Stripe_Logger::log(
						'Agentic approval declined: insufficient stock',
						[
							'sku'            => $sku,
							'product_id'     => $product->get_id(),
							'requested'      => $quantity,
							'available'      => $stock_quantity,
						]
					);
					return false;
				}
			}
		}

		return true;
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

		/**
		 * Filter product lookup by SKU for agentic checkout.
		 *
		 * @since 8.9.0
		 * @param WC_Product|null $product Product object or null if not found.
		 * @param string          $sku     Product SKU.
		 */
		return apply_filters( 'wc_stripe_agentic_product_by_sku', $product, $sku );
	}

	/**
	 * Get decline reason.
	 *
	 * @param array $line_items Line items.
	 * @return string
	 */
	private function get_decline_reason( $line_items ) {
		return $this->decline_reason ?? 'validation_failed';
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
