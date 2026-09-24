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

	protected const STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS = [
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
	];

	protected const STRIPE_SINGLE_EXPAND_PARAM = [
		'latest_charge',
		'latest_charge.balance_transaction',
	];

	protected const STRIPE_LIST_RESPONSE_ALLOWED_FIELDS = [
		'object',
		'has_more',
		'data.id',
		'data.created',
		'data.amount',
		'data.currency',
		'data.status',
		'data.description',
		'data.latest_charge.billing_details.name',
	];

	protected const STRIPE_LIST_EXPAND_PARAM = [
		'data.latest_charge',
		'data.latest_charge.balance_transaction',
	];

	protected const STRIPE_LIST_PARAMS_TO_FORWARD = [ 'limit', 'starting_after', 'ending_before', 'customer', 'customer_account', 'created' ];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>' . WC_Stripe_REST_Args_Validator::PAYMENT_INTENT_ID_PATTERN . ')$',
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
						'validate_callback' => [ WC_Stripe_REST_Args_Validator::class, 'validate_payment_intent_pagination_cursor' ],
					],
					'ending_before'    => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ WC_Stripe_REST_Args_Validator::class, 'validate_payment_intent_pagination_cursor' ],
					],
					'customer'         => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ WC_Stripe_REST_Args_Validator::class, 'validate_customer_id' ],
					],
					'customer_account' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ WC_Stripe_REST_Args_Validator::class, 'validate_customer_id' ],
					],
					'created'          => [
						'required'          => false,
						'sanitize_callback' => [ WC_Stripe_REST_Args_Validator::class, 'sanitize_unix_timestamp_range' ],
						'validate_callback' => [ WC_Stripe_REST_Args_Validator::class, 'validate_unix_timestamp_range' ],
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
		$response = WC_Stripe_REST_API_Abstract_Client::fetch_from_stripe( 'payment_intents/' . rawurlencode( $request['id'] ), [ 'expand' => self::STRIPE_SINGLE_EXPAND_PARAM ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_SINGLE_RESPONSE_ALLOWED_FIELDS );

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
		$response = WC_Stripe_REST_API_Abstract_Client::fetch_from_stripe(
			'payment_intents',
			WC_Stripe_REST_API_Abstract_Client::build_params_to_forward( $request, self::STRIPE_LIST_PARAMS_TO_FORWARD, self::STRIPE_LIST_EXPAND_PARAM ),
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, self::STRIPE_LIST_RESPONSE_ALLOWED_FIELDS );

		return rest_ensure_response( $filtered_response );
	}
}
