<?php
/**
 * Class WC_REST_Stripe_Exit_Survey_Controller
 *
 * REST controller for the exit survey cooldown.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the exit survey dismiss REST endpoint.
 */
class WC_REST_Stripe_Exit_Survey_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/exit-survey';

	/**
	 * Option name for the cooldown timestamp.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wc_stripe_exit_survey_last_shown';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'dismiss' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Persist the exit survey cooldown timestamp.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return WP_REST_Response
	 */
	public function dismiss( WP_REST_Request $request ) {
		update_option( self::OPTION_NAME, gmdate( 'c' ), false );

		return new WP_REST_Response( [ 'success' => true ] );
	}
}
