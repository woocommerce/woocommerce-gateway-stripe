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
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_locations' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'ending_before'  => [
						'type' => 'string',
						'required' => false,
					],
					'limit'          => [
						'type' => 'integer',
						'required' => false,
					],
					'starting_after' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<location_id>\w+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
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
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<location_id>\w+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'display_name' => [
						'type'     => 'string',
						'required' => false,
					],
					'address'      => [
						'type'     => 'object',
						'required' => false,
					],
				],
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
		return rest_ensure_response( $response );
	}

	/**
	 * Get all terminal locations via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_all_locations( $request ) {
		return rest_ensure_response( $this->fetch_locations() );
	}

	/**
	 * Delete a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function delete_location( $request ) {
		$response = WC_Stripe_API::request( [], 'terminal/locations/' . urlencode( $request['location_id'] ), 'DELETE' );
		return rest_ensure_response( $response );
	}

	/**
	 * Get a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_location( $request ) {
		$response = WC_Stripe_API::request( [], 'terminal/locations/' . urlencode( $request['location_id'] ), 'GET' );
		return rest_ensure_response( $response );
	}

	/**
	 * Update a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_location( $request ) {
		$body = [];
		if ( isset( $request['display_name'] ) ) {
			$body['display_name'] = $request['display_name'];
		}
		if ( isset( $request['address'] ) ) {
			$body['address'] = $request['address'];
		}
		$response = WC_Stripe_API::request( $body, 'terminal/locations/' . urlencode( $request['location_id'] ), 'POST' );
		return rest_ensure_response( $response );
	}

	/**
	 * Fetch terminal locations from Stripe API.
	 */
	private function fetch_locations() {
		$response = WC_Stripe_API::request( [], 'terminal/locations', 'GET' );
		return $response->data;
	}
}
