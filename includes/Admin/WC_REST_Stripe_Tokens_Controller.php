<?php
/***
 * Class WC_REST_Stripe_Tokens_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for tokens.
 */
class WC_REST_Stripe_Tokens_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 */
	protected $rest_base = 'wc_stripe/tokens';

	/**
	 * Register REST API routes for Stripe tokens.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			// For more info on Stripe tokens, see the following:
			// https://docs.stripe.com/api/tokens/object
			'/' . $this->rest_base . '/(?P<token_id>[a-z]{3}_[a-zA-Z0-9]{24})',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_token' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve a Stripe token, given a secret-key and token_id.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_token( $request ) {
		$token_id   = $request->get_param( 'token_id' );
		$secret_key = $request->get_header( 'X-WCStripe-Secret-Key' );

		try {
			WC_Stripe_API::set_secret_key( $secret_key );
			$response = WC_Stripe_API::request( [], "tokens/$token_id", 'GET' );

			if ( ! empty( $response->error ) ) {
				return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
			}
		} catch ( Exception $exception ) {
			return new WP_REST_Response( [ 'result' => 'bad_request' ], 400 );
		}

		return new WP_REST_Response( $response, 200 );
	}
}
