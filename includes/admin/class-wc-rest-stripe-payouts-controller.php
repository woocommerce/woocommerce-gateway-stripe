<?php
/**
 * Class WC_REST_Stripe_Payouts_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe balance and payouts data to the admin UI.
 *
 * @since 10.7.0
 */
class WC_REST_Stripe_Payouts_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/payouts';

	/**
	 * Cache key for the balance response.
	 */
	protected const BALANCE_CACHE_KEY = 'wc_stripe_payouts_balance';

	/**
	 * Cache TTL for the balance response. Balance changes slowly and admin page loads can be frequent.
	 */
	protected const BALANCE_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Allowed Stripe payout statuses.
	 *
	 * @var string[]
	 */
	protected const ALLOWED_STATUSES = [ 'paid', 'pending', 'in_transit', 'canceled', 'failed' ];

	/**
	 * Configure REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/balance',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_balance' ],
				'permission_callback' => [ $this, 'check_permission' ],
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
					'status'         => [
						'type'              => 'string',
						'required'          => false,
						'enum'              => self::ALLOWED_STATUSES,
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Get the cache key for storing balance response.
	 *
	 * @return string
	 * @since 10.7.0
	 */
	public static function get_balance_cache_key() {
		return self::BALANCE_CACHE_KEY;
	}

	/**
	 * Retrieve the Stripe account balance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_balance() {
		$cached = WC_Stripe_Database_Cache::get( self::get_balance_cache_key() );
		if ( null !== $cached ) {
			return rest_ensure_response( $cached );
		}

		try {
			$response = WC_Stripe_API::retrieve( 'balance' );
		} catch ( WC_Stripe_Exception $exception ) {
			return new WP_Error( 'wc_stripe_balance_error', $exception->getLocalizedMessage(), [ 'status' => 502 ] );
		}

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_balance_error',
				__( 'Unable to retrieve balance from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 502 ]
			);
		}

		if ( ! empty( $response->error ) ) {
			return new WP_Error(
				'wc_stripe_balance_error',
				$response->error->message ?? __( 'Unable to retrieve balance from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 502 ]
			);
		}

		$data = [
			'available'         => $response->available ?? [],
			'pending'           => $response->pending ?? [],
			'instant_available' => $response->instant_available ?? [],
			'livemode'          => $response->livemode ?? false,
		];

		WC_Stripe_Database_Cache::set( self::get_balance_cache_key(), $data, self::BALANCE_CACHE_TTL );

		return rest_ensure_response( $data );
	}

	/**
	 * Retrieve a paginated list of Stripe payouts.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payouts( $request ) {
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
			$response = WC_Stripe_API::retrieve( 'payouts?' . http_build_query( $query ) );
		} catch ( WC_Stripe_Exception $exception ) {
			return new WP_Error( 'wc_stripe_payouts_error', $exception->getLocalizedMessage(), [ 'status' => 502 ] );
		}

		if ( ! empty( $response->error ) ) {
			return new WP_Error(
				'wc_stripe_payouts_error',
				$response->error->message ?? __( 'Unable to retrieve payouts from Stripe.', 'woocommerce-gateway-stripe' ),
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
