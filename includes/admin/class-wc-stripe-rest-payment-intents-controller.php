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
	protected $rest_base = 'payment_intents';

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
			'sanitize_callback' => [ WC_Stripe_REST_Validator::class, 'sanitize_timestamp' ],
			'validate_callback' => [ WC_Stripe_REST_Validator::class, 'validate_timestamp' ],
		],
		'query'            => [
			'type'              => 'array',
			'required'          => false,
			'sanitize_callback' => [ self::class, 'sanitize_query_field' ],
			'validate_callback' => [ self::class, 'validate_query_field' ],
		],
	];

	/**
	 * Endpoint query args.
	 *
	 * @var array
	 */
	protected static $rest_query_args = [
		'amount'   => 'numeric',
		'created'  => 'numeric',
		'currency' => 'token',
		'customer' => 'token',
		'metadata' => 'token',
		'status'   => 'token',
	];

	protected array $stripe_response_allowed_fields = [
		'object'                                    => '',
		'has_more'                                  => '',
		'data.id'                                   => '',
		'data.payment_intent'                       => '',
		'data.created'                              => [ WC_Stripe_REST_Response_Filter::class, 'date_format' ],
		'data.amount'                               => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'data.currency'                             => 'strtoupper',
		'data.status'                               => [ WC_Stripe_REST_Response_Filter::class, 'status_format' ],
		'data.description'                          => '',
		'data.latest_charge.billing_details.name'   => '',
		'data.latest_charge.payment_method_details' => [ WC_Stripe_REST_Response_Filter::class, 'payment_method_details_format' ],
	];

	protected array $stripe_details_response_allowed_fields = [
		'object'                                                     => '',
		'id'                                                         => '',
		'created'                                                    => [ WC_Stripe_REST_Response_Filter::class, 'date_format' ],
		'amount'                                                     => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'amount_received'                                            => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'currency'                                                   => 'strtoupper',
		'payment_details.order_reference'                            => '',
		'status'                                                     => [ WC_Stripe_REST_Response_Filter::class, 'status_format' ],
		'description'                                                => '',
		'latest_charge.balance_transaction.fee'                      => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'latest_charge.balance_transaction.net'                      => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
		'latest_charge.balance_transaction.currency'                 => 'strtoupper',
		'latest_charge.billing_details'                              => '',
		'latest_charge.payment_method_details.type'                  => 'ucfirst',
		'latest_charge.payment_method_details.card.last4'            => '',
		'latest_charge.payment_method_details.card.exp_month'        => '',
		'latest_charge.payment_method_details.card.exp_year'         => '',
		'latest_charge.payment_method_details.card.checks.cvc_check' => 'ucfirst',
		'latest_charge.payment_method_details.card.brand'            => 'ucfirst',
		'latest_charge.payment_method_details.card.funding'          => '',
		'latest_charge.payment_method_details.card.country'          => 'strtoupper',
	];

	protected array $stripe_expand_fields = [
		'data.latest_charge',
	];

	protected array $stripe_details_expand_fields = [
		'latest_charge.balance_transaction',
		'latest_charge.billing_details',
		'latest_charge.payment_method_details',
	];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace . '/wc_stripe',
			'/payment_intents(?:/(?P<id>.+))?',
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
	 * Retrieve a paginated list of Stripe payment intents.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment_intents( $request ) {
		$search_params = $request->get_params();

		if ( isset( $search_params['id'] ) ) {
			$expand            = http_build_query( [ 'expand' => $this->stripe_details_expand_fields ] );
			$stripe_url_ending = '/' . $search_params['id'] . '?' . $expand;
		} else {
			$expand            = http_build_query( [ 'expand' => $this->stripe_expand_fields ] );
			$stripe_url_ending = ( WC_Stripe_REST_Helper::is_search_request( $request ) ? '/search' : '' ) . '?' . WC_Stripe_REST_Helper::build_http_query_string_from_request( $request, $this->get_payment_intents_route_args(), $this->stripe_expand_fields );
		}

		$response = WC_Stripe_API::retrieve( 'payment_intents' . $stripe_url_ending );

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_payment_intents_error',
				__( 'Unable to retrieve payment intents from Stripe.', 'woocommerce-gateway-stripe' ),
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

		if ( ! isset( $search_params['id'] ) ) {
			$allowed_fields = $this->stripe_response_allowed_fields;
		} else {
			$allowed_fields = $this->stripe_details_response_allowed_fields;
		}

		$filtered_response = WC_Stripe_REST_Response_Filter::filter_response( $response, $allowed_fields );

		return rest_ensure_response( $filtered_response );
	}

	/**
	 * Validate a 'query' parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_query_field( $value, WP_REST_Request $request, string $param ) {
		return WC_Stripe_REST_Validator::validate_query( $value, $request, $param, self::$rest_query_args );
	}

	/**
	 * Sanitize a 'query' parameter value.
	 *
	 * @param array $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return mixed
	 */
	public static function sanitize_query_field( $value, WP_REST_Request $request, string $param ) {
		return WC_Stripe_REST_Validator::sanitize_query( $value, $request, $param, self::$rest_query_args );
	}
}
