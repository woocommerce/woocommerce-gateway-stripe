<?php
/**
 * Class WC_REST_Stripe_Payment_Intents_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe payment intents data to the admin UI.
 *
 * @since 10.7.0
 */
class WC_REST_Stripe_Payment_Intents_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/transactions';

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
				'callback'            => [ $this, 'get_payment_intents' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'limit'          => [
						'type'              => 'integer',
						'default'           => 25,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'starting_after' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'ending_before'  => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve a paginated list of Stripe payment intents.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment_intents( $request ) {
		$query = [
			'limit' => $request->get_param( 'limit' ),
		];

		$starting_after = $request->get_param( 'starting_after' );
		if ( ! empty( $starting_after ) ) {
			$query['starting_after'] = $starting_after;
		}

		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) ) {
			$query['status'] = $status;
		}

		try {
			$response = WC_Stripe_API::retrieve( 'payment_intents?' . http_build_query( $query ) );
		} catch ( WC_Stripe_Exception $exception ) {
			return new WP_Error( 'wc_stripe_payment_intents_error', $exception->getLocalizedMessage(), [ 'status' => 502 ] );
		}

		if ( ! empty( $response->error ) ) {
			return new WP_Error(
				'wc_stripe_payment_intents_error',
				$response->error->message ?? __( 'Unable to retrieve payment intents from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 502 ]
			);
		}

		return rest_ensure_response(
			[
				'data'     => $response->data ?? [],
				'has_more' => $response->has_more ?? false,
			]
		);
	}
}
