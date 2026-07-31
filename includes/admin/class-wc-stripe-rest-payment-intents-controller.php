<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing a Stripe payment intent detais to the admin UI.
 *
 * @since 10.9.0
 */
class WC_Stripe_REST_Payment_Intents_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payment_intents';

	protected array $stripe_response_allowed_fields = [
		'object',
		'id',
		'amount',
		'amount_received',
		'currency',
		'payment_details.order_reference',
		'status',
		'description',
		'latest_charge.balance_transaction.fee',
		'latest_charge.balance_transaction.net',
		'latest_charge.balance_transaction.currency',
		'latest_charge.billing_details',
		'latest_charge.payment_method_details.type',
		'latest_charge.payment_method_details.card.last4',
		'latest_charge.payment_method_details.card.exp_month',
		'latest_charge.payment_method_details.card.exp_year',
		'latest_charge.payment_method_details.card.checks.cvc_check',
		'latest_charge.payment_method_details.card.brand',
		'latest_charge.payment_method_details.card.funding',
		'latest_charge.payment_method_details.card.country',
	];

	protected array $stripe_expand_fields = [
		'latest_charge',
	];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace . '/wc_stripe',
			'/' . $this->rest_base . '(?:/(?P<id>.+))',
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
		$response = $this->fetch_from_stripe( 'payment_intents/' . $request['id'], [ 'expand' => $this->stripe_expand_fields ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, $this->stripe_response_allowed_fields );

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
		$query_string = http_build_query( $params );

		$response = WC_Stripe_API::retrieve( $endpoint . ( $query_string ? '?' . $query_string : '' ) );

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
