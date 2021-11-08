<?php
/**
 * Class WC_REST_Stripe_Locations_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for terminal locations.
 */
class WC_REST_Stripe_Locations_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/terminal/locations';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'display_name' => [
						'type'     => 'string',
						'required' => true,
					],
					'address'      => [
						'type'     => 'object',
						'required' => true,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<location_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Create a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function create_location( $request ) {
		$response = WC_Stripe_API::request(
			[
				'display_name' => $request['display_name'],
				'address'      => $request['address'],
			],
			'terminal/locations'
		);
		return new WP_Rest_Response( [ 'data' => $response ] );
	}

	/**
	 * Get a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_location( $request ) {
		$response = WC_Stripe_API::request( [], 'terminal/locations/' . urlencode( $request['location_id'] ) );
		return new WP_Rest_Response( [ 'data' => $response ] );
	}
}
