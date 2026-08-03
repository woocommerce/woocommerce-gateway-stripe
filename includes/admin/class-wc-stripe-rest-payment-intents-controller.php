<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe payment intent details to the admin UI.
 *
 * @since 10.9.0
 */
class WC_Stripe_REST_Payment_Intents_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/payment_intents';

	protected const STRIPE_RESPONSE_ALLOWED_FIELDS = [
		'object',
		'id',
		'amount',
		'amount_received',
		'currency',
		'status',
		'description',
		'latest_charge.balance_transaction.fee',
		'latest_charge.balance_transaction.net',
		'latest_charge.balance_transaction.currency',
		'latest_charge.billing_details',
		'latest_charge.payment_method_details',
	];

	protected const STRIPE_EXPAND_PARAM = [
		'latest_charge',
		'latest_charge.balance_transaction',
	];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '(?:/(?P<id>pi_[A-Za-z0-9_]+))$',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [],
			]
		);
	}

	/**
	 * Retrieve, filters and return one Stripe payment intent.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment_intent( $request ) {
		$response = $this->fetch_from_stripe( 'payment_intents/' . rawurlencode( $request['id'] ), [ 'expand' => self::STRIPE_EXPAND_PARAM ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_RESPONSE_ALLOWED_FIELDS );

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
	protected function fetch_from_stripe( $endpoint, $params ) {
		$query_string = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$stripe_resource_url = $endpoint . ( '' === $query_string ? '' : '?' . $query_string );

		$response = WC_Stripe_API::retrieve( $stripe_resource_url );

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
