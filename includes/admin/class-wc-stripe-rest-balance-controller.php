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
		$response = $this->fetch_from_stripe( 'balance' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS );

		return rest_ensure_response( $filtered_response );
	}

	/**
	 * Fetch data from an Stripe API endpoint and returns its raw data or a WP_Error if an error occurs.
	 *
	 * @param string $endpoint The Stripe endpoint.
	 * @param array $params Parameters to pass to the endpoint.
	 *
	 * @return StdClass|WP_Error
	 */
	protected function fetch_from_stripe( $endpoint ) {
		$response = WC_Stripe_API::retrieve( $endpoint );

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_error',
				__( 'Unable to fetch data from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 401 ]
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_object( $response ) && isset( $response->error ) ) {
			$error_code    = isset( $response->error->code ) ? (string) $response->error->code : 'wc_stripe_api_error';
			$error_message = isset( $response->error->message ) ? (string) $response->error->message : __( 'Stripe API returned an error.', 'woocommerce-gateway-stripe' );

			return new WP_Error( $error_code, $error_message, [ 'status' => 400 ] );
		}

		return $response;
	}
}
