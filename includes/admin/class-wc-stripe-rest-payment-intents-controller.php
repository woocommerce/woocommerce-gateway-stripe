<?php
/**
 * Class WC_Stripe_REST_Payment_Intents_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe payment intents data to the admin UI.
 *
 * The controller acts as a proxy that forwards the received parameters to the remote Stripe API and returns the received response.
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

	/**
	 * Endpoint args.
	 *
	 * @var array
	 */
	protected $rest_args = [
		'limit'            => [
			'type'              => 'integer',
			'default'           => 25,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'starting_after'   => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'ending_before'    => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'customer'         => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'customer_account' => [
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'created'          => [
			'required'          => false,
			'sanitize_callback' => [ self::class, 'sanitize_created_field' ],
			'validate_callback' => [ self::class, 'validate_created_field' ],
		],
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
				'callback'            => [ $this, 'get_payment_intents' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->get_payment_intents_route_args(),
			]
		);
	}

	/**
	 * Return route args.
	 *
	 * @return array
	 */
	public function get_payment_intents_route_args(): array {
		return $this->rest_args;
	}

	/**
	 * Given an incoming REST request, build and return an array of query parameters to be appended to Stripe API request URL.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 *
	 * @return array
	 */
	public function build_http_query_array_from_request( $request ): array {
		$rest_args = $this->get_payment_intents_route_args();

		$search_params = [];

		/**
		 * Search parameter value.
		 *
		 * @var string $search_param_name
		*/
		foreach ( $rest_args as $search_param_name => $search_param_definition ) {
			$search_param_value = $request->get_param( $search_param_name );

			if ( '' === $search_param_value || is_null( $search_param_value ) ) {
				continue;
			}

			$search_params[ $search_param_name ] = $search_param_value;
		}

		return $search_params;
	}

	/**
	 * Given an incoming REST request, build and return a query parameters string to be appended to Stripe API request URL.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 *
	 * @return string
	 */
	public function build_http_query_string_from_request( $request ): string {
		return http_build_query( $this->build_http_query_array_from_request( $request ) );
	}

	/**
	 * Retrieve a paginated list of Stripe payment intents.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment_intents( $request ) {
		try {
			$response = WC_Stripe_API::retrieve( 'payment_intents?' . $this->build_http_query_string_from_request( $request ) );
		} catch ( WC_Stripe_Exception $exception ) {
			return new WP_Error( 'wc_stripe_payment_intents_error', $exception->getLocalizedMessage(), [ 'status' => 502 ] );
		}

		if ( is_null( $response ) ) {
			return new WP_Error(
				'wc_stripe_payment_intents_error',
				$response->error->message ?? __( 'Unable to retrieve payment intents from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 502 ]
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Sanitize a "created" parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return mixed
	 */
	public static function sanitize_created_field( $value, WP_REST_Request $request, string $param ) {
		if ( ! is_array( $value ) ) {
			$value = sanitize_text_field( $value );
		} else {
			$sanitized_value = [];

			foreach ( $value as $operator => $operand ) {
				$sanitized_value[ sanitize_key( $operator ) ] = sanitize_text_field( $operand );
			}

			$value = $sanitized_value;
		}

		return $value;
	}

	/**
	 * Validate a "created" parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_created_field( $value, WP_REST_Request $request, string $param ): bool {
		if ( empty( $value ) ) {
			return true;
		}

		$unix_timestamp_pattern = '^\d+$';

		if ( is_string( $value ) ) {
			return preg_match( '/' . $unix_timestamp_pattern . '/', $value ) === 1;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		$allowed_operators = [ 'gt', 'gte', 'lt', 'lte' ];

		foreach ( $value as $operator => $operand ) {
			if ( ! in_array( $operator, $allowed_operators ) ) {
				return false;
			}

			if ( preg_match( '/' . $unix_timestamp_pattern . '/', $operand ) !== 1 ) {
				return false;
			}
		}

		return true;
	}
}
