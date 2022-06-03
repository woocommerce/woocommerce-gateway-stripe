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
						'type'     => 'string',
						'required' => false,
					],
					'limit'          => [
						'type'     => 'integer',
						'required' => false,
					],
					'starting_after' => [
						'type'     => 'string',
						'required' => false,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/store',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_store_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
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
		try {
			$response = WC_Stripe_API::request(
				[
					'display_name' => $request['display_name'],
					'address'      => $request['address'],
				],
				'terminal/locations'
			);
			return rest_ensure_response( $response );
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
	}

	/**
	 * Get all terminal locations via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_all_locations( $request ) {
		try {
			return rest_ensure_response( $this->fetch_locations() );
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
	}

	/**
	 * Delete a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function delete_location( $request ) {
		try {
			$response = WC_Stripe_API::request( [], 'terminal/locations/' . urlencode( $request['location_id'] ), 'DELETE' );
			return rest_ensure_response( $response );
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
	}

	/**
	 * Get a terminal location via Stripe API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_location( $request ) {
		try {
			$response = WC_Stripe_API::request( [], 'terminal/locations/' . urlencode( $request['location_id'] ), 'GET' );
			return rest_ensure_response( $response );
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
	}

	/**
	 * Get default terminal location.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_store_location( $request ) {
		// Originally `get_bloginfo` was used for location name, later switched to `site_url` as the former may be blank.
		$store_hostname = str_replace( [ 'https://', 'http://' ], '', get_site_url() );
		$possible_names = [ get_bloginfo(), $store_hostname ];
		$store_address = WC()->countries;
		$address       = array_filter(
			[
				'city'        => $store_address->get_base_city(),
				'country'     => $store_address->get_base_country(),
				'line1'       => $store_address->get_base_address(),
				'line2'       => $store_address->get_base_address_2(),
				'postal_code' => $store_address->get_base_postcode(),
				'state'       => $store_address->get_base_state(),
			]
		);

		// Return an error if store doesn't have a location.
		$is_address_populated = isset( $address['country'], $address['city'], $address['postal_code'], $address['line1'] );
		if ( ! $is_address_populated ) {
			return rest_ensure_response(
				new WP_Error(
					'store_address_is_incomplete',
					admin_url(
						add_query_arg(
							[
								'page' => 'wc-settings',
								'tab'  => 'general',
							],
							'admin.php'
						)
					)
				)
			);
		}

		try {
			foreach ( $this->fetch_locations() as $location ) {
				if (
					in_array( $location->display_name, $possible_names, true )
					&& count( array_intersect( (array) $location->address, $address ) ) === count( $address )
				) {
					return rest_ensure_response( $location );
				}
			}

			// Create new location if no location matches display name and address.
			$response = WC_Stripe_API::request(
				[
					'display_name' => $store_hostname,
					'address'      => $address,
				],
				'terminal/locations'
			);
			return rest_ensure_response( $response );
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
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
		try {
			$response = WC_Stripe_API::request( $body, 'terminal/locations/' . urlencode( $request['location_id'] ), 'POST' );
			return rest_ensure_response( $response );
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
	}

	/**
	 * Fetch terminal locations from Stripe API.
	 */
	private function fetch_locations() {
		$response = (array) WC_Stripe_API::request( [], 'terminal/locations', 'GET' );
		return $response['data'];
	}
}
