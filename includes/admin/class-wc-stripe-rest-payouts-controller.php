<?php
/**
 * Class WC_Stripe_REST_Payouts_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe payout details to the admin UI.
 *
 * @since 10.9.0
 */
class WC_Stripe_REST_Payouts_Controller extends WC_Stripe_REST_Base_Controller {

	protected const PAYOUT_ID_PATTERN = 'po_[A-Za-z0-9_]+';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/payouts';

	protected const STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS = [
		'object',
		'id',
		'created',
		'amount',
		'currency',
		'status',
		'method',
		'arrival_date',
		'destination',
	];

	protected const STRIPE_SINGLE_EXPAND_PARAM = [];

	protected const STRIPE_LIST_RESPONSE_ALLOWED_FIELDS = [
		'object',
		'has_more',
		'data.id',
		'data.created',
		'data.amount',
		'data.currency',
		'data.status',
		'data.method',
		'data.arrival_date',
		'data.destination',
	];

	protected const STRIPE_LIST_EXPAND_PARAM = [ 'data.destination' ];

	protected const STRIPE_LIST_PARAMS_TO_FORWARD = [ 'limit', 'starting_after', 'ending_before', 'status', 'created', 'arrival_date', 'destination' ];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>' . self::PAYOUT_ID_PATTERN . ')$',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payout' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payouts' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'limit'            => [
						'type'              => 'integer',
						'required'          => false,
						'default'           => 10,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'starting_after'   => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_starting_after' ],
					],
					'ending_before'    => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_ending_before' ],
					],
					'status'           => [
						'type'              => 'string',
						'required'          => false,
						'enum'              => [ 'pending', 'paid', 'failed', 'canceled' ],
					],
					'arrival_date'          => [
						'required'          => false,
						'sanitize_callback' => [ self::class, 'sanitize_unix_timestamp' ],
						'validate_callback' => [ self::class, 'validate_unix_timestamp' ],
					],
					'created'          => [
						'required'          => false,
						'sanitize_callback' => [ self::class, 'sanitize_unix_timestamp' ],
						'validate_callback' => [ self::class, 'validate_unix_timestamp' ],
					],
					'destination'    => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_non_empty_string' ],
					]
				],
			],
		);
	}

	/**
	 * Retrieve, filters and return one Stripe payout.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payout( $request ) {
		$response = $this->fetch_from_stripe( 'payouts/' . rawurlencode( $request['id'] ), [ 'expand' => self::STRIPE_SINGLE_EXPAND_PARAM ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS );

		return rest_ensure_response( $filtered_response );
	}

	/**
	 * Retrieve, filters and return Stripe payouts.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payouts( $request ) {
		$response = $this->fetch_from_stripe(
			'payouts',
			self::build_params_to_forward( $request, self::STRIPE_LIST_PARAMS_TO_FORWARD, self::STRIPE_LIST_EXPAND_PARAM ),
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_LIST_RESPONSE_ALLOWED_FIELDS );

		return rest_ensure_response( $filtered_response );
	}

	/**
	 * Builds an array of parameters to forward to Stripe API.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 * @param array $params_to_forward Names of params to forward.
	 * @param array $expand_param Array of value to populate the 'expand' Stripe AAPI param.
	 *
	 * @return array
	 */
	private static function build_params_to_forward( $request, $params_to_forward, $expand_param ) {
		$stripe_params = array_intersect_key(
			$request->get_params(),
			array_flip( $params_to_forward )
		);

		$stripe_params['expand'] = $expand_param;

		return $stripe_params;
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

	/**
	 * Validate starting_after parameter value that should be a payout ID.
	 * Also raise an error if the ending_before parameter is also specified.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return WP_Error|bool
	 */
	public static function validate_starting_after( $param_value, $request, $param_name ) {
		if ( $request->has_param( 'ending_before' ) ) {
			return new WP_Error(
				'invalid_starting_after',
				__( 'Received both starting_after and ending_before parameters. Please pass in only one.', 'woocommerce-gateway-stripe' )
			);
		}

		return self::validate_payout_id( $param_value, $request, $param_name );
	}

	/**
	 * Validate ending_before parameter value that should be a payout ID.
	 * Also raise an error if the starting_after parameter is also specified.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return WP_Error|bool
	 */
	public static function validate_ending_before( $param_value, $request, $param_name ) {
		if ( $request->has_param( 'starting_after' ) ) {
			return new WP_Error(
				'invalid_ending_before',
				__( 'Received both starting_after and ending_before parameters. Please pass in only one.', 'woocommerce-gateway-stripe' )
			);
		}

		return self::validate_payout_id( $param_value, $request, $param_name );
	}

	/**
	 * Validate a parameter value that should be a payout ID.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	private static function validate_payout_id( $param_value, $request, $param_name ) {
		return 1 === preg_match( '/^' . self::PAYOUT_ID_PATTERN . '$/', $param_value );
	}

	/**
	 * Sanitize a Unix timestamp parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return mixed
	 */
	public static function sanitize_unix_timestamp( $param_value, $request, $param_name ) {
		if ( ! is_array( $param_value ) ) {
			$sanitized_value = self::is_valid_timestamp( $param_value ) ? (int) $param_value : '';
		} else {
			$sanitized_value = [];

			foreach ( $param_value as $operator => $operand ) {
				if ( self::is_valid_timestamp( $operand ) ) {
					$sanitized_value[ sanitize_key( $operator ) ] = (int) $operand;
				} else {
					$sanitized_value[ sanitize_key( $operator ) ] = '';
				}
			}
		}

		return $sanitized_value;
	}

	/**
	 * Validate a Unix timestamp parameter value
	 *
	 * Validates that the parameter is either a Unix timestamp containing digits only,
	 * or an array of Unix timestamps keyed by comparison operators (gt, gte, lt, lte).
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_unix_timestamp( $param_value, $request, $param_name ) {
		if ( self::is_valid_timestamp( $param_value ) ) {
			return true;
		}

		if ( ! is_array( $param_value ) ) {
			return false;
		}

		$allowed_operators = [ 'gt', 'gte', 'lt', 'lte' ];

		foreach ( $param_value as $operator => $operand ) {
			if ( ! in_array( $operator, $allowed_operators, true ) ) {
				return false;
			}

			if ( ! self::is_valid_timestamp( $operand ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a timestamp value.
	 *
	 * Validates that the value represents a non-negative integer, either as an int or as a non-empty string containing digits only.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	private static function is_valid_timestamp( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		return ctype_digit( $value ) && ( (int) $value >= 0 );
	}

	/**
	 * Validate that a parameter is a non-empty string.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_non_empty_string( $param_value, $request, $param_name ) {
		return  '' !== trim( $param_value );
	}
}
