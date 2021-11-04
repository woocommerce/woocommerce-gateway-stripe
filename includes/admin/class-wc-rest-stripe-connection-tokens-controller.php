<?php
/**
 * Class WC_REST_Stripe_Connection_Tokens_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for connection tokens.
 */
class WC_REST_Stripe_Connection_Tokens_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/connection_tokens';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_token' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Create a connection token via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function create_token( $request ) {
		return WC_Stripe_API::request( [], 'terminal/connection_tokens' );
	}
}
