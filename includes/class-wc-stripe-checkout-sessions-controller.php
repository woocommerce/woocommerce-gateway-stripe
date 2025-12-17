<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_Sessions_Controller class.
 */
class WC_Stripe_Checkout_Sessions_Controller extends WC_Stripe_REST_Base_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/checkout-sessions';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [],
			]
		);
	}

	/**
	 * Retrieve flag status.
	 *
	 * @return WP_REST_Response
	 */
	public function create( WP_REST_Request $request ) {
		return new WP_REST_Response( [ 'test' ], 200 );
	}

}
