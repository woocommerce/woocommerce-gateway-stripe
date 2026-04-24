<?php

defined( 'ABSPATH' ) || exit;

/**
 * Admin REST controller for toggling the diagnostics feature on or off.
 *
 * Routes:
 *   GET  /wc/v3/wc_stripe/diagnostics/state   current toggle state + trace count
 *   POST /wc/v3/wc_stripe/diagnostics/toggle  flip the toggle
 */
class WC_REST_Stripe_Diagnostics_Toggle_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint base path. Shared with WC_REST_Stripe_Diagnostics_Controller
	 * so all diagnostics routes group under `/wc_stripe/diagnostics`.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/diagnostics';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/state',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => [ $this, 'admin_check' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/toggle',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'toggle' ],
				'permission_callback' => [ $this, 'admin_check' ],
				'args'                => [
					'enabled' => [
						'required' => true,
						'type'     => 'boolean',
					],
				],
			]
		);
	}

	/**
	 * Permission callback. Restricted to WooCommerce admins.
	 *
	 * @return bool
	 */
	public function admin_check() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Return `{ enabled, count, limit }`.
	 *
	 * @return WP_REST_Response
	 */
	public function get_state() {
		$store = new WC_Stripe_Diagnostics_Trace_Store();
		return new WP_REST_Response(
			[
				'enabled' => WC_Stripe_Diagnostics_Recorder::is_enabled(),
				'count'   => $store->count(),
				'limit'   => WC_Stripe_Diagnostics_Recorder::CAPTURE_LIMIT,
			],
			200
		);
	}

	/**
	 * Flip the diagnostics toggle.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function toggle( $request ) {
		$enabled = (bool) $request->get_param( 'enabled' );
		update_option( 'wc_stripe_diagnostics_enabled', $enabled ? 'yes' : 'no' );
		return $this->get_state();
	}
}
