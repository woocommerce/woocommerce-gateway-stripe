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

	protected const PAYMENT_INTENT_ID_PATTERN = 'pi_[A-Za-z0-9_]+';
	protected const CUSTOMER_ID_PATTERN       = 'cus_[A-Za-z0-9_]+';

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

	protected const STRIPE_LIST_EXPAND_PARAM = [
		'data.latest_charge',
		'data.latest_charge.balance_transaction',
	];

	protected const STRIPE_LIST_RESPONSE_ALLOWED_FIELDS = [
		'object',
		'has_more',
		'data.id',
		'data.payment_intent',
		'data.created',
		'data.amount',
		'data.currency',
		'data.status',
		'data.description',
		'data.latest_charge.billing_details.name',
		'data.latest_charge.payment_method_details',
	];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>' . self::PAYMENT_INTENT_ID_PATTERN . ')$',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_intents' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'limit'            => [
						'type'              => 'integer',
						'required'          => false,
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
						'validate_callback' => [ self::class, 'validate_starting_after' ],
					],
					'ending_before'    => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_ending_before' ],
					],
					'customer'         => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_customer' ],
					],
					'customer_account' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_customer_account' ],
					],
					'created'          => [
						'required'          => false,
						'sanitize_callback' => [ self::class, 'sanitize_created' ],
						'validate_callback' => [ self::class, 'validate_created' ],
					],
				],
			],
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
	 * Retrieve, filters and return Stripe payment intents.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment_intents( $request ) {
		$response = $this->fetch_from_stripe(
			'payment_intents',
			array_merge(
				$request->get_params(),
				[ 'expand' => self::STRIPE_LIST_EXPAND_PARAM ]
			),
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_LIST_RESPONSE_ALLOWED_FIELDS );

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

	/**
	 * Validate starting_after parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_starting_after( $param_value, $request, $param_name ) {
		return preg_match( '/^' . self::PAYMENT_INTENT_ID_PATTERN . '$/', $param_value ) === 1;
	}

	/**
	 * Validate ending_before parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_ending_before( $param_value, $request, $param_name ) {
		return preg_match( '/^' . self::PAYMENT_INTENT_ID_PATTERN . '$/', $param_value ) === 1;
	}

	/**
	 * Validate customer parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_customer( $param_value, $request, $param_name ) {
		return preg_match( '/^' . self::CUSTOMER_ID_PATTERN . '$/', $param_value ) === 1;
	}

	/**
	 * Validate customer_account parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_customer_account( $param_value, $request, $param_name ) {
		return preg_match( '/^' . self::CUSTOMER_ID_PATTERN . '$/', $param_value ) === 1;
	}

	/**
	 * Sanitize created parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function sanitize_created( $param_value, $request, $param_name ) {
		if ( ! is_array( $param_value ) ) {
			$sanitized_value = sanitize_text_field( $param_value );
		} else {
			$sanitized_value = [];

			foreach ( $param_value as $operator => $operand ) {
				$sanitized_value[ sanitize_key( $operator ) ] = sanitize_text_field( $operand );
			}
		}

		return $sanitized_value;
	}

	/**
	 * Validate created parameter value.
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
	public static function validate_created( $param_value, $request, $param_name ) {
		if ( empty( $param_value ) ) {
			return true;
		}

		if ( is_string( $param_value ) ) {
			return preg_match( '/^\d+$/', $param_value ) === 1;
		}

		if ( ! is_array( $param_value ) ) {
			return false;
		}

		$allowed_operators = [ 'gt', 'gte', 'lt', 'lte' ];

		foreach ( $param_value as $operator => $operand ) {
			if ( ! in_array( $operator, $allowed_operators ) ) {
				return false;
			}

			if ( ! is_scalar( $operand ) || preg_match( '/' . $unix_timestamp_pattern . '/', (string) $operand ) !== 1 ) {
				return false;
			}
		}

		return true;
	}
}
