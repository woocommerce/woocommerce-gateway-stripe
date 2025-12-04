<?php
/**
 * Class WC_Stripe_REST_UPE_Flag_Toggle_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for UPE feature flag.
 *
 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
 */
class WC_Stripe_REST_UPE_Flag_Toggle_Controller extends WC_Stripe_REST_Base_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/upe_flag_toggle';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_flag' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_flag' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_upe_enabled' => [
						'description'       => __( 'Determines if the UPE feature flag is enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve flag status.
	 *
	 * @return WP_REST_Response
	 *
	 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
	 */
	public function get_flag() {
		return new WP_REST_Response(
			[
				'is_upe_enabled' => true,
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @deprecated 10.2.0 UPE is generally available and this endpoint will be removed in a future release.
	 */
	public function set_flag( WP_REST_Request $request ) {
		// including the class again because otherwise it's not present.
		if ( WC_Stripe_Inbox_Notes::are_inbox_notes_supported() ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
			WC_Stripe_UPE_Availability_Note::possibly_delete_note();

			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-stripelink-note.php';
			WC_Stripe_UPE_StripeLink_Note::possibly_delete_note();
		}

		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order();

		return new WP_REST_Response( [ 'result' => 'success' ], 200 );
	}
}
