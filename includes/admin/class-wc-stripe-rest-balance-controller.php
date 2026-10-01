<?php
/**
 * Class WC_Stripe_REST_Balance_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe balance to the admin UI.
 *
 * @since 11.1.0
 */
class WC_Stripe_REST_Balance_Controller extends WC_Stripe_REST_Base_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/balance';

	protected const STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS = [
		'object',
		'available',
		'pending',
		'instant_available',
		'livemode',
	];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_balance' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [],
			],
		);
	}

	/**
	 * Retrieve, filters and return the Stripe balance.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_balance( $request ) {
		$response = WC_Stripe_REST_API_Abstract_Client::fetch_from_stripe( 'balance' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS );

		return rest_ensure_response( $filtered_response );
	}
}
