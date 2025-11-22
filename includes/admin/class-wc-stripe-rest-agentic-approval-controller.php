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
		// For now, approve all orders.
		// TODO: Add product stock checks in next task.
		return true;
	}

	/**
	 * Get decline reason.
	 *
	 * @param array $line_items Line items.
	 * @return string
	 */
	private function get_decline_reason( $line_items ) {
		return 'validation_failed';
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
